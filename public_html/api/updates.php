<?php
declare(strict_types=1);

require_once __DIR__ . '/db_config.php';
cubespace_require_project('lib/cors.php');
cubespace_require_project('lib/events.php');

set_cors_headers('GET, OPTIONS');

$since = isset($_GET['since']) ? max(0, (int)$_GET['since']) : 0;
if (!$since) $since = (int)(microtime(true) * 1000);

if (isset($_GET['stream']) && $_GET['stream'] === 'true') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Access-Control-Allow-Credentials: true');

    set_time_limit(300);

    // Send initial handshake
    echo ": open\n\n";
    ob_flush();
    flush();

    $loopCount = 0;
    $maxLoops = 150;

    while ($loopCount < $maxLoops) {
        if (connection_aborted()) {
            break;
        }

        $events = get_events_since($since, null, 50);
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
            echo ": heartbeat\n\n";
            ob_flush();
            flush();
        }

        if (connection_status() !== CONNECTION_NORMAL) {
            break;
        }
        
        usleep(2000000);
        $loopCount++;
    }
    exit;
}

$events = get_events_since($since, null, 50);
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

