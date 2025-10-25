<?php
require_once __DIR__ . '/../Controllers/InterviewBankController.php';

function InterviewBankRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'create':
            if ($method === 'POST') {
                CreateInterviewBankController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'list':
            if ($method === 'GET') {
                GetAllInterviewBankController();
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'get':
            if ($method === 'GET' && isset($_GET['student_id'])) {
                GetInterviewBankByIdController($_GET['student_id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid ID']);
            }
            break;

        case 'update':
            if ($method === 'PUT' && isset($_GET['id'])) {
                UpdateInterviewBankController($_GET['id'], $input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid ID']);
            }
            break;

        case 'delete':
            if ($method === 'DELETE' && isset($_GET['id'])) {
                DeleteInterviewBankController($_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid ID']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid API endpoint']);
            break;
    }
}
