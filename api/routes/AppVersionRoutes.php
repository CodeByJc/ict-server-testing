<?php

require_once __DIR__ . '/../controllers/AppVersionController.php';

function AppVersionRoutes($method, $subpath) {
    switch ($subpath) {
        case 'check': // Handle "AppVersion/check"
            if ($method === 'POST') {
                AppVersionCheckController(); // Call the specific controller
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid subpath']);
            break;
    }
}
?>