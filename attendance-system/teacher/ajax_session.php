<?php
/**
 * teacher/ajax_session.php
 * Activate or deactivate the QR attendance session for one of the
 * teacher's own classes.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher');
header('Content-Type: application/json');

$teacherId = $_SESSION['profile_id'];
$action = $_POST['action'] ?? '';
$classId = (int) ($_POST['teacher_subject_id'] ?? 0);

try {
    if (!$classId) throw new Exception('Invalid class.');

    $classStmt = $pdo->prepare('SELECT * FROM teacher_subjects WHERE teacher_subject_id = ? AND teacher_id = ?');
    $classStmt->execute([$classId, $teacherId]);
    $class = $classStmt->fetch();
    if (!$class) throw new Exception('You do not have access to this class.');

    if ($action === 'activate') {
        // Prevent duplicate active sessions for the same class on the same day
        $existing = $pdo->prepare('SELECT session_id FROM attendance_sessions WHERE teacher_subject_id = ? AND session_date = CURDATE() AND is_active = 1');
        $existing->execute([$classId]);
        if ($existing->fetch()) throw new Exception('A session is already active for this class today.');

        $token = generate_token(32);
        $scheduledStart = date('Y-m-d') . ' ' . $class['start_time'];

        $ins = $pdo->prepare('
            INSERT INTO attendance_sessions (teacher_subject_id, session_date, qr_token, scheduled_start, late_threshold_minutes, is_active, activated_by)
            VALUES (?, CURDATE(), ?, ?, ?, 1, ?)
        ');
        $ins->execute([$classId, $token, $scheduledStart, LATE_THRESHOLD_MINUTES, $teacherId]);

        // Notify enrolled students
        $students = $pdo->prepare('
            SELECT s.user_id FROM enrollments e JOIN students s ON s.student_id = e.student_id
            WHERE e.teacher_subject_id = ? AND e.status = "enrolled"
        ');
        $students->execute([$classId]);
        foreach ($students->fetchAll() as $s) {
            create_notification($pdo, $s['user_id'], 'Attendance Session Started', 'Your teacher just activated the QR attendance session. Scan now to be marked present!');
        }

        log_activity($pdo, $_SESSION['user_id'], "Activated attendance session for class #$classId");
        echo json_encode(['success' => true, 'message' => 'Attendance session activated.']);

    } elseif ($action === 'deactivate') {
        $upd = $pdo->prepare('UPDATE attendance_sessions SET is_active = 0, deactivated_at = NOW() WHERE teacher_subject_id = ? AND session_date = CURDATE() AND is_active = 1');
        $upd->execute([$classId]);
        if ($upd->rowCount() === 0) throw new Exception('No active session found for this class.');

        log_activity($pdo, $_SESSION['user_id'], "Deactivated attendance session for class #$classId");
        echo json_encode(['success' => true, 'message' => 'Attendance session deactivated.']);

    } else {
        throw new Exception('Unknown action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
