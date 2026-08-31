<?php
ob_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db_config.php';
    cubespace_require_project('lib/cors.php');
    set_cors_headers('POST, OPTIONS');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    cubespace_require_project('lib/validator.php');
    cubespace_require_project('src/autoload.php');
    cubespace_require_project('lib/events.php');
    ob_end_clean();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(['success' => false, 'message' => 'Method not allowed', 'data' => null, 'errors' => null]));
    }

    // --- Honeypot check (hidden website field) ---
    // Silently acknowledge bots with fake success
    $honeypot = trim($_POST['website'] ?? '');
    if ($honeypot !== '') {
        error_log('[contact.php] Honeypot triggered from IP ' . ($_SERVER['REMOTE_ADDR'] ?? '') . ' — UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        die(json_encode(['success' => true, 'message' => 'Thank you! Your enquiry has been submitted.', 'data' => null, 'errors' => null]));
    }

    // --- Spam timing check (logging only to prevent clock skew / rapid click 400 errors) ---
    $mseTsRaw = trim($_POST['mse_ts'] ?? '');
    if ($mseTsRaw !== '' && is_numeric($mseTsRaw)) {
        $mseTs = (int)$mseTsRaw;
        if ($mseTs > 1000000000000) {
            $mseTs = (int)($mseTs / 1000);
        }
        $age = time() - $mseTs;
        if ($age < -300 || $age > 86400 * 7) {
            error_log('[contact.php] Suspicious timestamp: age=' . $age . 's, mse_ts=' . $mseTsRaw . ', IP=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        }
    }

    $submittedIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // --- Rate limiting: max 20 submissions per IP per 60s ---
    if ($submittedIp !== '' && isset($conn) && $conn) {
        $rateStmt = @mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts WHERE submitted_ip = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
        if ($rateStmt) {
            mysqli_stmt_bind_param($rateStmt, 's', $submittedIp);
            if (mysqli_stmt_execute($rateStmt)) {
                $r = mysqli_stmt_get_result($rateStmt);
                $row = $r ? mysqli_fetch_assoc($r) : null;
                if ($row && (int)$row['cnt'] >= 20) {
                    mysqli_stmt_close($rateStmt);
                    http_response_code(429);
                    die(json_encode(['success' => false, 'message' => 'Too many requests. Please try again in a minute.', 'data' => null, 'errors' => null]));
                }
            }
            mysqli_stmt_close($rateStmt);
        }
    }

    // --- Deduplication: same phone cannot submit identical enquiry within 60s ---
    $phoneForDedup = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    if ($phoneForDedup !== '' && isset($conn) && $conn) {
        if (strlen($phoneForDedup) > 10 && str_starts_with($phoneForDedup, '91')) {
            $phoneForDedup = substr($phoneForDedup, -10);
        } elseif (strlen($phoneForDedup) === 11 && str_starts_with($phoneForDedup, '0')) {
            $phoneForDedup = substr($phoneForDedup, 1);
        }
        $dedupStmt = @mysqli_prepare($conn, "SELECT id FROM contacts WHERE phone LIKE CONCAT('%', ?) AND created_at > (NOW() - INTERVAL 60 SECOND) LIMIT 1");
        if ($dedupStmt) {
            $likePhone = substr($phoneForDedup, -10);
            mysqli_stmt_bind_param($dedupStmt, 's', $likePhone);
            if (mysqli_stmt_execute($dedupStmt)) {
                $r2 = mysqli_stmt_get_result($dedupStmt);
                if ($r2 && mysqli_fetch_assoc($r2)) {
                    mysqli_stmt_close($dedupStmt);
                    http_response_code(429);
                    die(json_encode(['success' => false, 'message' => 'Duplicate enquiry detected. Please wait a moment before resubmitting.', 'data' => null, 'errors' => null]));
                }
            }
            mysqli_stmt_close($dedupStmt);
        }
    }

    // --- Input Validation ---
    $validator = new Validator($_POST);
    if (!$validator->validate([
        'name'         => 'required|max:255',
        'phone'        => 'required|phone',
        'email'        => 'email|max:255',
        'company'      => 'max:255',
        'message'      => 'max:5000',
        'office_id'    => 'integer|min:0',
        'listing_code' => 'max:100',
        'source'       => 'max:1000',
    ])) {
        error_log('[contact.php] Validation failed: ' . json_encode($validator->errors()) . ' — POST: ' . json_encode($_POST));
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => $validator->firstError() ?? 'Validation failed. Please check the entered fields.',
            'data'    => null,
            'errors'  => $validator->errors(),
        ]));
    }

    $name        = strip_tags(trim($_POST['name'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $email       = strip_tags(trim($_POST['email'] ?? ''));
    $rawInterest = strtolower(strip_tags(trim($_POST['interest'] ?? '')));
    $company     = strip_tags(trim($_POST['company'] ?? ''));
    $seats       = strip_tags(trim($_POST['seats'] ?? ''));
    $message     = strip_tags(trim($_POST['message'] ?? ''));
    $officeIdRaw = trim($_POST['office_id'] ?? '');
    $listingCode = strip_tags(trim($_POST['listing_code'] ?? ''));
    $source      = strip_tags(trim($_POST['source'] ?? ''));
    $officesJson = trim($_POST['offices_json'] ?? '');

    // Normalize interest
    $validInterests = ['managed', 'furnished', 'unfurnished', 'commercial'];
    if (in_array($rawInterest, $validInterests, true)) {
        $interest = $rawInterest;
    } elseif ($rawInterest === 'managed_offices') {
        $interest = 'managed';
    } elseif ($rawInterest === 'furnished_offices') {
        $interest = 'furnished';
    } elseif ($rawInterest === 'unfurnished_offices') {
        $interest = 'unfurnished';
    } elseif ($rawInterest !== '') {
        $interest = 'commercial';
    } else {
        $interest = 'managed';
    }

    $selectedOffices = [];
    $workspacesSummary = '';
    $isMulti = false;

    // Table mapping for listing types
    $tableMap = [
        'managed' => 'managed_offices',
        'managed_offices' => 'managed_offices',
        'furnished' => 'furnished_offices',
        'furnished_offices' => 'furnished_offices',
        'unfurnished' => 'unfurnished_offices',
        'unfurnished_offices' => 'unfurnished_offices',
        'commercial' => 'furnished_offices',
    ];

    if ($officesJson !== '') {
        $isMulti = true;
        $decoded = is_array($_POST['offices_json'] ?? null) ? $_POST['offices_json'] : json_decode($officesJson, true);
        if (is_array($decoded) && count($decoded) > 0) {
            // Cap at max 20 workspaces
            if (count($decoded) > 20) {
                $decoded = array_slice($decoded, 0, 20);
            }

            // Group IDs by normalized type for DB lookup
            $idsByType = ['managed' => [], 'furnished' => [], 'unfurnished' => []];
            foreach ($decoded as $item) {
                if (!is_array($item)) continue;
                $oid = (int)($item['id'] ?? 0);
                $rawType = strtolower(trim((string)($item['listing_type'] ?? '')));
                $normType = 'managed';
                if (str_contains($rawType, 'unfurn')) {
                    $normType = 'unfurnished';
                } elseif (str_contains($rawType, 'furn') || str_contains($rawType, 'comm')) {
                    $normType = 'furnished';
                } elseif (str_contains($rawType, 'manage')) {
                    $normType = 'managed';
                }
                if ($oid > 0) {
                    $idsByType[$normType][] = $oid;
                }
            }

            $validMap = [];
            foreach ($idsByType as $otype => $ids) {
                if (empty($ids)) continue;
                $ids = array_values(array_unique($ids));
                $table = $tableMap[$otype] ?? 'managed_offices';
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                $sql = "SELECT id, title, listing_code FROM `{$table}` WHERE id IN ({$placeholders})";
                $checkStmt = mysqli_prepare($conn, $sql);
                if ($checkStmt) {
                    $bindParams = array_merge([$types], $ids);
                    $refs = [];
                    foreach ($bindParams as $k => $v) { $refs[$k] = &$bindParams[$k]; }
                    call_user_func_array([$checkStmt, 'bind_param'], $refs);
                    mysqli_stmt_execute($checkStmt);
                    $res = mysqli_stmt_get_result($checkStmt);
                    if ($res) {
                        while ($officeRow = mysqli_fetch_assoc($res)) {
                            $key = $otype . ':' . $officeRow['id'];
                            $validMap[$key] = $officeRow;
                        }
                    }
                    mysqli_stmt_close($checkStmt);
                }
            }

            foreach ($decoded as $item) {
                if (!is_array($item)) continue;
                $oid = (int)($item['id'] ?? 0);
                $rawType = strtolower(trim((string)($item['listing_type'] ?? '')));
                $normType = 'managed';
                if (str_contains($rawType, 'unfurn')) {
                    $normType = 'unfurnished';
                } elseif (str_contains($rawType, 'furn') || str_contains($rawType, 'comm')) {
                    $normType = 'furnished';
                }
                $key = $normType . ':' . $oid;
                $dbRow = $validMap[$key] ?? null;

                $officeTitle = $dbRow ? strip_tags(trim($dbRow['title'] ?? '')) : strip_tags(trim((string)($item['title'] ?? 'Workspace #' . $oid)));
                $officeCode  = $dbRow ? strip_tags(trim($dbRow['listing_code'] ?? '')) : strip_tags(trim((string)($item['listing_code'] ?? '')));

                $selectedOffices[] = [
                    'id'           => $oid,
                    'title'        => mb_substr($officeTitle, 0, 200),
                    'listing_code' => mb_substr($officeCode, 0, 50),
                    'listing_type' => $normType,
                ];
            }

            if (!empty($selectedOffices)) {
                $lines = ["Selected workspaces (" . count($selectedOffices) . "):"];
                foreach ($selectedOffices as $idx => $off) {
                    $line = ($idx + 1) . '. ' . $off['title'];
                    if (!empty($off['listing_code'])) {
                        $line .= ' [' . $off['listing_code'] . ']';
                    }
                    $line .= ' (ID: ' . (int)$off['id'] . ', ' . $off['listing_type'] . ')';
                    $lines[] = mb_substr($line, 0, 300);
                }
                $workspacesSummary = implode("\n", $lines);
                $source = !empty($source) ? $source : 'multi_select_enquiry';
                $listingCode = 'MULTI (' . count($selectedOffices) . ')';
                $userMessage = $message !== '' ? "\n\nAdditional message:\n" . $message : '';
                $combined = $workspacesSummary . $userMessage;
                if (mb_strlen($combined) > 10000) {
                    $combined = mb_substr($combined, 0, 9980) . "\n[truncated]";
                }
                $message = $combined;

                $distinctTypes = array_unique(array_column($selectedOffices, 'listing_type'));
                if (count($distinctTypes) > 1) {
                    $interest = 'commercial';
                } elseif (count($distinctTypes) === 1) {
                    $interest = $distinctTypes[0];
                }
            }
        }
    }

    // Office ID resolution for single office enquiries
    $officeIdVal = null;
    if (!$isMulti && $officeIdRaw !== '' && is_numeric($officeIdRaw) && (int)$officeIdRaw > 0) {
        $candidateId = (int)$officeIdRaw;
        // Verify against all active office tables
        $checkStmt = @mysqli_prepare($conn, "SELECT id FROM managed_offices WHERE id = ? UNION SELECT id FROM furnished_offices WHERE id = ? UNION SELECT id FROM unfurnished_offices WHERE id = ? UNION SELECT id FROM office_spaces WHERE id = ?");
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'iiii', $candidateId, $candidateId, $candidateId, $candidateId);
            mysqli_stmt_execute($checkStmt);
            $res = mysqli_stmt_get_result($checkStmt);
            if ($res && mysqli_num_rows($res) > 0) {
                $officeIdVal = $candidateId;
            }
            mysqli_stmt_close($checkStmt);
        }
    }

    // Sanitize lengths for database columns
    $listingCodeVal = !empty($listingCode) ? mb_substr($listingCode, 0, 100) : null;
    $sourceVal      = !empty($source) ? mb_substr($source, 0, 255) : 'website';
    $companyVal     = !empty($company) ? mb_substr($company, 0, 160) : null;
    $seatsVal       = !empty($seats) ? mb_substr($seats, 0, 50) : null;
    $emailVal       = !empty($email) ? mb_substr($email, 0, 255) : null;

    $stmt = mysqli_prepare($conn,
        "INSERT INTO contacts (name, phone, email, interest, company, seats, message, office_id, listing_code, source, submitted_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log('[contact.php] Prepare failed: ' . mysqli_error($conn));
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to save your request. Please try again.', 'data' => null, 'errors' => null]));
    }

    mysqli_stmt_bind_param($stmt, 'sssssssissss',
        $name, $phone, $emailVal, $interest, $companyVal, $seatsVal, $message,
        $officeIdVal,
        $listingCodeVal,
        $sourceVal, $submittedIp, $userAgent
    );

    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        $contactData = [
            'name'               => $name,
            'phone'              => $phone,
            'email'              => $email,
            'interest'           => $interest,
            'company'            => $company,
            'seats'              => $seats,
            'message'            => $message,
            'office_id'          => $officeIdVal,
            'listing_code'       => $listingCode,
            'source'             => $sourceVal,
            'ip'                 => $submittedIp,
            'user_agent'         => $userAgent,
            'workspaces_summary' => $workspacesSummary,
            'selected_offices'   => $selectedOffices,
        ];

        try {
            publish_event('contact_created', 'contact', $newId, "$name - $interest");
            if ($isMulti) {
                publish_event('multi_select_enquiry', 'contact', $newId, "$name - $interest (" . count($selectedOffices) . " workspaces) | " . implode(',', array_column($selectedOffices, 'id')));
                publish_event('contact_created_multi', 'contact', $newId, "$name - $interest");
            }
        } catch (\Throwable $evErr) {
            error_log('[contact.php] Event publish error: ' . $evErr->getMessage());
        }

        $response = json_encode(['success' => true, 'message' => 'Thank you! We will get back to you shortly.', 'data' => ['id' => $newId], 'errors' => null]);

        // Close DB connection early so background email doesn't hold it
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        $conn = null;

        // Flush response to client
        echo $response;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level()) { ob_end_flush(); }
            flush();
            if (function_exists('ignore_user_abort')) ignore_user_abort(true);
        }

        // Send email in background after response is sent
        try {
            $mail = new \CubeSpace\EmailService();
            $sent = $mail->notifyAdminNewContact($contactData);
            if (!$sent) {
                error_log('CubeSpace email not sent for contact #' . $newId . ' - will log for retry');
                @file_put_contents(__DIR__ . '/../storage/email_failed.log', date('c') . ' contact#' . $newId . ' email failed ' . json_encode($contactData) . PHP_EOL, FILE_APPEND);
            }
        } catch (\Throwable $e) {
            error_log('CubeSpace background email error for contact #' . $newId . ': ' . $e->getMessage());
            @file_put_contents(__DIR__ . '/../storage/email_failed.log', date('c') . ' contact#' . $newId . ' exception ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        exit;
    } else {
        $err = mysqli_error($conn);
        error_log('[contact.php] Execute failed: ' . $err);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save your request. Please try again.', 'data' => null, 'errors' => null]);
    }

    if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
    if (isset($conn) && $conn) mysqli_close($conn);

} catch (\Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    error_log('CubeSpace contact.php error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.', 'data' => null, 'errors' => null]);
}
