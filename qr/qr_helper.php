<?php
/**
 * ------------------------------------------------------------
 * qr/qr_helper.php
 * Builds and parses the JSON payload that gets encoded into the
 * QR code image (rendered client-side by qrcode.min.js) and that
 * the student scanner (html5-qrcode) reads back.
 *
 * Payload format (JSON string):
 *   { "session_id": 12, "token": "a1b2c3...", "sig": "..." }
 *
 * "sig" is an HMAC of session_id+token using a server secret so a
 * forged/guessed QR payload cannot be accepted (defence in depth
 * on top of the random 32-byte qr_token itself).
 * ------------------------------------------------------------
 */

// Secret key used only for signing QR payloads. Change this in production.
define('QR_SECRET_KEY', 'CHANGE_THIS_SECRET_QR_SIGNING_KEY_2026');

function qr_build_payload($sessionId, $token) {
    $sig = hash_hmac('sha256', $sessionId . '|' . $token, QR_SECRET_KEY);
    return json_encode([
        'session_id' => (int) $sessionId,
        'token'      => $token,
        'sig'        => $sig,
    ]);
}

/**
 * Validate a scanned QR payload string.
 * Returns ['session_id' => int, 'token' => string] on success, or false on failure.
 */
function qr_parse_payload($raw) {
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['session_id']) || empty($data['token']) || empty($data['sig'])) {
        return false;
    }
    $expectedSig = hash_hmac('sha256', $data['session_id'] . '|' . $data['token'], QR_SECRET_KEY);
    if (!hash_equals($expectedSig, $data['sig'])) {
        return false;
    }
    return ['session_id' => (int) $data['session_id'], 'token' => $data['token']];
}
