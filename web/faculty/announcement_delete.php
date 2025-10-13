<?php
ob_start();
session_start();
include('../../api/db/db_connection.php');

// ✅ Check login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// ✅ Get announcement ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit();
}

$announcement_id = intval($_POST['id']);

// ✅ Delete query
$stmt = $conn->prepare("DELETE FROM announcements WHERE Announcement_id = ?");
$stmt->bind_param("i", $announcement_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Announcement deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete announcement"]);
}

$stmt->close();
$conn->close();
ob_end_flush();
?>
