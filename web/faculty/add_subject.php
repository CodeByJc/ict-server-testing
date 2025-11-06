<?php
// add_subject.php
// Adds a subject. Expects POST:
// - sem_info_id (int, required)
// - batch_id (int, required)
// - subject_name (string, required)
// - short_name (string, required) — will be uppercased
// - subject_code (string, required)
// - subject_type (string, required) e.g. 'mandatory', 'elective', 'open-elective'
// - lec_type (string, required) e.g. 'L', 'T', 'LT' (accepts 'TL' too)
// - is_creditable (optional) '1' or '0' (defaults to 1)
//
// Returns JSON { status: 'success', id: <new_id>, message: '...' } or { status: 'error', message: '...' }

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once('../../api/db/db_connection.php'); // provides $conn (mysqli)
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Read and sanitize inputs
$sem_info_id   = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
$batch_id      = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
$subject_name  = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$short_name    = isset($_POST['short_name']) ? strtoupper(trim($_POST['short_name'])) : '';
$subject_code  = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$subject_type  = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : '';
$lec_type      = isset($_POST['lec_type']) ? trim($_POST['lec_type']) : '';
$is_creditable = isset($_POST['is_creditable']) ? $_POST['is_creditable'] : '1'; // default creditable

// Basic validation
$allowed_types = ['mandatory', 'elective', 'open-elective'];
$allowed_lec   = ['L', 'T', 'LT', 'TL'];

if ($sem_info_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing sem_info_id']);
    exit;
}
if ($batch_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a valid batch (batch_id)']);
    exit;
}
if ($subject_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Subject name is required']);
    exit;
}
if ($short_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Short name is required']);
    exit;
}
if ($subject_code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Subject code is required']);
    exit;
}
if (!in_array($subject_type, $allowed_types, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid subject type']);
    exit;
}
$lec_type = strtoupper($lec_type);
if (!in_array($lec_type, $allowed_lec, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid lecture type']);
    exit;
}
$is_creditable = ($is_creditable === '1' || $is_creditable === 1 || $is_creditable === true) ? 1 : 0;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1) Verify sem_info exists (optional but recommended)
    $stmt = mysqli_prepare($conn, "SELECT id FROM sem_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $sem_info_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Selected semester does not exist');
    }
    mysqli_stmt_close($stmt);

    // 2) Verify batch exists and (if possible) belongs to sem_info
    // If your batch table uses sem_info_id column name different than sem_info_id, adjust accordingly.
    $stmt = mysqli_prepare($conn, "SELECT id, batch_start_year, batch_end_year FROM batch_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $batch_id);
    mysqli_stmt_execute($stmt);
    $batchRes = mysqli_stmt_get_result($stmt);
    if (!$batchRes || mysqli_num_rows($batchRes) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Selected batch does not exist');
    }
    $batchRow = mysqli_fetch_assoc($batchRes);
    mysqli_stmt_close($stmt);

    // If your batch_info table has sem_info_id column and you want to ensure batch belongs to sem:
    // Uncomment and adjust following block if batch_info.sem_info_id exists
    $stmt = mysqli_prepare($conn, "SELECT sem_info_id FROM batch_info WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $batch_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if ($r && ($br = mysqli_fetch_assoc($r))) {
            if (isset($br['sem_info_id']) && intval($br['sem_info_id']) !== $sem_info_id) {
                mysqli_stmt_close($stmt);
                throw new Exception('Selected batch does not belong to the selected semester');
            }
        }
        mysqli_stmt_close($stmt);
    }

    // 3) Optional: check duplicate subject_code for same sem_info and batch
    $stmt = mysqli_prepare($conn, "SELECT id FROM subject_info WHERE subject_code = ? AND sem_info_id = ? AND batch_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'sii', $subject_code, $sem_info_id, $batch_id);
    mysqli_stmt_execute($stmt);
    $dupRes = mysqli_stmt_get_result($stmt);
    if ($dupRes && mysqli_num_rows($dupRes) > 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('A subject with this code already exists for the selected semester and batch');
    }
    mysqli_stmt_close($stmt);

    // 4) Insert subject (prepared statement)
    $sql = "INSERT INTO subject_info (sem_info_id, batch_id, subject_name, short_name, subject_code, `type`, lec_type, is_creditable)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $ins = mysqli_prepare($conn, $sql);
    if (!$ins) throw new Exception('DB prepare failed: ' . mysqli_error($conn));
    mysqli_stmt_bind_param($ins, 'iisssssi',
        $sem_info_id,
        $batch_id,
        $subject_name,
        $short_name,
        $subject_code,
        $subject_type,
        $lec_type,
        $is_creditable
    );
    mysqli_stmt_execute($ins);
    $newId = mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    echo json_encode(['status' => 'success', 'id' => (int)$newId, 'message' => 'Subject added successfully']);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conn) && $conn) mysqli_close($conn);
}
?>