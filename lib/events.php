<?php
declare(strict_types=1);

if (!isset($conn)) {
    foreach ([__DIR__ . '/../api/db_config.php', __DIR__ . '/../../api/db_config.php'] as $dbFile) {
        if (is_file($dbFile)) {
            require_once $dbFile;
            break;
        }
    }
}

const ALLOWED_EVENT_TYPES = [
    'listing_created',
    'listing_updated',
    'listing_deleted',
    'contact_created',
    'contact_updated',
    'contact_deleted',
    'review_created',
    'review_updated',
    'review_deleted',
    'faq_created',
    'faq_updated',
    'faq_deleted',
    'building_updated',
    'leasing_created',
    'leasing_updated',
    'leasing_deleted',
    'bulk_operation',
];

function publish_event(string $eventType, ?string $entityType = null, ?int $entityId = null, ?string $summary = null): bool {
    if (!in_array($eventType, ALLOWED_EVENT_TYPES, true)) {
        error_log("CubeSpace: Invalid event type: $eventType");
        return false;
    }

    global $conn;
    $stmt = $conn->prepare(
        "INSERT INTO realtime_events (event_type, entity_type, entity_id, summary) VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $stmt->bind_param('ssis', $eventType, $entityType, $entityId, $summary);
    $result = $stmt->execute();
    $stmt->close();

    if (class_exists('JsonCache')) {
        JsonCache::incrementGlobalVersion();
    }

    return $result;
}

function get_events_since(int $timestamp, ?string $eventType = null, int $limit = 100): array {
    global $conn;

    $dt = date('Y-m-d H:i:s', (int)($timestamp / 1000));

    $sql = "SELECT id, event_type, entity_type, entity_id, summary, created_at 
            FROM realtime_events WHERE created_at > ?";
    $params = [$dt];
    $types = 's';

    if ($eventType) {
        $sql .= " AND event_type = ?";
        $params[] = $eventType;
        $types .= 's';
    }

    $sql .= " ORDER BY created_at ASC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $row['created_at_ts'] = strtotime($row['created_at']) * 1000;
        $events[] = $row;
    }
    $stmt->close();

    return $events;
}

function get_events_last_check(): int {
    return (int)($_SESSION['realtime_last_check'] ?? 0);
}

function set_events_last_check(int $timestamp): void {
    $_SESSION['realtime_last_check'] = $timestamp;
}
