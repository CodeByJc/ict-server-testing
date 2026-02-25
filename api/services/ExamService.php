<?php

require_once __DIR__ . '/../db/db_connection.php';

function GetExamListService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetExamList(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
        $exam_data = [];
        while ($row = $result->fetch_assoc()) {
            $exam_data[] = $row;
        }
        $stmt->close();
        return ['status' => true, 'data' => $exam_data];
    $stmt->close();
    http_response_code(401); // Unauthorized
    return ['status' => false, 'message' => 'Invalid Student Id'];
}
?>