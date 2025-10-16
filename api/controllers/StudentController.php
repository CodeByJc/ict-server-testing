<?php
use function JmesPath\search;

require_once __DIR__ . '/../services/StudentService.php';

/**
 * Student Login Controller
 */
function StudentLoginController($input) {

    error_log("Received Login Request: " . json_encode($input));

    if (!isset($input['username']) || !isset($input['password']) || !isset($input['device_token'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username, password and device_token are required']);
        return;
    }

    $username = $input['username'];
    $password = $input['password'];
    $device_token = $input['device_token'];

    $response = StudentLoginService($username, $password, $device_token);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(401);
        echo json_encode(['message' => $response['message']]);
    }

    error_log("Login Response: " . json_encode($response));
}


/**
 * Get Batch List By Branch
 */
function GetBatchesByBranchController() {

    // Call service function
    $response = GetBatchesByBranchService();

    // Send response
    if ($response['status']) {
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}


/**
 * Student Logout Controller
 */
function StudentLogoutController($input) {
    if (!isset($input['username'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username required']);
        return;
    }

    $username = $input['username'];
    $response = StudentLogoutService($username);

    if ($response['status']) {
        echo json_encode(['message' => $response['message']]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => $response['message']]);
    }
}


/**
 * Search Student Details by Faculty
 */
function StudentDetailsController($input) {
    if (!isset($input['enrolment'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Enrolment required']);
        return;
    }

    $enrolment = $input['enrolment'];
    $response = searchStudentByFaculty($enrolment);

    if ($response['status']) {
        echo json_encode($response);
    } else {
        http_response_code(404);
        echo json_encode(['message' => $response['message']]);
    }
}
?>
