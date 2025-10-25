<?php
require_once __DIR__ . '/../services/FeedbackServices.php';

function AddFeedbackController($input) {
    $review = isset($input['review']) ? trim($input['review']) : null;
    $faculty_info_id = isset($input['faculty_info_id']) ? (int)$input['faculty_info_id'] : null;
    $student_info_id = isset($input['student_info_id']) ? trim($input['student_info_id']) : null;
    $sem_info_id = isset($input['sem_info_id']) ? (int)$input['sem_info_id'] : null;

    // Check for required fields BEFORE hashing
    if (!$review || !$faculty_info_id || !$student_info_id || !$sem_info_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Review, faculty_info_id, student_info_id, and sem_info_id are required']);
        return;
    }

    // Hash the student_info_id after checking it's not empty
    $student_info_id_hashed = hash('sha256', $student_info_id);

    $response = addFeedbackService($review, $faculty_info_id, $student_info_id_hashed, $sem_info_id);
    echo json_encode($response);
}

function GetFeedbackByStudentController($input) {
    $student_info_id = isset($input['student_info_id']) ? trim($input['student_info_id']) : null;
    if (!$student_info_id) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Student ID is required']);
        return;
    }
    $student_info_id_hashed = hash('sha256', $student_info_id);

    $response = getFeedbackByStudentService($student_info_id_hashed);

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