<?php
header('Content-Type: application/json');
include('../../api/db/db_connection.php');
session_start();

try {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    $faculty_id = $_SESSION['user']; // adjust if column name is different
    $title = $_POST['Announcement_title'];
    $type_id = $_POST['Announcement_type_id'];
    $batch_id = $_POST['batch_id'];
    $desc = $_POST['announcement_description'];
    $date = date('Y-m-d');

    $sql = "INSERT INTO announcements_info (faculty_id, Announcement_title, Announcement_date, Announcement_type_id, batch_id, announcement_description) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssis", $faculty_id, $title, $date, $type_id, $batch_id, $desc);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Announcement added successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database insert failed']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
