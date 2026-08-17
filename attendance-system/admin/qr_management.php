<?php
/**
 * admin/qr_management.php
 * Admin-level oversight of all QR attendance sessions: see which
 * sessions are currently active (live), preview their QR code, and
 * force-deactivate a session if needed (e.g. teacher forgot to close it).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../qr/qr_helper.php';
require_role('admin');
$pageTitle = 'QR Code Management';

$activeSessions = $pdo->query('
    SELECT s.*, t.full_name AS teacher_name, sub.subject_name, sub.subject_code, lab.lab_name
    FROM attendance_sessions s
    JOIN teacher_subjects ts ON ts.teacher_subject_id = s.teacher_subject_id
    JOIN teachers t ON t.teacher_id = ts.teacher_id
    JOIN subjects sub ON sub.subject_id = ts.subject_id
    JOIN laboratories lab ON lab.lab_id = ts.lab_id
    WHERE s.is_active = 1
    ORDER BY s.created_at DESC
')->fetchAll();

$recentSessions = $pdo->query('
    SELECT s.*, t.full_name AS teacher_name, sub.subject_name, lab.lab_name,
        (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.session_id) AS scans
    FROM attendance_sessions s
    JOIN teacher_subjects ts ON ts.teacher_subject_id = s.teacher_subject_id
    JOIN teachers t ON t.teacher_id = ts.teacher_id
    JOIN subjects sub ON sub.subject_id = ts.subject_id
    JOIN laboratories lab ON lab.lab_id = ts.lab_id
    WHERE s.is_active = 0
    ORDER BY s.created_at DESC LIMIT 10
')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><h3>Currently Active QR Sessions (<?php echo count($activeSessions); ?>)</h3></div>
    <div class="card-body">
        <?php if (empty($activeSessions)): ?>
            <div class="empty-state"><i class="fa-solid fa-qrcode"></i><p>No active QR sessions right now.</p></div>
        <?php else: ?>
        <div class="grid-3">
            <?php foreach ($activeSessions as $s): ?>
            <div class="card">
                <div class="card-body text-center">
                    <div id="qr-<?php echo $s['session_id']; ?>" style="display:inline-block;padding:10px;background:#fff;border-radius:8px;border:1px solid #e2e8f0"></div>
                    <h4 style="margin:14px 0 4px"><?php echo e($s['subject_code'] . ' - ' . $s['subject_name']); ?></h4>
                    <p class="text-muted" style="font-size:13px;margin:0"><?php echo e($s['teacher_name']); ?> · <?php echo e($s['lab_name']); ?></p>
                    <p class="text-muted" style="font-size:12px;margin:4px 0 14px">Started: <?php echo format_datetime($s['created_at']); ?></p>
                    <button class="btn btn-danger btn-sm" onclick="forceStop(<?php echo $s['session_id']; ?>)"><i class="fa-solid fa-stop"></i> Force Stop</button>
                </div>
            </div>
            <script>
                new QRCode(document.getElementById('qr-<?php echo $s['session_id']; ?>'), {
                    text: <?php echo json_encode(qr_build_payload($s['session_id'], $s['qr_token'])); ?>,
                    width: 160, height: 160
                });
            </script>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-header"><h3>Recently Closed Sessions</h3></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Subject</th><th>Teacher</th><th>Lab</th><th>Date</th><th>Scans</th><th>Closed At</th></tr></thead>
            <tbody>
            <?php if (empty($recentSessions)): ?>
                <tr><td colspan="6" class="text-center text-muted">No closed sessions yet.</td></tr>
            <?php else: foreach ($recentSessions as $s): ?>
                <tr>
                    <td><?php echo e($s['subject_name']); ?></td>
                    <td><?php echo e($s['teacher_name']); ?></td>
                    <td><?php echo e($s['lab_name']); ?></td>
                    <td><?php echo format_date($s['session_date']); ?></td>
                    <td><?php echo (int) $s['scans']; ?></td>
                    <td><?php echo format_datetime($s['deactivated_at']); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
async function forceStop(sessionId) {
    if (!confirm('Force-stop this QR session? Students will no longer be able to scan it.')) return;
    const res = await ajaxPost('qr_force_stop.php', { session_id: sessionId });
    if (res.success) { showToast('success', res.message); setTimeout(() => location.reload(), 700); }
    else showToast('error', res.message);
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
