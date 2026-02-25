<?php

require_once __DIR__ . '/../services/FacultyService.php';

function FacultyLoginController($input) {
    // Log request for debugging (do not expose sensitive info in production logs)
    error_log("Received Faculty Login Request: " . json_encode($input));

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($input['username']) || !isset($input['password']) || !isset($input['device_token'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username, password and device_token are required']);
        return;
    }

    $username = $input['username'];
    $password = $input['password'];
    $device_token = $input['device_token'];

    $response = FacultyLoginService($username, $password, $device_token);

    if (!is_array($response)) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal error']);
        error_log("Faculty Login Response (invalid): " . json_encode($response));
        return;
    }

    if (!empty($response['status'])) {
        // Success: return same shape as Student endpoint (status, token, data)
        echo json_encode([
            'status' => true,
            'token' => $response['token'],
            'data' => $response['data']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => $response['message'] ?? 'Unauthorized']);
    }

    error_log("Faculty Login Response: " . json_encode($response));
}

function GetFacultyListByStudentController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID required']);
        return;
    }
    $studentId = $input['s_id'];

    $response = GetFacultyListByStudentService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']); 
        // will look like {"faculty_list": [...], "mentor": {...}}
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}


function GetMentorByStudentController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID required']);
        return;
    }
    $studentId = $input['s_id'];

    $response = GetMentorByStudentService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}
// Controller for faculty directory listing
function GetFacultyDirectoryController($input) {
    // Optional filter: designation (e.g., 'ap', 'hod')
    $designation = isset($input['designation']) ? $input['designation'] : null;

    $response = GetFacultyDirectoryService($designation);
    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => $response['message']]);
    }
}

?>
