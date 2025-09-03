<?php
include('../../api/db/db_connection.php');

// Get the data from the AJAX request
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['student_id'], $data['drive_id'], $data['round_id'], $data['status'])) {
    $student_id = $data['student_id'];
    $drive_id = $data['drive_id'];
    $round_id = $data['round_id'];
    $status = $data['status'];

    // Update the status in the database
    $query = "UPDATE student_round_info 
              SET status = '$status' 
              WHERE student_info_id = $student_id 
              AND campus_placement_info_id = $drive_id 
              AND company_round_info_id = $round_id";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>
