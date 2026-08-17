<?php
/**
 * admin/qr_force_stop.php
 * Allows the administrator to forcibly deactivate any active
 * attendance session (e.g. a teacher forgot to close it).
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
header('Content-Type: application/json');

try {
    $sessionId = (int) ($_POST['session_id'] ?? 0);
    if (!$sessionId) throw new Exception('Invalid session.');

    $stmt = $pdo->prepare('UPDATE attendance_sessions SET is_active = 0, deactivated_at = NOW() WHERE session_id = ? AND is_active = 1');
    $stmt->execute([$sessionId]);

    if ($stmt->rowCount() === 0) throw new Exception('Session already closed or not found.');

    log_activity($pdo, $_SESSION['user_id'], "Force-stopped attendance session #$sessionId");
    echo json_encode(['success' => true, 'message' => 'Session stopped successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
