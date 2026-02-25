<?php

require_once __DIR__ . '/../controllers/ParentController.php';

function    ParentRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'login': // Handle "Parent/Login"
            if ($method === 'POST') {
                ParentLoginController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'logout': // Handle "Parent/Logutn"
            if ($method === 'POST') {
                ParentLogoutController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'getFacultyContact': // Handle "Parent/getFacultyContact"
            if ($method === 'POST') {
                GetFacultyContactController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'getStudentTimetable': // Handle "Parent/getStudentTimetable"
            if ($method === 'POST') {
                GetStudentTimetableController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'sendQuery':
            if ($method === 'POST') {
                ParentSendQueryController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'getQueries':
            if ($method === 'POST') {
                GetParentQueriesController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid Parent API endpoint']);
            break;
    }
}
?>