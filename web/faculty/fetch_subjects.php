<?php
ob_start();
include('../../api/db/db_connection.php');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
if (!isset($_POST['sem_id']) || !isset($_POST['type'])|| !isset($_POST['type'])) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing sem_id or type']);
    exit;
}
$sem_id = (int)$_POST['sem_id'];
$type = $_POST['type'];
$subId = isset($_POST['subId']) && $_POST['subId'] !== '' && $_POST['subId'] !== 'null' ? (int)$_POST['subId'] : null;
$subjects = [];
try {
    if ($subId) {
        $query = "SELECT * FROM subject_info WHERE id = ? AND sem_info_id = ? AND type = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        $stmt->bind_param('iis', $subId, $sem_id, $type);
    } else {
        $query = "SELECT * FROM subject_info WHERE sem_info_id = ? AND type = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        $stmt->bind_param('is', $sem_id, $type);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    error_log("fetch_subjects: sem_id=$sem_id, type=$type, subId=" . ($subId ?? 'null') . ", found " . count($subjects) . " subjects, query: $query");
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($subjects);
} catch (Exception $e) {
    error_log("fetch_subjects error: " . $e->getMessage() . ", sem_id=$sem_id, type=$type, subId=" . ($subId ?? 'null'));
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
exit;
?>