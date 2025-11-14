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

$subject_id   = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$sem_input    = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
$batch_input  = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
$subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$short_name   = isset($_POST['short_name']) ? strtoupper(trim($_POST['short_name'])) : '';
$subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$subject_type = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : '';
$lec_type     = isset($_POST['lec_type']) ? strtoupper(trim($_POST['lec_type'])) : '';
$is_creditable= isset($_POST['is_creditable']) ? $_POST['is_creditable'] : null;

$allowed_types = ['mandatory', 'elective', 'open-elective'];
$allowed_lec   = ['L', 'T', 'LT', 'TL'];

if ($subject_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid subject id']);
    exit;
}
if ($sem_input <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid sem_info_id']);
    exit;
}
if ($batch_input <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid batch_id']);
    exit;
}
if ($subject_name === '' || $short_name === '' || $subject_code === '') {
    echo json_encode(['status' => 'error', 'message' => 'Subject name, short name and code are required']);
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
if ($is_creditable === null) {
    $is_creditable = 1;
} else {
    $is_creditable = ($is_creditable === '1' || $is_creditable === 1 || $is_creditable === true || $is_creditable === 'true') ? 1 : 0;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // verify subject exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM subject_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $subject_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    if (!$r || mysqli_num_rows($r) === 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Subject does not exist');
    }
    mysqli_stmt_close($stmt);

    // verify sem exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM sem_info WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $sem_input);
    mysqli_stmt_execute($stmt);
    $sr = mysqli_stmt_get_result($stmt);
    if (!$sr || mysqli_num_rows($sr) === 0) {
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

    // discover sem & batch column names in subject_info
    // $semCol = null; $batchCol = null;
    // $colRes = mysqli_query($conn, "SHOW COLUMNS FROM subject_info");
    // if (!$colRes) throw new Exception('Unable to inspect subject_info columns: ' . mysqli_error($conn));
    // while ($col = mysqli_fetch_assoc($colRes)) {
    //     $f = strtolower($col['Field']);
    //     if ($semCol === null && (in_array($f, ['sem_info_id','sem_id','sem'], true) || strpos($f, 'sem') !== false)) {
    //         $semCol = $col['Field'];
    //     }
    //     if ($batchCol === null && strpos($f, 'batch') !== false) {
    //         $batchCol = $col['Field'];
    //     }
    // }
    // if (!$semCol) throw new Exception('Could not detect semester column in subject_info table (expected sem_info_id or similar).');
    // if (!$batchCol) throw new Exception('Could not detect batch column in subject_info table (expected batch_id or similar).');

    // duplicate check excluding current
    $dupSql = "SELECT id FROM subject_info WHERE sem_info_id = ? AND batch_id = ? AND subject_code = ? AND id <> ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $dupSql);
    mysqli_stmt_bind_param($stmt, 'iisi', $sem_input, $batch_input, $subject_code, $subject_id);
    mysqli_stmt_execute($stmt);
    $dup = mysqli_stmt_get_result($stmt);
    if ($dup && mysqli_num_rows($dup) > 0) {
        mysqli_stmt_close($stmt);
        throw new Exception('Another subject with this code already exists for the selected semester and batch');
    }
    mysqli_stmt_close($stmt);

    // perform update using discovered columns
    $sql = "UPDATE subject_info SET sem_info_id = ?, batch_id = ?, subject_name = ?, short_name = ?, subject_code = ?, `type` = ?, lec_type = ?, is_creditable = ? WHERE id = ?";
    $upd = mysqli_prepare($conn, $sql);
    if (!$upd) throw new Exception('DB prepare failed: ' . mysqli_error($conn));
    mysqli_stmt_bind_param($upd, 'iisssssii',
        $sem_input,
        $batch_input,
        $subject_name,
        $short_name,
        $subject_code,
        $subject_type,
        $lec_type,
        $is_creditable,
        $subject_id
    );
    mysqli_stmt_execute($upd);
    $affected = mysqli_stmt_affected_rows($upd);
    mysqli_stmt_close($upd);

    echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully', 'affected_rows' => $affected]);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conn) && $conn) mysqli_close($conn);
}
?>