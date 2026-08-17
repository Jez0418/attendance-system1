<?php
/**
 * student/ajax_scan.php
 * ============================================================
 * CORE ATTENDANCE LOGIC. Never trust the client — every check is
 * re-verified here against the database:
 *
 *   1. Payload signature must be valid (qr_helper.php HMAC check)
 *   2. The session must exist, be ACTIVE, and the token must match
 *   3. The student must be ENROLLED in that class
 *   4. The student must not have already scanned for this session
 *   5. Status is computed from scheduled_start + late_threshold_minutes
 * ============================================================
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../qr/qr_helper.php';
require_role('student');
header('Content-Type: application/json');

$studentId = $_SESSION['profile_id'];

try {
    $rawPayload = $_POST['qr_payload'] ?? '';
    if ($rawPayload === '') throw new Exception('No QR data received.');

    $parsed = qr_parse_payload($rawPayload);
    if (!$parsed) throw new Exception('Invalid or tampered QR code.');

    // Fetch the session and verify token + active status
    $stmt = $pdo->prepare('
        SELECT s.*, ts.teacher_subject_id, sub.subject_name, lab.lab_name
        FROM attendance_sessions s
        JOIN teacher_subjects ts ON ts.teacher_subject_id = s.teacher_subject_id
        JOIN subjects sub ON sub.subject_id = ts.subject_id
        JOIN laboratories lab ON lab.lab_id = ts.lab_id
        WHERE s.session_id = ?
    ');
    $stmt->execute([$parsed['session_id']]);
    $session = $stmt->fetch();

    if (!$session) throw new Exception('This attendance session does not exist.');
    if (!hash_equals($session['qr_token'], $parsed['token'])) throw new Exception('This QR code is no longer valid.');
    if ((int) $session['is_active'] !== 1) throw new Exception('This attendance session has been closed by the teacher.');

    // Enrollment check — student must be enrolled in this class
    $enrollCheck = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND teacher_subject_id = ? AND status = "enrolled"');
    $enrollCheck->execute([$studentId, $session['teacher_subject_id']]);
    if ($enrollCheck->fetchColumn() == 0) throw new Exception('You are not enrolled in this class. Attendance rejected.');

    // Duplicate scan check
    $dupCheck = $pdo->prepare('SELECT COUNT(*) FROM attendance_records WHERE session_id = ? AND student_id = ?');
    $dupCheck->execute([$session['session_id'], $studentId]);
    if ($dupCheck->fetchColumn() > 0) throw new Exception('You have already scanned attendance for this session.');

    // ---- Compute status: Present vs Late ----
    $now = new DateTime();
    $scheduledStart = new DateTime($session['scheduled_start']);
    $lateThreshold = (clone $scheduledStart)->modify('+' . (int) $session['late_threshold_minutes'] . ' minutes');
    $status = ($now > $lateThreshold) ? 'Late' : 'Present';

    $ins = $pdo->prepare('INSERT INTO attendance_records (session_id, student_id, time_in, status) VALUES (?, ?, NOW(), ?)');
    $ins->execute([$session['session_id'], $studentId, $status]);

    // Notify the student
    create_notification(
        $pdo,
        $_SESSION['user_id'],
        'Attendance Recorded',
        "You were marked $status for {$session['subject_name']} in {$session['lab_name']}."
    );

    log_activity($pdo, $_SESSION['user_id'], "Scanned attendance for session #{$session['session_id']} - $status");

    echo json_encode([
        'success' => true,
        'message' => "Marked as $status for {$session['subject_name']}.",
        'status' => $status,
        'subject_name' => $session['subject_name'],
        'time_in' => date('h:i:s A'),
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    // Unique constraint (uniq_scan) race-condition safety net
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'You have already scanned attendance for this session.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
