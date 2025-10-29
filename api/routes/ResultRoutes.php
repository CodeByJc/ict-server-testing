<?php
// ResultRoutes.php
// Route dispatcher for Results API endpoints.
// Place next to controllers/ and services/ directories.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/ResultController.php';

function ResultRoutes($method, $subpath) {
    // Read JSON body once (controller methods may re-use $_FILES for uploads)
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($subpath) {

        case 'FetchSemResultsbyStudent':
            if ($method === 'POST') {
                FetchSemResultsbyStudent($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid Results API endpoint']);
            break;
    }
}