<?php
// Start output buffering
ob_start();

include('../../api/db/db_connection.php');

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Validate input
if (!isset($_POST['subject_id']) || !is_numeric($_POST['subject_id']) || !isset($_POST['class_id']) || !is_numeric($_POST['class_id'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid or missing subject_id or class_id']);
    exit;
}

$subject_id = (int)$_POST['subject_id'];
$class_id = (int)$_POST['class_id'];

try {
    // Query to fetch faculty assigned to the subject and class
    // Use subject_allocation (or replace with faculty_subject_mapping if needed)
    $query = "
        SELECT f.id, f.first_name, f.last_name
        FROM faculty_info f
        JOIN subject_allocation sa ON f.id = sa.faculty_info_id
        WHERE sa.subject_info_id = ? AND sa.class_info_id = ?
    ";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    $stmt->bind_param('ii', $subject_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row;
    }

    // Log the query result
    error_log("fetch_faculty: subject_id=$subject_id, class_id=$class_id, found " . count($faculty) . " faculty, query: $query");

    // Clear output buffer and send JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($faculty);
} catch (Exception $e) {
    // Log the error
    error_log("fetch_faculty error: " . $e->getMessage() . ", subject_id=$subject_id, class_id=$class_id");
    // Clear output buffer and send error JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit;
?>