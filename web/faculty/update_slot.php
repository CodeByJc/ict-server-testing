<?php
include('../../api/db/db_connection.php');
$id = $_POST['id'];
$day = $_POST['day'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$subject_id = $_POST['subject_id'];
$faculty_id = $_POST['faculty_id'];
$class_id = $_POST['class_id'];
$location_id = $_POST['location_id'];
$sem_id = $_POST['sem_id'];
$lec_type = $_POST['lec_type'];

$response = ['status' => 'success', 'message' => ''];

try {
    $conn->begin_transaction();

    $query = "UPDATE time_table 
              SET day = ?, start_time = ?, end_time = ?, subject_info_id = ?, faculty_info_id = ?, 
                  class_info_id = ?, class_location_info_id = ?, sem_info_id = ?, lec_type = ? 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssiiiisii', $day, $start_time, $end_time, $subject_id, $faculty_id, $class_id, $location_id, $sem_id, $lec_type, $id);
    $stmt->execute();

    $conn->commit();
    $response['message'] = 'Slot updated successfully';
} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Error updating slot: ' . $e->getMessage()];
}

echo json_encode($response);
?>