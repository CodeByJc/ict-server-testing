<?php

require_once __DIR__ . '/../services/AttendanceService.php';

function handleTotalAttendance($input) {
    if (!isset($input['s_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID is required']);
        return;
    }

    $studentId = $input['s_id'];

    $response = TotalAttendanceService($studentId);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => $response['message']]);
    }
}

function handleAttendanceByDate($input) {
    if (!isset($input['s_id']) || !isset($input['date'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Student ID and date are required']);
        return;
    }

    $studentId = $input['s_id'];
    $date = $input['date'];

    $response = AttendanceByDateService($studentId, $date);

    if ($response['status']) {
        echo json_encode($response['data']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => $response['message']]);
    }
}

function handleAttendanceList($input) {
    global $conn, $port; // Access global DB connection and port
    
    if (!isset($input['sub_id']) || !isset($input['c_date'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Subject ID, Class ID, Faculty ID, and Date are required']);
        return;
    }

    // Call the service function and get the response
    $response = getAttendanceList(
        $input['sub_id'],
        $input['f_id'],
        $input['c_id'],
        $input['c_date'],
        $input['s_time']
    );

    // Return the response as JSON
    echo json_encode($response);
}

function handleUploadAttendance($input) {
    if (empty($input) || !is_array($input)) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Invalid input format or empty input']);
        return;
    }

    // Call the service function and get the response
    $response = uploadAttendance($input);

    // Return the response as JSON
    echo json_encode($response);
}

function handleFacultySchedule($input) {
    if (isset($input['f_id']) && isset($input['date'])) {
        $FacultyId = $input['f_id'];
        $Date = $input['date'];

        // Call the service function to fetch faculty schedule
        $schedule_data = getFacultyScheduleByDate($FacultyId, $Date);

        // Return the response
        if (empty($schedule_data)) {
            echo json_encode(['message' => 'No schedule records found']);
        } else {
            echo json_encode($schedule_data);
        }
    } else {
        echo json_encode(['message' => 'Faculty ID and date are required']);
    }
}

function handleEngagedStudentsByCC($input) {
    if (isset($input['f_id'])) {
        $FacultyId = $input['f_id'];

        // Call the service function to get engaged students by CC
        $students_data = getEngagedStudentsByCC($FacultyId);

        // Return the response
        if (empty($students_data)) {
            echo json_encode(['message' => 'No students']);
        } else {
            echo json_encode($students_data);
        }
    } else {
        echo json_encode(['message' => 'Faculty ID is required']);
    }
}

function handleEngagedStudentUpsert($input) {
    if (isset($input['student_info_id']) && isset($input['reason']) && 
        isset($input['type']) && isset($input['faculty_info_id']) && 
        isset($input['start_date']) && isset($input['end_date'])) {
        
        // Call the service function to handle the upsert logic
        $response = upsertEngagedStudent($input);

        // Send the response
        echo json_encode($response);
    } else {
        // Handle missing required fields
        http_response_code(400);
        echo json_encode(['message' => 'All fields are required']);
    }
}

function handleStudentsByCC($input) {
    if (isset($input['f_id'])) {
        $FacultyId = $input['f_id'];

        // Call the service function
        $response = getStudentsByCC($FacultyId);

        // Send the response
        echo json_encode($response);
    } else {
        // Handle missing required fields
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID is required']);
    }
}

function handleExtraSchedule($input) {
    if (isset($input['f_id'])) {
        $FacultyId = $input['f_id'];

        // Call the service function
        $response = getExtraSchedule($FacultyId);

        // Send the response
        echo json_encode($response);
    } else {
        // Handle missing required fields
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID is required']);
    }
}

function handleExtraAttendanceStudentsList($input) {
    if (isset($input['sub_id']) && isset($input['c_date']) && isset($input['f_id'])) {
        $SubjectId = $input['sub_id'];
        $FacultyId = $input['f_id'];
        $CDate = $input['c_date'];

        // Call the service function
        $response = getExtraAttendanceStudentsList($SubjectId, $FacultyId, $CDate);

        // Send the response
        echo json_encode($response);
    } else {
        // Handle missing required fields
        http_response_code(400);
        echo json_encode(['message' => 'Subject ID, Faculty ID, and Date are required']);
    }
}

function handleUploadExtraAttendance($input) {
    if (!empty($input) && is_array($input)) {
        // Call the service function
        $response = uploadExtraAttendance($input);

        // Send the response
        echo json_encode($response);
    } else {
        // Handle invalid input format
        http_response_code(400);
        echo json_encode(['message' => 'Invalid input format or empty input']);
    }
}



// Faculty 


function FetchAttendanceInfo($input) {
    if (!isset($input['faculty_info_id']) || !isset($input['date'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID and date are required']);
        return;
    }
    $response = FetchAttendanceInfoService($input['faculty_info_id'], $input['date']);
    echo json_encode($response);
}

function PunchIn($input) {
    if (!isset($input['faculty_info_id']) || !isset($input['punch_in'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID and punch in time are required']);
        return;
    }
    $response = PunchInService($input['faculty_info_id'],$input['date'],$input['punch_in']);
    echo json_encode($response);
}

function PunchOut($input) {
    if (!isset($input['faculty_info_id']) || !isset($input['date']) || !isset($input['punch_out'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID, date and punch out time are required']);
        return;
    }
    $response = PunchOutService($input['faculty_info_id'], $input['date'], $input['punch_out']);
    echo json_encode($response);
}

function GetAttendanceHistory($input) {
    if (!isset($input['faculty_info_id'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Faculty ID is required']);
        return;
    }
    $response = GetAttendanceHistoryService($input['faculty_info_id']);
    echo json_encode($response);
}
?>