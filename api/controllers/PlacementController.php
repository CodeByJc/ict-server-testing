<?php

require_once __DIR__ . '/../services/PlacementService.php';

function RecentlyPlaced($input) {
    if (!isset($input['batch_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Batch id is required']);
        return;
    }
    $batchId = $input['batch_id'];
    $response = RecentlyPlacedService($batchId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function CompanyList() {
    $response = GetCompanyListService();
    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function CampusDriveStudentList($input) {
    if (!isset($input['batch_id'])||!isset($input['student_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Batch id is required and Student id is required']);
        return;
    }
    $batchId = $input['batch_id'];
    $studentId = $input['student_id'];
    $response = GetCampusDriveStudentListService($studentId,$batchId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function StatusUpdateCampusDrive($input) {
    if (!isset($input['student_id']) || !isset($input['campus_drive_id']) || !isset($input['status'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student id, Campus drive id, and Status are required']);
        return;
    }

    $studentId = $input['student_id'];
    $driveId = $input['campus_drive_id'];
    $status = $input['status'];

    $response = StatusUpdateCampusDriveService($studentId, $driveId, $status);

    if ($response['status']) {
        http_response_code(200);
        echo json_encode(['message' => $response['message']]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => $response['message']]);
    }
}

function CampusDriveStudentRoundsList($input) {
    if (!isset($input['batch_id'])||!isset($input['student_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Batch id is required and Student id is required']);
        return;
    }
    $batchId = $input['batch_id'];
    $studentId = $input['student_id'];
    $response = GetCampusDriveRoundsByStudentService($studentId,$batchId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        echo json_encode(['message' => $response['message']]);
    }
}

function AddOldDataController($input) {
    // Check if input is empty
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'No data provided']);
        return;
    }

    // Handle single object or array of objects
    $dataList = is_array($input) && isset($input[0]) ? $input : [$input];
    $results = [];
    $hasError = false;

    foreach ($dataList as $index => $data) {
        // Validate required fields
        if (!isset($data['student_info_id']) || !isset($data['company_info_id']) || !isset($data['date']) || !isset($data['data'])) {
            $results[] = ['index' => $index, 'status' => false, 'message' => 'All fields are required: student_info_id, company_info_id, date, data'];
            $hasError = true;
            continue;
        }

        $enrollmentNo = trim($data['student_info_id']);
        $companyInfoId = intval($data['company_info_id']);
        $date = $data['date'];
        $interviewData = $data['data'];

        // Basic date format validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $results[] = ['index' => $index, 'status' => false, 'message' => 'Invalid date format for entry ' . $index . '. Use YYYY-MM-DD'];
            $hasError = true;
            continue;
        }

        // Call service for each entry
        $response = addOldDataService($enrollmentNo, $companyInfoId, $date, $interviewData);
        $results[] = array_merge(['index' => $index], $response);
        if (!$response['status']) {
            $hasError = true;
        }
    }
    // Set appropriate response code
    http_response_code($hasError ? 207 : 200); // 207 Multi-Status if any errors
    echo json_encode([
        'status' => !$hasError,
        'message' => $hasError ? 'Some entries failed to process' : 'All interview data added successfully',
        'results' => $results
    ]);
}

?>
