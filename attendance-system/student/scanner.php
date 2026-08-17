<?php
/**
 * student/scanner.php
 * Uses the html5-qrcode JS library to read a QR code via the device
 * camera. The raw scanned text is posted to ajax_scan.php, which
 * performs ALL the real validation server-side (never trust the client):
 *   - signature check, session active, enrollment check, duplicate check
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('student');
$pageTitle = 'QR Code Scanner';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="scanner-wrapper">
    <div class="card">
        <div class="card-header"><h3>Scan Attendance QR Code</h3></div>
        <div class="card-body">
            <p class="text-muted" style="margin-top:0">Point your camera at the QR code shown by your teacher. Attendance is recorded automatically the moment it's detected.</p>
            <div id="qrReader" style="width:100%"></div>
            <div id="scanResult"></div>
            <div class="text-center" style="margin-top:16px">
                <button class="btn btn-outline btn-sm" id="restartBtn" style="display:none" onclick="restartScanner()"><i class="fa-solid fa-rotate"></i> Scan Another</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode;
let scanning = false;

function startScanner() {
    html5QrCode = new Html5Qrcode('qrReader');
    scanning = true;
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: 240 },
        onScanSuccess,
        () => {} // ignore per-frame "not found" errors
    ).catch(err => {
        document.getElementById('qrReader').innerHTML =
            '<div class="alert alert-error">Could not access camera: ' + err + '. Please allow camera permission and reload.</div>';
    });
}

async function onScanSuccess(decodedText) {
    if (!scanning) return;
    scanning = false; // prevent multiple rapid-fire scans of the same code
    await html5QrCode.stop();

    const resultDiv = document.getElementById('scanResult');
    resultDiv.innerHTML = '<div class="alert alert-info"><span class="spinner spinner-dark"></span> Verifying scan...</div>';

    try {
        const res = await ajaxPost('ajax_scan.php', { qr_payload: decodedText });
        if (res.success) {
            resultDiv.innerHTML = `
                <div class="scan-result alert-success" style="background:#dcfce7">
                    <i class="fa-solid fa-circle-check" style="font-size:28px;color:#16a34a"></i>
                    <h3 style="margin:10px 0 4px">Attendance Recorded!</h3>
                    <p style="margin:0">${res.subject_name} — <span class="badge badge-${res.status.toLowerCase()}">${res.status}</span></p>
                    <p class="text-muted" style="margin:6px 0 0">${res.time_in}</p>
                </div>`;
            showToast('success', res.message);
        } else {
            resultDiv.innerHTML = `<div class="scan-result alert-error" style="background:#fee2e2">
                <i class="fa-solid fa-circle-exclamation" style="font-size:28px;color:#dc2626"></i>
                <h3 style="margin:10px 0 4px">Scan Rejected</h3><p style="margin:0">${res.message}</p></div>`;
            showToast('error', res.message);
        }
    } catch (err) {
        resultDiv.innerHTML = '<div class="alert alert-error">Something went wrong verifying your scan. Please try again.</div>';
    }
    document.getElementById('restartBtn').style.display = 'inline-flex';
}

function restartScanner() {
    document.getElementById('scanResult').innerHTML = '';
    document.getElementById('restartBtn').style.display = 'none';
    startScanner();
}

startScanner();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
