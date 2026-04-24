<?php
/**
 * Cleanup script to delete proctoring data older than 30 days
 * Can be run manually or via cron job: 0 2 * * * php /path/to/cleanup_old_snapshots.php
 * Usage: php cleanup_old_snapshots.php [days] [verbose]
 */

require_once __DIR__ . '/../config/db.php';

$retention_days = isset($argv[1]) ? intval($argv[1]) : 30;
$verbose = isset($argv[2]) && $argv[2] === 'verbose';

$cutoff_date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));

if ($verbose) {
    echo "Proctoring Data Cleanup (retention: $retention_days days)\n";
    echo "Cutoff date: $cutoff_date\n";
    echo "---\n";
}

// Delete old snapshots
$del_snapshots = "DELETE FROM proctor_snapshots WHERE captured_at < ?";
$stmt = $conn->prepare($del_snapshots);
$stmt->bind_param("s", $cutoff_date);
$stmt->execute();
$snapshot_count = $conn->affected_rows;

if ($verbose) {
    echo "Deleted $snapshot_count old snapshots\n";
}

// Delete old activity logs
$del_activity = "DELETE FROM proctor_activity WHERE recorded_at < ?";
$stmt = $conn->prepare($del_activity);
$stmt->bind_param("s", $cutoff_date);
$stmt->execute();
$activity_count = $conn->affected_rows;

if ($verbose) {
    echo "Deleted $activity_count old activity records\n";
}

// Delete old proctoring sessions (that have no snapshots/activity left)
$del_sessions = "DELETE FROM proctor_sessions WHERE started_at < ? AND session_id NOT IN (SELECT DISTINCT session_id FROM proctor_snapshots UNION SELECT DISTINCT session_id FROM proctor_activity)";
$stmt = $conn->prepare($del_sessions);
$stmt->bind_param("s", $cutoff_date);
$stmt->execute();
$session_count = $conn->affected_rows;

if ($verbose) {
    echo "Deleted $session_count old proctor sessions\n";
    echo "---\n";
    echo "Cleanup completed successfully.\n";
}

exit(0);
