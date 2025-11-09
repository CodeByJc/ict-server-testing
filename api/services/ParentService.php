<?php

require_once __DIR__ . '/../vendor/autoload.php'; // adjust path to composer autoload if needed
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once __DIR__ . '/../db/db_connection.php'; // ensure $conn is available (adjust path)


function ParentOutService($username) {
    global $conn; // Use global DB connection

    // Sanitize input
    $username = $conn->real_escape_string($username);


    // Set device token to NULL
    $update_stmt = $conn->prepare("UPDATE user_login SET device_token = NULL WHERE username = ?");
    if (!$update_stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the update statement'];
    }

    $update_stmt->bind_param("s", $username);
    $update_stmt->execute();
    $update_stmt->close();

    return ['status' => true, 'message' => 'User logged out successfully'];
}


function ParentLoginService($username, $password, $device_token) {
    global $conn;

    // sanitize incoming username
    $username = $conn->real_escape_string($username);

    // 1) Ensure username exists (optional, clearer messages)
    $stmt = $conn->prepare("SELECT password FROM user_login WHERE username = ?");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare query'];
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // 2) Call stored procedure
    $stmt = $conn->prepare("CALL LoginParent(?)");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->next_result(); // Important after calling a stored procedure
        // ✅ Create JWT token valid for 7 days
        $issuedAt = time();
        $expirationTime = $issuedAt + (7 * 24 * 60 * 60); // 7 days
        $payload = [
            'username' => $username,
            'iat' => $issuedAt,
            'exp' => $expirationTime,
        ];

        // NOTE: move secret to config/env in production
        $secretKey = 'a3e1b9e673d1f4c0e6e8d2b1a96f0e5c24b7f122e38b04a94d3cfab1a8f29c9d';
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // ✅ Update device token if provided
        $update_stmt = $conn->prepare("UPDATE user_login SET device_token = ? WHERE username = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $device_token, $username);
            $update_stmt->execute();
            $update_stmt->close();
        }

        // ✅ Return standardized response
        return [
            'status' => true,
            'token' => $jwt,
            'data' => [
                'parent_details' => json_decode($row['parent_details'], true),
                'student_details' => json_decode($row['student_details'], true),
                'class_details' => json_decode($row['class_details'], true),
            ]
        ];

    } else {
        $stmt->close();
        $conn->next_result();
        return ['status' => false, 'message' => 'No user data found'];
    }
}




function GetFacultyContactService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetFacultyContactByStudent(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
        $faculty_data = [];
        while ($row = $result->fetch_assoc()) {
            $faculty_data[] = $row;
        }
        $stmt->close();
        if (count($faculty_data) > 0) {
            return ['status' => true, 'data' => $faculty_data];
        }else{
            return ['status' => false, 'message' => 'Invalid Student Id'];
        }
    $stmt->close();
    http_response_code(401); // Unauthorized
    return ['status' => false, 'message' => 'Invalid Student Id'];
}

function GetStudentTimetableService($studentId) {
    global $conn; 
    $stmt = $conn->prepare("CALL GetStudentTimetable(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    
    $stmt->execute();
    $result = $stmt->get_result();
        $subject_data = [];
        while ($row = $result->fetch_assoc()) {
            $subject_data[] = $row;
        }
        $stmt->close();
        if (count($subject_data) > 0) {
            return ['status' => true, 'data' => $subject_data];
        }
        else if(count($subject_data) == 0){
            return ['status' => true, 'data' => ["message" => "No Classes Scheduled"]];
        }
        else{
            return ['status' => false, 'message' => 'Invalid Student Id'];
        }
    $stmt->close();
    http_response_code(401); // Unauthorized
    return ['status' => false, 'message' => 'Invalid Student Id'];
}
function ParentSendQueryService($parent_id, $query_text) {
    global $conn;

   $stmt = $conn->prepare("
        INSERT INTO parent_faculty_queries (parent_id, query_text, created_at) 
        VALUES (?, ?, NOW())
    ");

    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare statement'];
    }

    $stmt->bind_param("is", $parent_id, $query_text);
    $stmt->execute();
    $stmt->close();

    return ['status' => true];
}

function GetParentQueriesService($parent_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM parent_faculty_queries WHERE parent_id = ? ORDER BY created_at DESC");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare statement'];
    }

    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $queries = [];
    while ($row = $result->fetch_assoc()) {
        $queries[] = $row;
    }

    $stmt->close();
    return ['status' => true, 'data' => $queries];
}


?>