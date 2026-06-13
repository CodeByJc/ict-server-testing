<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2), '.env');
$dotenv->load();

function validateApiKey() {
    $validApiKey = $_ENV['VALIDATION_KEY']; // Replace this with getenv('API_KEY') if using .env

    // Get the API key from the request headers
    $headers = getallheaders();
    if (!isset($headers['Authorization']) || $headers['Authorization'] !== $validApiKey) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: Invalid API Key']);
        exit; // Stop further execution
    }
}
?>
