<?php
require_once __DIR__ . '/../Controllers/LeaveController.php';

function LeaveRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'leaveRequest': // Handle "Leave/leaveRequest"
            if ($method === 'POST') {
                LeaveRequestController($input, $_FILES); // Pass $_FILES for file upload
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'getLeaveHistory': // Handle "Leave/getLeaveHistory"
            if ($method === 'GET') {
                GetLeaveHistoryController($input);
            } else {
                http_response_code(405);
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