<?php
ob_start();
include('../../api/db/db_connection.php');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// --- Read JSON input ---
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// --- Validate input ---
if (!isset($data['sem_id']) || empty($data['sem_id'])) {
    echo json_encode(['error' => 'Missing sem_id']);
    exit;
}

$sem_id = (int)$data['sem_id'];

try {
    // Prepare query — no need for `$type`, we just fetch both types
    $query = "SELECT * FROM subject_info WHERE sem_info_id = ? AND (type = 'mandatory' OR type = 'elective')";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }

    $stmt->bind_param('i', $sem_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $subjects = [];
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'subjects' => $subjects
    ]);
} catch (Exception $e) {
    error_log("fetch_subjects error: " . $e->getMessage() . ", sem_id=$sem_id");
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

exit;
?>
