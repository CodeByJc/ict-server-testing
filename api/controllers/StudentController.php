<?php

use function JmesPath\search;

require_once __DIR__ . '/../services/StudentService.php';

function StudentLoginController($input) {

    // DEBUG: print received input
    error_log("Received Login Request: " . json_encode($input));


    if (!isset($input['username']) || !isset($input['password']) || !isset($input['device_token'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Username and password required']);
        return;
    }

    // Extract username and password
    $username = $input['username'];
    $password = $input['password'];
    $device_token = $input['device_token'];

    // Call the service
    $response = StudentLoginService($username,$password,$device_token);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }

    // DEBUG: print response sent to Flutter
    error_log("Login Response: " . json_encode($response));
    
}

function StudentLogoutController($input) {
    if (!isset($input['username'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Username and password required']);
        return;
    }

    // Extract username and password
    $username = $input['username'];

    // Call the service
    $response = StudentLogoutService($username);

    if ($response['status']) {
        echo json_encode($response['message']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function StudentDetailsController($input) {
    if (!isset($input['enrolment'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'enrolment required']);
        return;
    }
    $enrolment = $input['enrolment'];

    // Call the service
    $response = searchStudentByFaculty($enrolment);

    if ($response['status']) {
        echo json_encode($response);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}
?>