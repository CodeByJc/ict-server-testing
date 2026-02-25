<?php

require_once __DIR__ . '/../services/ExamService.php';


function GetExamListController($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID required']);
        return;
    }
    $studentId = $input['s_id'];

    $response = GetExamListService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

?>