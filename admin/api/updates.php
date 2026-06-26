<?php
declare(strict_types=1);

require_once __DIR__ . '/../jwt_helper.php';
require_once __DIR__ . '/../../api/db_config.php';
require_once __DIR__ . '/../../lib/events.php';

$auth = require_jwt_auth();
if (!$auth) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => null, 'errors' => null]);
    exit;
}

$since = isset($_GET['since']) ? max(0, (int)$_GET['since']) : 0;
if (!$since) $since = (int)(microtime(true) * 1000);

if (isset($_GET['stream']) && $_GET['stream'] === 'true') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    set_time_limit(0);

    // Send initial handshake
    echo ": open\n\n";
    ob_flush();
    flush();

    while (true) {
        if (connection_aborted()) {
            break;
        }

        $events = get_events_since($since, null, 100);
        if (!empty($events)) {
            $maxTs = $since;
            foreach ($events as $event) {
                if ($event['created_at_ts'] > $maxTs) {
                    $maxTs = (int)$event['created_at_ts'];
                }
            }
            $since = $maxTs;

            echo "data: " . json_encode([
                'events' => $events,
                'timestamp' => $since
            ]) . "\n\n";
            ob_flush();
            flush();
        } else {
            // Keep connection alive
            echo ": ping\n\n";
            ob_flush();
            flush();
        }

        usleep(1000000); // 1 second
    }
    exit;
}

$events = get_events_since($since, null, 100);
$now = (int)(microtime(true) * 1000);

header('Content-Type: application/json');
echo json_encode([
    'success'   => true,
    'message'   => 'OK',
    'data'      => [
        'events'    => $events,
        'timestamp' => $now,
    ],
    'errors'    => null,
]);

