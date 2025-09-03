<?php
require_once __DIR__ . '/../Services/InterviewBankService.php';

function CreateInterviewBankController($input) {
    $response = createInterviewBankService($input);
    echo json_encode($response);
}

function GetAllInterviewBankController() {
    $response = getAllInterviewBankService();
    echo json_encode($response);
}

function GetInterviewBankByIdController($id) {
    $response = getInterviewBankByIdService($id);
    echo json_encode($response);
}

function UpdateInterviewBankController($id, $input) {
    $response = updateInterviewBankService($id, $input);
    echo json_encode($response);
}

function DeleteInterviewBankController($id) {
    $response = deleteInterviewBankService($id);
    echo json_encode($response);
}
