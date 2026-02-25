<?php
require_once __DIR__ . '/../services/AnnouncementService.php';

function CreateAnnouncementController($input) {
    $response = createAnnouncementService($input);
    echo json_encode($response);
}

function GetAllAnnouncementController($batch_id = null, $faculty_id = null) {
    $response = getAllAnnouncementService($batch_id, $faculty_id);
    echo json_encode($response);
}

function GetAnnouncementByIdController($faculty_id) {
    $response = getAnnouncementByIdService($faculty_id);
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
function GetAnnouncementFieldsController() {
    $response = getAnnouncementFieldsService();
    echo json_encode($response);
}