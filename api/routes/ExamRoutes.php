<?php

require_once __DIR__ . '/../controllers/ExamController.php';

function ExamRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'getExamList': // Handle "Exam/getExamList"
            if ($method === 'POST') {
                GetExamListController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid API endpoint']);
            break;
    }
}
?>