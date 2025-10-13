<?php
require_once __DIR__ . '/../Controllers/AnnouncementController.php';

function AnnouncementRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'create':
            if ($method === 'POST') {
                CreateAnnouncementController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'list':
            if ($method === 'GET' && isset($_GET['batch_id'])) {
                GetAllAnnouncementController($_GET['batch_id']);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'get':
            if ($method === 'GET' && isset($_GET['id'])) {
                GetAnnouncementByIdController($_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid ID']);
            }
            break;

        case 'update':
            if ($method === 'PUT' && isset($_GET['id'])) {
                UpdateAnnouncementController($_GET['id'], $input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid ID']);
            }
            break;

        case 'delete':
            if ($method === 'DELETE') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['id'])) {
                    DeleteAnnouncementController($input['id']);
                } else {
                    http_response_code(400);
                    echo json_encode(['status' => false, 'message' => 'Missing or invalid ID in body']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Invalid request method']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid API endpoint']);
            break;
    }
}
