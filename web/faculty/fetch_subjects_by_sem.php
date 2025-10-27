<?php
include('../../api/db/db_connection.php');

header('Content-Type: application/json');

$sem_id = isset($_POST['sem_id']) ? intval($_POST['sem_id']) : 0;

if ($sem_id <= 0) {
    echo json_encode(['error' => 'Invalid semester ID']);
    exit;
}

// Alias is_creditable as iscredit and cast to integer to ensure JS receives 0/1
$query = "SELECT id,
                 subject_name,
                 short_name,
                 subject_code,
                 type,
                 lec_type,
                 CAST(is_creditable AS UNSIGNED) AS iscredit,
                 sem_info_id
          FROM subject_info 
          WHERE sem_info_id = $sem_id 
          ORDER BY type";

$result = mysqli_query($conn, $query);

$subjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Optional: ensure iscredit is integer 0/1 (mysqli returns strings sometimes)
        $row['iscredit'] = isset($row['iscredit']) ? (int)$row['iscredit'] : 0;
        $subjects[] = $row;
    }
}

echo json_encode($subjects);
mysqli_close($conn);
?>