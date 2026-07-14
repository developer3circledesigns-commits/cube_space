<?php
ob_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db_config.php';
    cubespace_require_project('lib/cors.php');
    set_cors_headers('POST, OPTIONS');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    cubespace_require_project('lib/ratelimit.php');
    cubespace_require_project('lib/validator.php');
    cubespace_require_project('src/autoload.php');
    cubespace_require_project('lib/events.php');
    ob_end_clean();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die(json_encode(['success' => false, 'message' => 'Method not allowed', 'data' => null, 'errors' => null]));
    }

    $honeypot = trim($_POST['website'] ?? '');
    if ($honeypot !== '') {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid request', 'data' => null, 'errors' => null]));
    }

    $submittedIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $limiter = new RateLimiter(5, 300, 'contact_');
    if (!$limiter->check($submittedIp)) {
        http_response_code(429);
        die(json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.', 'data' => null, 'errors' => null]));
    }

    $validator = new Validator($_POST);
    if (!$validator->validate([
        'name'         => 'required|max:255',
        'phone'        => 'required|phone',
        'email'        => 'email|max:255',
        'company'      => 'max:160',
        'message'      => 'max:1000',
        'interest'     => 'in:managed,furnished,unfurnished',
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

    $stmt = mysqli_prepare($conn,
        "INSERT INTO contacts (name, phone, email, interest, company, seats, message, office_id, listing_code, source, submitted_ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $officeIdVal = $officeId !== '' ? (int)$officeId : null;

    if ($officeIdVal !== null) {
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
        ];

        publish_event('contact_created', 'contact', $newId, "$name - $interest");

        // Send response immediately, then send email in background
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
            if (ob_get_level()) { ob_end_flush(); }
            flush();
        }

        // Send email in background after response is sent
        try {
            $mail = new \CubeSpace\EmailService();
            $mail->notifyAdminNewContact($contactData);
        } catch (\Throwable $e) {
            error_log('CubeSpace background email error: ' . $e->getMessage());
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
