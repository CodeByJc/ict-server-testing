<?php

require_once __DIR__ . '/../controllers/AttendanceController.php';

function AttendanceRoutes($method, $subpath) {
    $input = json_decode(file_get_contents('php://input'), true);
    switch ($subpath) {
        case 'TotalAttendance': // Handle "Attendance/TotalAttendance"
            if ($method === 'POST') {
                handleTotalAttendance($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Invalid request method']);
            }
            break;
        case 'AttendanceByDate': // Handle "Attendance/AttendanceByDate"
            if ($method === 'POST') {
                handleAttendanceByDate($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Invalid request method']);
            }
            break;
            
        case 'GetAttendanceList': // Handle "Attendance/GetAttendanceList"
            if ($method === 'POST') {
                handleAttendanceList($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Invalid request method']);
            }
            break;
            
        case 'UploadAttendance': // Handle "Attendance/UploadAttendance"
            // This is where the function for UploadAttendance is called
            if ($method == 'POST') {
                // Get the input data (assumed to be sent in the body of the POST request)
                $input = json_decode(file_get_contents("php://input"), true);
                
                // Call the controller function for uploading attendance
                handleUploadAttendance($input);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        
        case 'GetFacultySchedule': // Handle "Attendance/GetFacultySchedule"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true);  // Decode input

                // Call the controller function to handle the request
                handleFacultySchedule($input);
            } else {
                http_response_code(405);  // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;


        
        case 'GetStudentsByCC': // Handle "Attendance/GetStudentsByCC"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true); // Decode JSON input
                handleStudentsByCC($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'GetEngagedStudentsByCC': // Handle "Attendance/GetEngagedStudentsByCC"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true);  // Decode input

                // Call the controller function to handle the request
                handleEngagedStudentsByCC($input);
            } else {
                http_response_code(405);  // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'UpsertEngagedStudent': // Handle "Attendance/UpsertEngagedStudent"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true);  // Decode input

                // Call the controller function to handle the request
                handleEngagedStudentUpsert($input);
            } else {
                http_response_code(405);  // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'GetExtraSchedule': // Handle "Attendance/GetExtraSchedule"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true); // Decode JSON input
                handleExtraSchedule($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'GetExtraAttendanceList': // Handle "Attendance/GetExtraAttendanceList"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true); // Decode JSON input
                handleExtraAttendanceStudentsList($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'UploadExtraAttendance': // Handle "Attendance/UploadExtraAttendance"
            if ($method == 'POST') {
                $input = json_decode(file_get_contents("php://input"), true); // Decode JSON input
                handleUploadExtraAttendance($input); // Call the controller function
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['message' => 'Method not allowed for this endpoint']);
            }
            break;
        case 'facultyAttendance': // Handle "Attendance/facultyAttendance"
        switch ($method) {
            case 'POST':
                if (isset($input['action']) && $input['action'] === 'punchIn') {
                    PunchIn($input);
                } elseif (isset($input['action']) && $input['action'] === 'punchOut') {
                    PunchOut($input);
                } elseif (isset($input['action']) && $input['action'] === 'history') {
                    GetAttendanceHistory($input);
                } elseif (isset($input['action']) && $input['action'] === 'primary'){
                    FetchAttendanceInfo($input);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid action']);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(['message' => 'Method not allowed']);
        }
        break;
        default:
            http_response_code(404); // Not Found
            echo json_encode(['message' => 'Invalid Faculty API endpoint']);
            break;
    }
}
?>