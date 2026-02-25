<?php
require_once __DIR__ . '/../services/InterviewBankService.php';

function CreateInterviewBankController($input) {
    $response = createInterviewBankService($input);
    echo json_encode($response);
}

function GetAllInterviewBankController() {
    $response = getAllInterviewBankService();
    echo json_encode($response);
}

function GetInterviewBankByIdController($student_id) {
    $response = getInterviewBankByIdService($student_id);
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
