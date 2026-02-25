<?php
include('../../api/db/db_connection.php');

header('Content-Type: application/json');

$subject_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($subject_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid subject ID']);
    exit;
}

$query = "DELETE FROM subject_info WHERE id = $subject_id";

if (mysqli_query($conn, $query)) {
    echo json_encode(['status' => 'success', 'message' => 'Subject deleted successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete subject: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>