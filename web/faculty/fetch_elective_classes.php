<?php
include('../../api/db/db_connection.php');
$sem_id = $_POST['sem_id'];
$query = "SELECT id, classname, batch FROM class_info WHERE sem_info_id = ? AND `group` = 'elective'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $sem_id);
$stmt->execute();
$result = $stmt->get_result();
$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
echo json_encode($classes);
?>