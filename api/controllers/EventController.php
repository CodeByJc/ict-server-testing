<?php
require_once __DIR__ . '/../services/EventService.php';

function CreateEventController($input) {
    $title = isset($input['title']) ? trim($input['title']) : null;
    $details = isset($input['details']) ? trim($input['details']) : null;
    $datetime = isset($input['datetime']) ? trim($input['datetime']) : null;

    if (!$title || !$details) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Title and details are required']);
        return;
    }

    $response = createEventService($title, $details, $datetime);
    echo json_encode($response);
}

function GetAllEventsController() {
    $response = getAllEventsService();
    echo json_encode($response);
}

function GetEventByIdController($id) {
    $id = intval($id);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid Event ID']);
        return;
    }

    $response = getEventByIdService($id);
    echo json_encode($response);
}

function UpdateEventController($id, $input) {
    $id = intval($id);
    $title = isset($input['title']) ? trim($input['title']) : null;
    $details = isset($input['details']) ? trim($input['details']) : null;
    $datetime = isset($input['datetime']) ? trim($input['datetime']) : null;

    if (!$id || !$title || !$details) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Event ID, title, and details are required']);
        return;
    }

    $response = updateEventService($id, $title, $details, $datetime);
    echo json_encode($response);
}

function DeleteEventController($id) {
    $id = intval($id);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid Event ID']);
        return;
    }

    $response = deleteEventService($id);
    echo json_encode($response);
}

function GetUpcomingEventController() {
    $response = getUpcomingEventService();
    echo json_encode($response);
}
