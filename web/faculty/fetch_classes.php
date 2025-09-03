<?php
ob_start();
include('../../api/db/db_connection.php');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
if (!isset($_POST['sem_id']) || !is_numeric($_POST['sem_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid or missing sem_id']);
    exit;
}
$sem_id = (int)$_POST['sem_id'];
try {
    $query = "SELECT id, classname, batch, `group`, elective_subject_id FROM class_info WHERE sem_info_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    $stmt->bind_param('i', $sem_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $row['elective_subject_id'] = $row['elective_subject_id'] !== null ? (string)$row['elective_subject_id'] : '';
        $classes[] = $row;
    }
    error_log("fetch_classes: sem_id=$sem_id, found " . count($classes) . " classes, query: $query");
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($classes);
} catch (Exception $e) {
    error_log("fetch_classes error: " . $e->getMessage() . ", sem_id=$sem_id");
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit;
?>