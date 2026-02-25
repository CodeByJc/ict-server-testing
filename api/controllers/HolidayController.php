<?php

require_once __DIR__ . '/../services/HolidayService.php';

// Add Holiday
function AddHolidayController($input) {
    if (!isset($input['holiday_name']) || !isset($input['holiday_date'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Holiday name and date are required']);
        return;
    }

    $holidayName = $input['holiday_name'];
    $holidayDate = $input['holiday_date'];
    $response = AddHolidayService($holidayName, $holidayDate);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => 'Holiday added successfully']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

// Edit Holiday
function EditHolidayController($input) {
    if (!isset($input['id']) || !isset($input['holiday_name']) || !isset($input['holiday_date'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'ID, holiday name, and date are required']);
        return;
    }

    $holidayID = $input['id'];
    $holidayName = $input['holiday_name'];
    $holidayDate = $input['holiday_date'];
    $response = EditHolidayService($holidayID, $holidayName, $holidayDate);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => 'Holiday updated successfully']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

// Delete Holiday
function DeleteHolidayController($input) {
    if (!isset($input['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'ID is required']);
        return;
    }

    $holidayID = $input['id'];
    $response = DeleteHolidayService($holidayID);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => 'Holiday deleted successfully']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

// Get All Holidays
function GetAllHolidaysController() {
    $response = GetAllHolidaysService();

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

function GetNextUpcomingHolidayController() {
    $response = GetNextUpcomingHolidayService();

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

?>
