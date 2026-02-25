<?php

require_once __DIR__ . '/../controllers/PasswordController.php';

function PasswordRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'updatePassword': // Handle "Password/updatePassword"
            if ($method === 'POST') {
                UpdatePasswordController($input);
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