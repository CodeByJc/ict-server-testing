<?php

require_once __DIR__ . '/../services/ZoomService.php';

function AddZoomLinkController($input) {
    if (
        !isset($input['zoom_link']) ||
        !isset($input['zoom_date']) ||
        !isset($input['zoom_link_time'])
    ) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Required fields: zoom_link, zoom_date, zoom_link_time']);
        return;
    }

    $zoomLink = $input['zoom_link'];
    $zoomDate = $input['zoom_date'];
    $zoomTime = $input['zoom_link_time'];
    $zoomTitle = $input['zoom_link_title'] ?? null;
    $semInfoId = $input['sem_info_id'] ?? null;
    $facultyInfoId = $input['faculty_info_id'] ?? null;

    $response = AddZoomLinkService($zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => $response['message']]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

function EditZoomLinkController($input) {
    if (
        !isset($input['id']) ||
        !isset($input['zoom_link']) ||
        !isset($input['zoom_date']) ||
        !isset($input['zoom_link_time'])
    ) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Required fields: id, zoom_link, zoom_date, zoom_link_time']);
        return;
    }

    $id = $input['id'];
    $zoomLink = $input['zoom_link'];
    $zoomDate = $input['zoom_date'];
    $zoomTime = $input['zoom_link_time'];
    $zoomTitle = $input['zoom_link_title'] ?? null;
    $semInfoId = $input['sem_info_id'] ?? null;
    $facultyInfoId = $input['faculty_info_id'] ?? null;

    $response = EditZoomLinkService($id, $zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => $response['message']]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

function DeleteZoomLinkController($input) {
    if (!isset($input['id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Required field: id']);
        return;
    }

    $id = $input['id'];
    $response = DeleteZoomLinkService($id);

    if ($response['status']) {
        echo json_encode(['status' => true, 'message' => $response['message']]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => false, 'message' => $response['message']]);
    }
}

function GetUpcomingZoomLinksController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Required field: id']);
        return;
    }

    $id = $input['s_id'];

    $response = GetUpcomingZoomLinksService($id);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode($response['message']);
    }
}
?>