<?php

require_once __DIR__ . '/../controllers/PlacementController.php';

function PlacementRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'recentlyPlaced': // Handle "Placement/recentlyPlaced"
            if ($method === 'POST') {
                RecentlyPlaced($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'companyList': // Handle "Placement/companyList"
            if ($method === 'GET') {
                CompanyList();
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'campusDriveByStudentList': // Handle "Placement/campusDriveByStudentList"
            if ($method === 'POST') {
                CampusDriveStudentList($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'statusUpdateCampusDrive': // Handle "Placement/statusUpdateCampusDrive"
            if ($method === 'POST') {
                StatusUpdateCampusDrive($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);      
            }
            break;
         case 'campusDriveStudentRoundList': // Handle "Placement/campusDriveStudentRoundList"
            if ($method === 'POST') {
                CampusDriveStudentRoundsList($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        case 'addOldData': // Handle Placement/addOldData
            if ($method === 'POST') {
                AddOldDataController($input);
            } else {
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid Student API endpoint']);
            break;
    }
}
?>