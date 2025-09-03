<?php
require_once __DIR__ . '/../Services/LeaveService.php';

function LeaveRequestController($input, $files) {
    // Log incoming data for debugging
    error_log("Input: " . print_r($input, true));
    error_log("Files: " . print_r($files, true));

    // Check if required fields are present (input might be in $_POST due to multipart/form-data)
    $studentId = isset($input['student_info_id']) ? $input['student_info_id'] : (isset($_POST['student_info_id']) ? $_POST['student_info_id'] : null);
    $reason = isset($input['reason']) ? $input['reason'] : (isset($_POST['reason']) ? $_POST['reason'] : null);

    if (!$studentId || !$reason) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Student ID and reason are required']);
        return;
    }

    $studentId = intval($studentId);
    $reason = trim($reason);
    $file = isset($files['document_proof']) ? $files['document_proof'] : null;

    $response = leaveRequestService($studentId, $reason, $file);
    echo json_encode($response);
}

function GetLeaveHistoryController() {
    $studentId = isset($_GET['student_info_id']) ? intval($_GET['student_info_id']) : null;

    if (!$studentId) {
        http_response_code(400);
        echo json_encode(['message' => 'Student ID is required']);
        return;
    }

    $response = getLeaveHistoryService($studentId);
    echo json_encode($response);
}
?>