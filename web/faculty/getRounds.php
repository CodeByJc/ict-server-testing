<?php
include('../../api/db/db_connection.php');

// Get the campus drive ID
$drive_id = isset($_GET['drive_id']) ? $_GET['drive_id'] : null;

if ($drive_id) {
    $query = "SELECT id, round_name, round_index FROM company_rounds_info WHERE campus_placement_info_id = $drive_id";
    $result = mysqli_query($conn, $query);

    $rounds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rounds[] = $row;
    }

    echo json_encode($rounds);
}
?>
