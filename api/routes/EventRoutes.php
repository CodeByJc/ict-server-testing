<?php
require_once __DIR__ . '/../controllers/EventController.php';

function EventRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'create':
            if ($method === 'POST') {
                CreateEventController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'list':
            if ($method === 'GET') {
                GetAllEventsController();
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'get':
            if ($method === 'GET' && isset($_GET['id'])) {
                GetEventByIdController($_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid event ID']);
            }
            break;

        case 'update':
            if ($method === 'PUT' && isset($_GET['id'])) {
                UpdateEventController($_GET['id'], $input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid event ID']);
            }
            break;

        case 'delete':
            if ($method === 'DELETE' && isset($_GET['id'])) {
                DeleteEventController($_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid event ID']);
            }
            break;
        case 'getUpcomingEvent':
            if ($method === 'GET') {
                GetUpcomingEventController();
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
            

        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid API endpoint']);
            break;
    }
}
?>
