<?php
function validateApiKey() {
    $validApiKey = 'ictmu'; // Replace this with getenv('API_KEY') if using .env

    // Get the API key from the request headers
    $headers = getallheaders();
    if (!isset($headers['Authorization']) || $headers['Authorization'] !== $validApiKey) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: Invalid API Key']);
        exit; // Stop further execution
    }
}
?>
