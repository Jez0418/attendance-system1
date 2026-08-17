<?php
/**
 * admin/ajax_assignments.php — create / update / delete teacher_subjects (classes).
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

function validate_assignment_input($pdo) {
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $labId     = (int) ($_POST['lab_id'] ?? 0);
    $section   = clean($_POST['section'] ?? '');
    $day       = clean($_POST['schedule_day'] ?? '');
    $start     = clean($_POST['start_time'] ?? '');
    $end       = clean($_POST['end_time'] ?? '');
    $status    = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    if (!$teacherId || !$subjectId || !$labId || $section === '' || $day === '' || $start === '' || $end === '') {
        throw new Exception('Please fill in all required fields.');
    }
    if (strtotime($end) <= strtotime($start)) {
        throw new Exception('End time must be after start time.');
    }
    return [$teacherId, $subjectId, $labId, $section, $day, $start, $end, $status];
}

try {
    if ($action === 'create') {
        [$teacherId, $subjectId, $labId, $section, $day, $start, $end, $status] = validate_assignment_input($pdo);
        $stmt = $pdo->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id, lab_id, section, schedule_day, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$teacherId, $subjectId, $labId, $section, $day, $start, $end, $status]);
        log_activity($pdo, $_SESSION['user_id'], "Created class assignment (section $section)");
        echo json_encode(['success' => true, 'message' => 'Class assignment created successfully.']);

    } elseif ($action === 'update') {
        $id = (int) ($_POST['teacher_subject_id'] ?? 0);
        if (!$id) throw new Exception('Invalid assignment.');
        [$teacherId, $subjectId, $labId, $section, $day, $start, $end, $status] = validate_assignment_input($pdo);
        $stmt = $pdo->prepare('UPDATE teacher_subjects SET teacher_id=?, subject_id=?, lab_id=?, section=?, schedule_day=?, start_time=?, end_time=?, status=? WHERE teacher_subject_id=?');
        $stmt->execute([$teacherId, $subjectId, $labId, $section, $day, $start, $end, $status, $id]);
        log_activity($pdo, $_SESSION['user_id'], "Updated class assignment ID $id");
        echo json_encode(['success' => true, 'message' => 'Class assignment updated successfully.']);

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['teacher_subject_id'] ?? 0);
        if (!$id) throw new Exception('Invalid assignment.');
        $stmt = $pdo->prepare('DELETE FROM teacher_subjects WHERE teacher_subject_id = ?');
        $stmt->execute([$id]);
        log_activity($pdo, $_SESSION['user_id'], "Deleted class assignment ID $id");
        echo json_encode(['success' => true, 'message' => 'Class assignment deleted successfully.']);
    } else {
        throw new Exception('Unknown action.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
