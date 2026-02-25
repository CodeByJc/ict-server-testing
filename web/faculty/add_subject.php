<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once('../../api/db/db_connection.php');
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$sem_input     = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
$batch_input   = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
$subject_name  = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$short_name    = isset($_POST['short_name']) ? strtoupper(trim($_POST['short_name'])) : '';
$subject_code  = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$subject_type  = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : '';
$lec_type      = isset($_POST['lec_type']) ? strtoupper(trim($_POST['lec_type'])) : '';
$is_creditable = isset($_POST['is_creditable']) ? $_POST['is_creditable'] : '1';

$allowed_types = ['mandatory', 'elective', 'open-elective'];
$allowed_lec   = ['L', 'T', 'LT', 'TL'];

if ($sem_input <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing sem_info_id']);
    exit;
}
if ($batch_input <= 0) {
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
if (!in_array($lec_type, $allowed_lec, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid lecture type']);
    exit;
}
$is_creditable = ($is_creditable === '1' || $is_creditable === 1 || $is_creditable === true || $is_creditable === 'true') ? 1 : 0;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // verify sem exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM sem_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $sem_input);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    if (!$r || mysqli_num_rows($r) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Selected semester does not exist');
    }
    mysqli_stmt_close($stmt);

    // verify batch exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM batch_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $batch_input);
    mysqli_stmt_execute($stmt);
    $br = mysqli_stmt_get_result($stmt);
    if (!$br || mysqli_num_rows($br) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Selected batch does not exist');
    }
    $batchRow = mysqli_fetch_assoc($br);
    mysqli_stmt_close($stmt);
    if (isset($batchRow['sem_info_id']) && intval($batchRow['sem_info_id']) !== $sem_input) {
        throw new Exception('Selected batch does not belong to the selected semester');
    }

    // // discover sem & batch column names in subject_info (robust)
    // $semCol = null; $batchCol = null;
    // $colRes = mysqli_query($conn, "SHOW COLUMNS FROM subject_info");
    // if (!$colRes) throw new Exception('Unable to inspect subject_info columns: ' . mysqli_error($conn));
    // while ($col = mysqli_fetch_assoc($colRes)) {
    //     $f = strtolower($col['Field']);
    //     // find a candidate for sem column
    //     if ($semCol === null && (in_array($f, ['sem_info_id','sem_id','seM_info_id','sem'], true) || strpos($f, 'sem') !== false)) {
    //         $semCol = $col['Field'];
    //     }
    //     // find batch column
    //     if ($batchCol === null && strpos($f, 'batch') !== false) {
    //         $batchCol = $col['Field'];
    //     }
    // }
    // if (!$semCol) throw new Exception('Could not detect semester column in subject_info table (expected sem_info_id or similar).');
    // if (!$batchCol) throw new Exception('Could not detect batch column in subject_info table (expected batch_id or similar).');

    // // duplicate check: subject_code for same sem & batch
    // $dupSql = "SELECT id FROM subject_info WHERE {$semCol} = ? AND {$batchCol} = ? AND subject_code = ? LIMIT 1";
    // $stmt = mysqli_prepare($conn, $dupSql);
    // mysqli_stmt_bind_param($stmt, 'iis', $sem_input, $batch_input, $subject_code);
    // mysqli_stmt_execute($stmt);
    // $dup = mysqli_stmt_get_result($stmt);
    // if ($dup && mysqli_num_rows($dup) > 0) {
    //     mysqli_stmt_close($stmt);
    //     throw new Exception('A subject with this code already exists for the selected semester and batch');
    // }
    // mysqli_stmt_close($stmt);

    // perform insert using discovered column names
    $sql = "INSERT INTO subject_info (sem_info_id, batch_id, subject_name, short_name, subject_code, `type`, lec_type, is_creditable)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $ins = mysqli_prepare($conn, $sql);
    if (!$ins) throw new Exception('DB prepare failed: ' . mysqli_error($conn));
    mysqli_stmt_bind_param($ins, 'iisssssi',
        $sem_input,
        $batch_input,
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