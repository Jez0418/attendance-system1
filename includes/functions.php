<?php
/**
 * ------------------------------------------------------------
 * functions.php
 * Reusable helper functions: sanitization, redirects, flash
 * messages, notifications, QR token generation, formatting.
 * ------------------------------------------------------------
 */

/** Escape output to prevent XSS */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Trim + sanitize a raw input string */
function clean($value) {
    return trim(strip_tags($value ?? ''));
}

/** Redirect helper */
function redirect($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/** Store a one-time flash message in session */
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Retrieve + clear the flash message (used by header.php to show a toast) */
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Generate a cryptographically random token for QR codes / sessions */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/** Insert a notification for a given user_id */
function create_notification(PDO $pdo, $userId, $title, $message) {
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $title, $message]);
}

/** Count unread notifications for the currently logged-in user */
function unread_notification_count(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Log an action to activity_logs (simple audit trail) */
function log_activity(PDO $pdo, $userId, $action) {
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action) VALUES (?, ?)');
    $stmt->execute([$userId, $action]);
}

/** Format a MySQL datetime nicely for display */
function format_datetime($datetime) {
    if (!$datetime) return '—';
    return date('M d, Y h:i A', strtotime($datetime));
}

function format_date($date) {
    if (!$date) return '—';
    return date('M d, Y', strtotime($date));
}

function format_time($time) {
    if (!$time) return '—';
    return date('h:i A', strtotime($time));
}

/** Simple pagination helper: returns [offset, limit, page] */
function paginate($totalRows, $perPage = 10) {
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return ['offset' => $offset, 'limit' => $perPage, 'page' => $page, 'totalPages' => $totalPages];
}

/** Render pagination links (keeps existing query string filters) */
function render_pagination($page, $totalPages) {
    if ($totalPages <= 1) return;
    $params = $_GET;
    echo '<nav><ul class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $params['page'] = $i;
        $qs = http_build_query($params);
        $active = $i === $page ? 'active' : '';
        echo "<li class=\"page-item $active\"><a class=\"page-link\" href=\"?$qs\">$i</a></li>";
    }
    echo '</ul></nav>';
}
