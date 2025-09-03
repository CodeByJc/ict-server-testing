<?php
// Include the database connection file
include('../../api/db/db_connection.php');

$student_data = [];
$attendance_data = [];

if (isset($_GET['enrollment_no'])) {
    $enrollment_no = $_GET['enrollment_no'];

    // Get student info
    $sql = "CALL GetStudentInfo(?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $enrollment_no);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $student_data[] = $row;
    }

    $stmt->close();

    // If student data exists, get attendance info
    if (count($student_data) > 0) {
        $student_id = $student_data[0]['student_id'];

        $sql = "CALL TotalAttendance(?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $attendance_data[] = $row;
        }

        $stmt->close();
    }
}

// Return both student info and attendance data
echo json_encode([
    'student_info' => $student_data,
    'attendance_info' => $attendance_data
]);
?>
