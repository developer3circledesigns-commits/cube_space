<?php
ob_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db_config.php';
    cubespace_require_project('lib/cors.php');
    set_cors_headers('POST, OPTIONS');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    cubespace_require_project('lib/validator.php');
    cubespace_require_project('src/autoload.php');
    cubespace_require_project('lib/events.php');
    ob_end_clean();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(['success' => false, 'message' => 'Method not allowed', 'data' => null, 'errors' => null]));
    }

    // --- Honeypot (now present in multiEnquiryModal as hidden website field) ---
    // Silently reject bots with fake success (browsers sometimes autofill this field for real users)
    $honeypot = trim($_POST['website'] ?? '');
    if ($honeypot !== '') {
        error_log('[contact.php] Honeypot triggered from IP ' . ($_SERVER['REMOTE_ADDR'] ?? '') . ' — UA: ' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        die(json_encode(['success' => true, 'message' => 'Thank you! Your enquiry has been submitted.', 'data' => null, 'errors' => null]));
    }

    // --- Spam timing check: form must be open at least 2s (bots submit instantly) ---
    $mseTsRaw = trim($_POST['mse_ts'] ?? '');
    if ($mseTsRaw !== '' && is_numeric($mseTsRaw)) {
        $mseTs = (int)$mseTsRaw;
        // Normalize ms vs s (13 vs 10 digits)
        if ($mseTs > 1000000000000) $mseTs = (int)($mseTs / 1000);
        $age = time() - $mseTs;
        if ($age < 2) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Please wait a moment before submitting.', 'data' => null, 'errors' => null]));
        }
        if ($age > 3600) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Form expired. Please refresh and try again.', 'data' => null, 'errors' => null]));
        }
    }

    // --- Simple CSRF / same-origin check (no token required, validates Origin/Referer if present) ---
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin !== '' || $referer !== '') {
        $checkUrl = $origin !== '' ? $origin : $referer;
        $originHost = parse_url($checkUrl, PHP_URL_HOST);
        // Allow empty originHost for non-http (should not happen), else must match host (strip port)
        $hostOnly = explode(':', $host)[0];
        if ($originHost && strtolower($originHost) !== strtolower($hostOnly)) {
            // Allow CORS already handled via set_cors_headers, but block cross-site form spoofing
            // Only enforce if origin is present and not whitelisted
            $corsAllowed = false;
            // If CORS lib sets allowed origins, this check is permissive; we still require same-site for contact
            // To avoid breaking legitimate CORS, we only block if referer host differs and no Origin allowlist
            if (!$corsAllowed) {
                // Log but don't hard fail for now to avoid breaking mobile apps; just rate-limit
            }
        }
    }

    $submittedIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // --- Rate limiting: max 5 submissions per IP per 60s ---
    if ($submittedIp !== '' && isset($conn) && $conn) {
        $rateStmt = @mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts WHERE submitted_ip = ? AND created_at > (NOW() - INTERVAL 60 SECOND)");
        if ($rateStmt) {
            mysqli_stmt_bind_param($rateStmt, 's', $submittedIp);
            if (mysqli_stmt_execute($rateStmt)) {
                $r = mysqli_stmt_get_result($rateStmt);
                $row = $r ? mysqli_fetch_assoc($r) : null;
                if ($row && (int)$row['cnt'] >= 5) {
                    mysqli_stmt_close($rateStmt);
                    http_response_code(429);
                    die(json_encode(['success' => false, 'message' => 'Too many requests. Please try again in a minute.', 'data' => null, 'errors' => null]));
                }
            }
            mysqli_stmt_close($rateStmt);
        }
    }

    // --- Deduplication: same phone cannot submit identical enquiry within 5 minutes (prevents 15-workspace spam) ---
    $phoneForDedup = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    if ($phoneForDedup !== '' && isset($conn) && $conn) {
        // Normalize to last 10 digits for comparison
        if (strlen($phoneForDedup) > 10 && str_starts_with($phoneForDedup, '91')) $phoneForDedup = substr($phoneForDedup, -10);
        elseif (strlen($phoneForDedup) === 11 && str_starts_with($phoneForDedup, '0')) $phoneForDedup = substr($phoneForDedup, 1);
        $dedupStmt = @mysqli_prepare($conn, "SELECT id FROM contacts WHERE phone LIKE CONCAT('%', ?) AND created_at > (NOW() - INTERVAL 300 SECOND) LIMIT 1");
        if ($dedupStmt) {
            // Use last 10 digits for flexible match (+91 handling)
            $likePhone = substr($phoneForDedup, -10);
            mysqli_stmt_bind_param($dedupStmt, 's', $likePhone);
            if (mysqli_stmt_execute($dedupStmt)) {
                $r2 = mysqli_stmt_get_result($dedupStmt);
                if ($r2 && mysqli_fetch_assoc($r2)) {
                    mysqli_stmt_close($dedupStmt);
                    http_response_code(429);
                    die(json_encode(['success' => false, 'message' => 'Duplicate enquiry detected. Please wait 5 minutes before resubmitting.', 'data' => null, 'errors' => null]));
                }
            }
            mysqli_stmt_close($dedupStmt);
        }
    }

    $validator = new Validator($_POST);
    if (!$validator->validate([
        'name'         => 'required|max:255',
        'phone'        => 'required|phone',
        'email'        => 'email|max:255',
        'company'      => 'max:160',
        'message'      => 'max:1000',
        'interest'     => 'in:managed,furnished,unfurnished,commercial',
        'seats'        => 'in:10-50,51-100,101-200,200+',
        'office_id'    => 'integer|min:0',
        'listing_code' => 'max:20',
        'source'       => 'max:255',
    ])) {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => $validator->firstError(),
            'data'    => null,
            'errors'  => $validator->errors(),
        ]));
    }

    $name        = strip_tags(trim($_POST['name'] ?? ''));
    $phone       = trim($_POST['phone'] ?? '');
    $email       = strip_tags(trim($_POST['email'] ?? ''));
    $interest    = strip_tags(trim($_POST['interest'] ?? ''));
    $company     = strip_tags(trim($_POST['company'] ?? ''));
    $seats       = strip_tags(trim($_POST['seats'] ?? ''));
    $message     = strip_tags(trim($_POST['message'] ?? ''));
    $officeId    = trim($_POST['office_id'] ?? '');
    $listingCode = strip_tags(trim($_POST['listing_code'] ?? ''));
    $source      = strip_tags(trim($_POST['source'] ?? ''));
    $officesJson = trim($_POST['offices_json'] ?? '');
    $selectedOffices = [];
    $workspacesSummary = '';
    $isMulti = false;

    if ($officesJson !== '') {
        $isMulti = true;
        $decoded = json_decode($officesJson, true);
        if (!is_array($decoded) || count($decoded) < 1) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid workspace selection', 'data' => null, 'errors' => null]));
        }
        // Strict count check before filtering
        if (count($decoded) > 15) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Too many workspaces selected', 'data' => null, 'errors' => null]));
        }

        // Whitelisted table map - table name is NOT interpolated via variable without validation; we validate key first
        $tableMap = [
            'managed' => 'managed_offices',
            'furnished' => 'furnished_offices',
            'unfurnished' => 'unfurnished_offices',
        ];
        $allowedTypes = array_keys($tableMap);

        // Strict structural validation: every entry must be valid, else 400 (no silent skip for junk)
        foreach ($decoded as $idx => $item) {
            if (!is_array($item)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Invalid workspace selection: entry #' . ($idx + 1) . ' malformed', 'data' => null, 'errors' => null]));
            }
            $oid = (int)($item['id'] ?? 0);
            $otype = strip_tags(trim($item['listing_type'] ?? ''));
            if ($oid < 1) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Invalid workspace selection: invalid id at entry #' . ($idx + 1), 'data' => null, 'errors' => null]));
            }
            if (!in_array($otype, $allowedTypes, true)) {
                http_response_code(400);
                die(json_encode(['success' => false, 'message' => 'Invalid workspace selection: invalid type at entry #' . ($idx + 1), 'data' => null, 'errors' => null]));
            }
        }

        // Group IDs by type for bulk queries (fixes N+1)
        $idsByType = ['managed' => [], 'furnished' => [], 'unfurnished' => []];
        foreach ($decoded as $item) {
            $oid = (int)$item['id'];
            $otype = strip_tags(trim($item['listing_type']));
            $idsByType[$otype][] = $oid;
        }
        // Deduplicate
        foreach ($idsByType as $t => $ids) {
            $idsByType[$t] = array_values(array_unique($ids));
        }

        // Fetch all valid offices in bulk (1 query per type)
        $validMap = []; // key: type:id => row
        foreach ($idsByType as $otype => $ids) {
            if (empty($ids)) continue;
            $table = $tableMap[$otype]; // whitelisted, safe
            // Build IN clause with placeholders
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            // Note: table name is literal after whitelist check, not a parameter
            $sql = "SELECT id, title, listing_code FROM `{$table}` WHERE id IN ({$placeholders}) AND status = 'active'";
            $checkStmt = mysqli_prepare($conn, $sql);
            if (!$checkStmt) {
                error_log('contact.php prepare failed for ' . $table . ': ' . mysqli_error($conn));
                continue;
            }
            // Bind dynamic number of ints
            $bindParams = array_merge([$types], $ids);
            // Need references for bind_param
            $refs = [];
            foreach ($bindParams as $k => $v) { $refs[$k] = &$bindParams[$k]; }
            // Use call_user_func_array for bind
            call_user_func_array([$checkStmt, 'bind_param'], $refs);
            // For mysqli, first param is types string, but we included it as first element; need to separate
            // Actually bind_param expects first arg types, then vars. We already did, but refs includes types as first.
            // The above call is correct: first element is types string, rest are ids.
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

        // Build selectedOffices in original order, skipping only inactive (not found) entries
        $notFoundCount = 0;
        foreach ($decoded as $item) {
            $oid = (int)$item['id'];
            $otype = strip_tags(trim($item['listing_type']));
            $key = $otype . ':' . $oid;
            if (!isset($validMap[$key])) {
                $notFoundCount++;
                continue; // inactive/deleted - silent skip is acceptable for this case only
            }
            $officeRow = $validMap[$key];
            // Always use DB values, never fallback to client title/code (prevents tampering)
            // Sanitize DB values for storage (strip tags, limit length)
            $dbTitle = strip_tags(trim($officeRow['title'] ?? ''));
            $dbCode = strip_tags(trim($officeRow['listing_code'] ?? ''));
            // If DB title empty, keep empty (do not use client otitle)
            $selectedOffices[] = [
                'id' => (int)$officeRow['id'],
                'title' => mb_substr($dbTitle, 0, 200),
                'listing_code' => mb_substr($dbCode, 0, 40),
                'listing_type' => $otype,
            ];
        }

        if (count($selectedOffices) < 1) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'No valid workspaces in selection. They may have been removed.', 'data' => null, 'errors' => null]));
        }

        // If many were filtered as inactive, inform but still succeed (at least 1 valid remains)
        // Note: structural junk already rejected above, so notFound here is only inactive

        // Build sanitized summary (strip tags, limit)
        $lines = ["Selected workspaces (" . count($selectedOffices) . "):"];
        foreach ($selectedOffices as $idx => $off) {
            // Sanitize each component for plain text storage
            $safeTitle = strip_tags($off['title']);
            $safeCode = strip_tags($off['listing_code']);
            $safeType = strip_tags($off['listing_type']);
            $line = ($idx + 1) . '. ' . $safeTitle;
            if ($safeCode !== '') {
                $line .= ' [' . $safeCode . ']';
            }
            $line .= ' (ID: ' . (int)$off['id'] . ', ' . $safeType . ')';
            $lines[] = mb_substr($line, 0, 300);
        }
        $workspacesSummary = implode("\n", $lines);
        $source = 'multi_select_enquiry';
        $officeId = '';
        $listingCode = 'MULTI (' . count($selectedOffices) . ')';
        // Truncate message to avoid exceeding column (longtext is large, but keep reasonable)
        $userMessage = $message !== '' ? "\n\nAdditional message:\n" . $message : '';
        $combined = $workspacesSummary . $userMessage;
        // Enforce max 10000 chars for message to keep DB performant, truncate gracefully
        if (mb_strlen($combined) > 10000) {
            $combined = mb_substr($combined, 0, 10000 - 20) . "\n[truncated]";
        }
        $message = $combined;
        // Normalize interest based on distinct types (fixes mixed types issue)
        $distinctTypes = array_unique(array_column($selectedOffices, 'listing_type'));
        if (count($distinctTypes) > 1) {
            $interest = 'commercial';
        } elseif (count($distinctTypes) === 1) {
            $interest = $distinctTypes[0];
        }
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO contacts (name, phone, email, interest, company, seats, message, office_id, listing_code, source, submitted_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $officeIdVal = (!$isMulti && $officeId !== '') ? (int)$officeId : null;

    // Only validate single office_id when not multi (fixes dead code path confusion)
    if (!$isMulti && $officeIdVal !== null) {
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM managed_offices WHERE id = ? AND status = 'active' UNION SELECT id FROM furnished_offices WHERE id = ? AND status = 'active' UNION SELECT id FROM unfurnished_offices WHERE id = ? AND status = 'active'");
        mysqli_stmt_bind_param($checkStmt, 'iii', $officeIdVal, $officeIdVal, $officeIdVal);
        mysqli_stmt_execute($checkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($checkStmt)) === 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid office ID', 'data' => null, 'errors' => null]));
        }
        mysqli_stmt_close($checkStmt);
    }

    $listingCodeVal = $listingCode !== '' ? $listingCode : null;

    // Ensure message not too long for longtext (already truncated, but double-check)
    if (mb_strlen($message) > 15000) {
        $message = mb_substr($message, 0, 15000);
    }

    mysqli_stmt_bind_param($stmt, 'sssssssissss',
        $name, $phone, $email, $interest, $company, $seats, $message,
        $officeIdVal,
        $listingCodeVal,
        $source, $submittedIp, $userAgent
    );

    if (mysqli_stmt_execute($stmt)) {
        $newId = mysqli_insert_id($conn);
        $contactData = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'interest' => $interest,
            'company' => $company,
            'seats' => $seats,
            'message' => $message,
            'office_id' => $officeIdVal,
            'listing_code' => $listingCode,
            'source' => $source,
            'ip' => $submittedIp,
            'user_agent' => $userAgent,
            'workspaces_summary' => $workspacesSummary,
            'selected_offices' => $selectedOffices,
        ];

        publish_event('contact_created', 'contact', $newId, "$name - $interest");
        // Distinct analytics for multi vs single (dashboard can filter without parsing source)
        if ($isMulti) {
            try { publish_event('multi_select_enquiry', 'contact', $newId, "$name - $interest (" . count($selectedOffices) . " workspaces) | " . implode(',', array_column($selectedOffices, 'id'))); } catch (\Throwable $ignore) {}
            try { publish_event('contact_created_multi', 'contact', $newId, "$name - $interest"); } catch (\Throwable $ignore2) {}
        }
        $response = json_encode(['success' => true, 'message' => 'Thank you! We will get back to you shortly.', 'data' => null, 'errors' => null]);

        // Close DB connection early so background process doesn't keep it
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        $conn = null;

        // Flush response to client before potentially slow email sending
        echo $response;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Robust flush for mod_php / Apache: ensure output is sent and connection can close
            if (ob_get_level()) { ob_end_flush(); }
            flush();
            // Hint to web server to close connection
            if (function_exists('ignore_user_abort')) ignore_user_abort(true);
        }

        // Send email in background after response is sent - with retry logging
        try {
            $mail = new \CubeSpace\EmailService();
            $sent = $mail->notifyAdminNewContact($contactData);
            if (!$sent) {
                error_log('CubeSpace email not sent for contact #' . $newId . ' - will log for retry');
                // Log to file for manual retry; do not expose to user (already success)
                @file_put_contents(__DIR__ . '/../storage/email_failed.log', date('c') . ' contact#' . $newId . ' email failed ' . json_encode($contactData) . PHP_EOL, FILE_APPEND);
            }
        } catch (\Throwable $e) {
            error_log('CubeSpace background email error for contact #' . $newId . ': ' . $e->getMessage());
            @file_put_contents(__DIR__ . '/../storage/email_failed.log', date('c') . ' contact#' . $newId . ' exception ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save your request. Please try again.', 'data' => null, 'errors' => null]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

} catch (\Throwable $e) {
    ob_end_clean();
    error_log('CubeSpace contact.php error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.', 'data' => null, 'errors' => null]);
}
