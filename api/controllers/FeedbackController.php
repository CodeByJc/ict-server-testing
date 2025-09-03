<?php
require_once __DIR__ . '/../services/FeedbackServices.php';

function AddFeedbackController($input) {
    $review = isset($input['review']) ? trim($input['review']) : null;
    $faculty_info_id = isset($input['faculty_info_id']) ? (int)$input['faculty_info_id'] : null;
    $student_info_id = isset($input['student_info_id']) ? (int)$input['student_info_id'] : null;
    $sem_info_id = isset($input['sem_info_id']) ? (int)$input['sem_info_id'] : null;

    if (!$review || !$faculty_info_id || !$student_info_id || !$sem_info_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Review, faculty_info_id, and student_info_id are required']);
        return;
    }

    $response = addFeedbackService($review, $faculty_info_id, $student_info_id,$sem_info_id);
    echo json_encode($response);
}

function GetFeedbackByStudentController($student_id) {
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Student ID is required']);
        return;
    }

    $response = getFeedbackByStudentService($student_id);
    echo json_encode($response);
}

function GetFeedbackByFacultyController($faculty_id) {
    if (!$faculty_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Faculty ID is required']);
        return;
    }

    $response = getFeedbackByFacultyService($faculty_id);
    echo json_encode($response);
}

function UpdateFeedbackViewedController($feedback_id) {
    if (!$feedback_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Feedback ID is required']);
        return;
    }

    $response = updateFeedbackViewedService($feedback_id);
    echo json_encode($response);
}