<?php
require_once __DIR__ . '/../services/ResultService.php';

function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function FetchSemResultsbyStudent($input) {
    // Basic auth/role check (adjust to your auth system if needed)
    // if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    //     json_response(['status' => false, 'message' => 'Unauthorized'], 401);
    // }

    // if (!is_array($input)) {
    //     json_response(['status' => false, 'message' => 'Invalid request body'], 400);
    // }

    if (!isset($input['student_id']) || !isset($input['sem_info_id'])) {
        json_response(['status' => false, 'message' => 'Missing required parameters: student_id and sem_info_id'], 400);
    }

    $studentId = intval($input['student_id']);
    $sem_info_id = intval($input['sem_info_id']);

    if ($studentId <= 0) {
        json_response(['status' => false, 'message' => 'Invalid student_id'], 400);
    }

    try {
        $response = getSemResultsByStudent($studentId, $sem_info_id); // service returns ['status' => bool, 'data' => [...]] or ['status'=>false,'message'=>...]
        if ($response['status']) {
            // The service returns both resultsets: ['semesters'=>[], 'subjects'=>[]]
            // Normalize to a consistent payload
            json_response([
                'status' => true,
                'data' => $response['data']
            ], 200);
        } else {
            json_response(['status' => false, 'message' => $response['message']], 500);
        }
    } catch (Exception $e) {
        json_response(['status' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
    }
}
?>