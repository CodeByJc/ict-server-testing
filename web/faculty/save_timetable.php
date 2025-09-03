<?php
include('../../api/db/db_connection.php');
$sem_id = $_POST['sem_id'];
$class_id = $_POST['class_id'];
$slots = json_decode($_POST['slots'], true);

$response = ['status' => 'success', 'message' => ''];

try {
    $conn->begin_transaction();

    // Insert new slots
    $insert_query = "INSERT INTO time_table (day, subject_info_id, faculty_info_id, class_info_id, class_location_info_id, sem_info_id, start_time, end_time, lec_type) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);

    foreach ($slots as $day => $day_slots) {
        foreach ($day_slots as $slot) {
            $insert_stmt->bind_param('siiiissss', 
                $day, 
                $slot['subject_id'], 
                $slot['faculty_id'], 
                $slot['class_id'], 
                $slot['location_id'], 
                $sem_id, 
                $slot['start_time'], 
                $slot['end_time'], 
                $slot['lec_type']
            );
            $insert_stmt->execute();
        }
    }

    $conn->commit();
    $response['message'] = 'Timetable saved successfully';
} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Error saving timetable: ' . $e->getMessage()];
}

echo json_encode($response);
?>