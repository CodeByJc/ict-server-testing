<?php
include('../../api/db/db_connection.php');
header('Content-Type: application/json;');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // helpful during dev

$response = ['status' => 'error', 'message' => 'Unknown error'];

try {
    // --- Read and decode raw JSON ---
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        throw new Exception('Invalid JSON body');
    }

    // --- Validate input ---
    $sem_id   = isset($input['sem_id']) ? intval($input['sem_id']) : 0;
    $class_id = isset($input['class_id']) ? intval($input['class_id']) : 0;
    $slots    = $input['slots'] ?? [];

    if ($sem_id <= 0 || $class_id <= 0 || empty($slots)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing sem_id, class_id, or slots']);
        exit;
    }

    $conn->begin_transaction();

    // --- Prepare insert query once ---
    $insert_query = "
        INSERT INTO time_table 
            (day, subject_info_id, faculty_info_id, class_info_id, class_location_info_id, sem_info_id, start_time, end_time, lec_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);

    foreach ($slots as $day => $day_slots) {
        foreach ($day_slots as $slot) {
            $day          = trim($day);
            $subject_id   = intval($slot['subject_id'] ?? 0);
            $faculty_id   = intval($slot['faculty_id'] ?? 0);
            $slot_class   = intval($slot['class_id'] ?? $class_id);
            $location_id  = intval($slot['location_id'] ?? 0);
            $start_time   = trim($slot['start_time'] ?? '');
            $end_time     = trim($slot['end_time'] ?? '');
            $lec_type     = trim($slot['lec_type'] ?? '');

            if (!$day || !$subject_id || !$faculty_id || !$start_time || !$end_time) {
                throw new Exception("Missing required fields in slot for day $day");
            }

            $stmt->bind_param(
                'siiiissss',
                $day,
                $subject_id,
                $faculty_id,
                $slot_class,
                $location_id,
                $sem_id,
                $start_time,
                $end_time,
                $lec_type
            );
            $stmt->execute();
        }
    }

    $conn->commit();
    $response = ['status' => 'success', 'message' => 'Timetable saved successfully'];

} catch (Exception $e) {
    if ($conn && $conn->errno === 0) {
        $conn->rollback();
    }
    $response = ['status' => 'error', 'message' => 'Error saving timetable: ' . $e->getMessage()];
}

echo json_encode($response);
exit;
?>
