<?php

require_once __DIR__ . '/../controllers/ZoomController.php';

function ZooomRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'addLink': // Handle "ZoomLink/addLink"
            if ($method === 'POST') {
                AddZoomLinkController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'editLink': // Handle "ZoomLink/editLink"
            if ($method === 'PUT') {
                EditZoomLinkController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'deleteLink':  // Handle "ZoomLink/deleteLink"
            if ($method === 'DELETE') {
                DeleteZoomLinkController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        
        case 'getUpcomingLinks': // Handle "ZoomLink/getUpcomingLinks"
            if ($method === 'POST') {
                GetUpcomingZoomLinksController($input);
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