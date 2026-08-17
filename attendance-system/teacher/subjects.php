<?php
/**
 * teacher/subjects.php
 * Read-only view of subjects/classes assigned to the logged-in teacher.
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
$pageTitle = 'Assigned Subjects';

$teacherId = $_SESSION['profile_id'];

$stmt = $pdo->prepare('
    SELECT ts.*, sub.subject_name, sub.subject_code, sub.units, lab.lab_name, lab.location,
        (SELECT COUNT(*) FROM enrollments e WHERE e.teacher_subject_id = ts.teacher_subject_id AND e.status="enrolled") AS enrolled_count
    FROM teacher_subjects ts
    JOIN subjects sub ON sub.subject_id = ts.subject_id
    JOIN laboratories lab ON lab.lab_id = ts.lab_id
    WHERE ts.teacher_id = ?
    ORDER BY ts.schedule_day, ts.start_time
');
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="grid-3">
<?php if (empty($classes)): ?>
    <div class="empty-state" style="grid-column:1/-1"><i class="fa-solid fa-book"></i><p>You have no assigned subjects yet. Please contact the administrator.</p></div>
<?php else: foreach ($classes as $c): ?>
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 4px"><?php echo e($c['subject_code']); ?></h3>
            <p style="margin:0 0 12px;color:var(--slate-600)"><?php echo e($c['subject_name']); ?></p>
            <div style="font-size:13.5px;color:var(--slate-700);line-height:1.9">
                <div><i class="fa-solid fa-flask" style="width:18px;color:var(--indigo-600)"></i> <?php echo e($c['lab_name']); ?></div>
                <div><i class="fa-solid fa-location-dot" style="width:18px;color:var(--indigo-600)"></i> <?php echo e($c['location']); ?></div>
                <div><i class="fa-solid fa-calendar-days" style="width:18px;color:var(--indigo-600)"></i> <?php echo e($c['schedule_day']); ?></div>
                <div><i class="fa-solid fa-clock" style="width:18px;color:var(--indigo-600)"></i> <?php echo format_time($c['start_time']); ?> – <?php echo format_time($c['end_time']); ?></div>
                <div><i class="fa-solid fa-users" style="width:18px;color:var(--indigo-600)"></i> <?php echo e($c['section']); ?> (<?php echo (int) $c['enrolled_count']; ?> enrolled)</div>
            </div>
            <span class="badge badge-<?php echo $c['status'] === 'active' ? 'active' : 'inactive'; ?>" style="margin-top:12px;display:inline-block"><?php echo ucfirst($c['status']); ?></span>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
