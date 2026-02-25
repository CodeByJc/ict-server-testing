<?php

require_once __DIR__ . '/../services/ParentService.php';

function ParentLoginController($input) {

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($input['username']) || !isset($input['password']) || !isset($input['device_token'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Username, password and device_token are required']);
        return;
    }

    $username = $input['username'];
    $password = $input['password'];
    $device_token = $input['device_token'];

    // Call the service that handles authentication & returns standardized response
    $response = ParentLoginService($username, $password, $device_token);

    if ($response['status']) {
    echo json_encode([
        'status' => true,
        'token' => $response['token'],
        'data' => $response['data']
    ]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }

    error_log("Parent Login Response: " . json_encode($response));
}


function ParentLogoutController($input) {
    if (!isset($input['username'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Username and password required']);
        return;
    }

    // Extract username and password
    $username = $input['username'];

    // Call the service
    $response = ParentOutService($username);

    if ($response['status']) {
        echo json_encode($response['message']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function GetFacultyContactController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID required']);
        return;
    }
    $studentId = $input['s_id'];

    $response = GetFacultyContactService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function GetStudentTimetableController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID required']);
        return;
    }
    $studentId = $input['s_id'];

    $response = GetStudentTimetableService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function ParentSendQueryController($input) {
    if (!isset($input['parent_id']) || !isset($input['query_text'])) {
        http_response_code(400);
        echo json_encode(['message' => 'parent_id & query_text required']);
        return;
    }

    $parent_id = $input['parent_id'];
    $query_text = $input['query_text'];

    $response = ParentSendQueryService($parent_id, $query_text);

    if ($response['status']) {
        echo json_encode(['message' => 'Query sent successfully']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function GetParentQueriesController($input) {
    if (!isset($input['parent_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'parent_id required']);
        return;
    }

    $parent_id = $input['parent_id'];

    $response = GetParentQueriesService($parent_id);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

?>