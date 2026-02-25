<?php

require_once __DIR__ . '/../services/AppVersionService.php';

function AppVersionCheckController() {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input fields
    if (!isset($input['login']) || !isset($input['code'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Both login and code are required']);
        return;
    }

    // Extract input data
    $login = $input['login'];
    $code = $input['code'];

    // Call the service to handle logic
    $response = AppVersionService($login, $code);
    echo json_encode($response);
}

?>