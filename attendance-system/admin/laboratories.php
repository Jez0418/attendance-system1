<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$pageTitle = 'Laboratory Management';

$labs = $pdo->query('SELECT * FROM laboratories ORDER BY lab_name ASC')->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h3>Laboratories (<?php echo count($labs); ?>)</h3>
        <button class="btn btn-primary btn-sm" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add Laboratory</button>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Lab Name</th><th>Location</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($labs)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No laboratories found.</td></tr>
                <?php else: foreach ($labs as $l): ?>
                    <tr>
                        <td><?php echo e($l['lab_code']); ?></td>
                        <td><?php echo e($l['lab_name']); ?></td>
                        <td><?php echo e($l['location']); ?></td>
                        <td><?php echo e($l['capacity']); ?></td>
                        <td><span class="badge badge-<?php echo $l['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($l['status']); ?></span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick='openEditModal(<?php echo json_encode($l); ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteLab(<?php echo $l['lab_id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="labModal">
    <div class="modal">
        <div class="modal-header"><h3 id="labModalTitle">Add Laboratory</h3><button class="modal-close" onclick="closeModal('labModal')">&times;</button></div>
        <form id="labForm">
            <div class="modal-body">
                <input type="hidden" name="lab_id" id="lab_id">
                <div class="form-row">
                    <div class="form-group"><label>Lab Code *</label><input type="text" name="lab_code" id="lab_code" class="form-control" required></div>
                    <div class="form-group"><label>Lab Name *</label><input type="text" name="lab_name" id="lab_name" class="form-control" required></div>
                </div>
                <div class="form-group"><label>Location</label><input type="text" name="location" id="location" class="form-control"></div>
                <div class="form-row">
                    <div class="form-group"><label>Capacity</label><input type="number" name="capacity" id="capacity" class="form-control" value="40"></div>
                    <div class="form-group"><label>Status</label>
                        <select name="status" id="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('labModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="labSubmitBtn">Save Laboratory</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('labForm').reset();
    document.getElementById('lab_id').value = '';
    document.getElementById('labModalTitle').textContent = 'Add Laboratory';
    openModal('labModal');
}
function openEditModal(l) {
    document.getElementById('lab_id').value = l.lab_id;
    document.getElementById('lab_code').value = l.lab_code;
    document.getElementById('lab_name').value = l.lab_name;
    document.getElementById('location').value = l.location || '';
    document.getElementById('capacity').value = l.capacity;
    document.getElementById('status').value = l.status;
    document.getElementById('labModalTitle').textContent = 'Edit Laboratory';
    openModal('labModal');
}
document.getElementById('labForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('labSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Saving...';
    const data = Object.fromEntries(new FormData(e.target));
    data.action = data.lab_id ? 'update' : 'create';
    const res = await ajaxPost('ajax_laboratories.php', data);
    if (res.success) { showToast('success', res.message); closeModal('labModal'); setTimeout(() => location.reload(), 700); }
    else showToast('error', res.message);
    btn.disabled = false; btn.innerHTML = 'Save Laboratory';
});
async function deleteLab(id) {
    if (!confirmDelete('Delete this laboratory? Related class assignments will also be removed.')) return;
    const res = await ajaxPost('ajax_laboratories.php', { action: 'delete', lab_id: id });
    if (res.success) { showToast('success', res.message); setTimeout(() => location.reload(), 700); } else showToast('error', res.message);
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
