<?php

require_once __DIR__ . '/../services/PasswordService.php';

function UpdatePasswordController($input) {
    
    if (!isset($input['username']) || !isset($input['currentPass']) || !isset($input['newPass'])){
        http_response_code(400);
        echo json_encode(['message' => 'Username and password required']);
    }
    
    $username = $input['username'];
    $currentPass = $input['currentPass'];
    $newPass = $input['newPass'];
    

    $response = UpdatePasswordService($username, $currentPass,$newPass);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => $response['message']]);
    } else {
        http_response_code(401); // Unauthorized or other error code
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }    
}

?>