<?php

require_once __DIR__ . '/../controllers/FacultyController.php';

function FacultyRoutes($method, $subpath) {

    // Handle preflight OPTIONS request for CORS
    if ($method === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['message' => 'OK']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'login': // Handle "Faculty/login"
            if ($method === 'POST') {
                FacultyLoginController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'getFacultyListByStudent': // Handle "Faculty/getFacultyListByStudent"
            if ($method === 'POST') {
                GetFacultyListByStudentController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'getMentorByStudent': // Handle "Faculty/getMentorByStudent"
            if ($method === 'POST') {
                GetMentorByStudentController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        
        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid Faculty API endpoint']);
            break;
    }
}

?>
