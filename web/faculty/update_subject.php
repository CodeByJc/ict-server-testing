<?php
include('../../api/db/db_connection.php');

header('Content-Type: application/json');
$subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
$sem_info_id = isset($_POST['sem_info_id']) ? intval($_POST['sem_info_id']) : 0;
$subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : '';
$short_name = isset($_POST['short_name']) ? trim($_POST['short_name']) : '';
$subject_code = isset($_POST['subject_code']) ? trim($_POST['subject_code']) : '';
$subject_type = isset($_POST['subject_type']) ? trim($_POST['subject_type']) : '';
$lec_type = isset($_POST['lec_type']) ? trim($_POST['lec_type']) : '';
$is_creditable = isset($_POST['is_creditable']) ? trim($_POST['is_creditable']) : '';

if ($subject_id <= 0 || $sem_info_id <= 0 || empty($subject_name) || empty($short_name) || empty($subject_code) || !in_array($subject_type, ['mandatory', 'elective']) || !in_array($lec_type, ['L', 'T', 'LT'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
    exit;
}

$subject_name = mysqli_real_escape_string($conn, $subject_name);
$short_name = mysqli_real_escape_string($conn, strtoupper($short_name));
$subject_code = mysqli_real_escape_string($conn, $subject_code);

$query = "UPDATE subject_info 
          SET sem_info_id = $sem_info_id, subject_name = '$subject_name', short_name = '$short_name', 
              subject_code = '$subject_code', type = '$subject_type', lec_type = '$lec_type'  , is_creditable = '$is_creditable'
          WHERE id = $subject_id";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update subject: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>