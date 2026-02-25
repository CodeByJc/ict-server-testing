<?php
include('../../api/db/db_connection.php');

if (isset($_GET['batch_id'])) {
    $batch_id = intval($_GET['batch_id']);

    $query = "SELECT psi.id AS placement_id, psi.*, CONCAT(si.first_name, ' ', si.last_name) AS student_name, 
                     ci.company_name, si.batch_info_id
              FROM placed_student_info psi 
              JOIN student_info si ON psi.student_info_id = si.id
              JOIN company_info ci ON psi.company_info_id = ci.id
              WHERE si.batch_info_id = ?
              ORDER BY student_name, placement_id";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $batch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $students = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }

    echo json_encode($students);
    exit;
}
?>
