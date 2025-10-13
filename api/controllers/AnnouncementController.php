<?php
require_once __DIR__ . '/../Services/AnnouncementService.php';

function CreateAnnouncementController($input) {
    $response = createAnnouncementService($input);
    echo json_encode($response);
}

function GetAllAnnouncementController($batch_id) {
    $response = getAllAnnouncementService($batch_id);
    echo json_encode($response);
}

function GetAnnouncementByIdController($id) {
    $response = getAnnouncementByIdService($id);
    echo json_encode($response);
}

function UpdateAnnouncementController($id, $input) {
    $response = updateAnnouncementService($id, $input);
    echo json_encode($response);
}

function DeleteAnnouncementController($id) {
    $response = deleteAnnouncementService($id);
    echo json_encode($response);
}
