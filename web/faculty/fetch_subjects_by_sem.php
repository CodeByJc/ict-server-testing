<?php
include('../../api/db/db_connection.php');

header('Content-Type: application/json');

$sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;

if ($sem_id <= 0) {
    echo json_encode(['error' => 'Invalid semester ID']);
    exit;
}

$query = "SELECT id, subject_name, short_name, subject_code, type, lec_type 
          FROM subject_info 
          WHERE sem_info_id = $sem_id 
          ORDER BY type";
$result = mysqli_query($conn, $query);

$subjects = [];
while ($row = mysqli_fetch_assoc($result)) {
    $subjects[] = $row;
}

echo json_encode($subjects);
mysqli_close($conn);
?>