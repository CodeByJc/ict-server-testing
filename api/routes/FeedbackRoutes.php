<?php
require_once __DIR__ . '/../controllers/FeedbackController.php';

function FeedbackRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'add':
            if ($method === 'POST') {
                AddFeedbackController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'by-student':
            if ($method === 'POST') {
                GetFeedbackByStudentController($input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid student ID']);
            }
            break;

        case 'by-faculty':
            if ($method === 'GET' && isset($_GET['faculty_id'])) {
                GetFeedbackByFacultyController($_GET['faculty_id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid faculty ID']);
            }
            break;

        case 'update-viewed':
            if ($method === 'PUT' && isset($_GET['feedback_id'])) {
                UpdateFeedbackViewedController($_GET['feedback_id']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Missing or invalid feedback ID']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['message' => 'Invalid API endpoint']);
            break;
    }
}