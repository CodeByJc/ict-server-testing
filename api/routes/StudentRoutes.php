<?php

require_once __DIR__ . '/../controllers/StudentController.php';

function StudentRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'login': // Handle "Student/Login"
            if ($method === 'POST') {
                StudentLoginController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'logout': // Handle "Student/Logutn"
            if ($method === 'POST') {
                StudentLogoutController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'by-enrolment': // Handle "Student/by-enrolment"
            if ($method === 'POST') {
                StudentDetailsController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid Student API endpoint']);
            break;
    }
}
?>