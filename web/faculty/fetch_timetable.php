<?php
// Start output buffering to capture any unexpected output
ob_start();

include('../../api/db/db_connection.php');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Validate input
if (!isset($_POST['sem_id']) || !is_numeric($_POST['sem_id']) || !isset($_POST['class_id']) || !is_numeric($_POST['class_id'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or missing sem_id or class_id']);
    exit;
}

$sem_id = (int)$_POST['sem_id'];
$class_id = (int)$_POST['class_id'];

try {
    $query = "
        SELECT 
            t.id, t.day, t.start_time, t.end_time, 
            t.class_info_id, t.subject_info_id, t.faculty_info_id, t.class_location_info_id, t.lec_type,
            c.classname, c.batch,
            s.short_name AS subject_name,
            CONCAT(f.first_name, ' ', f.last_name) AS faculty_name
        FROM time_table t
        JOIN class_info c ON t.class_info_id = c.id
        JOIN subject_info s ON t.subject_info_id = s.id
        JOIN faculty_info f ON t.faculty_info_id = f.id
        WHERE t.sem_info_id = ? AND t.class_info_id = ?
        ORDER BY t.day, t.start_time
    ";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    $stmt->bind_param('ii', $sem_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $timetable = [];
    while ($row = $result->fetch_assoc()) {
        $timetable[] = $row;
    }

    // Log the query result for debugging
    error_log("fetch_timetable: sem_id=$sem_id, class_id=$class_id, found " . count($timetable) . " slots, query: $query");

    // Clear output buffer and send JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($timetable);
} catch (Exception $e) {
    // Log the error
    error_log("fetch_timetable error: " . $e->getMessage() . ", sem_id=$sem_id, class_id=$class_id");
    // Clear output buffer and send error JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit; // Ensure no additional output
?>