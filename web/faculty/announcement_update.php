<?php
header('Content-Type: application/json');
include('../../api/db/db_connection.php');
session_start();

try {
    // Check login
    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    // Required POST fields
    $id = $_POST['Announcement_id'] ?? '';
    $title = $_POST['Announcement_title'] ?? '';
    $type_id = $_POST['Announcement_type_id'] ?? '';
    $batch_id = $_POST['batch_id'] ?? '';
    $desc = $_POST['announcement_description'] ?? '';

    if (empty($id) || empty($title) || empty($type_id) || empty($batch_id) || empty($desc)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        exit;
    }

    // Update record
    $sql = "UPDATE announcements 
            SET Announcement_title=?, Announcement_type_id=?, batch_id=?, announcement_description=? 
            WHERE Announcement_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisi", $title, $type_id, $batch_id, $desc, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Announcement updated successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
