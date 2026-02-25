<?php

require_once __DIR__ . '/../controllers/HolidayController.php';

function HolidayRoutes($method, $subpath) {
    $input = json_decode(file_get_contents("php://input"), true);

    switch ($subpath) {
        case 'addHoliday': // Handle "Holiday/addHoliday"
            if ($method === 'POST') {
                AddHolidayController($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'editHoliday': // Handle "Holiday/editHoliday"
            if ($method === 'PUT') {
                EditHolidayController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'deleteHoliday': // Handle "Holiday/deleteHoliday"
            if ($method === 'DELETE') {
                DeleteHolidayController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'getAllHolidays': // Handle "Holiday/getAllHolidays"
            if ($method === 'GET') {
                GetAllHolidaysController();
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;

        case 'getNextUpcomingHoliday': // Handle "Holiday/getNextUpcomingHoliday"
            if ($method === 'GET') {
                GetNextUpcomingHolidayController();
            } else {
                http_response_code(405); // Method Not Allowed
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