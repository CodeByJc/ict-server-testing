<?php

require_once __DIR__ . '/../db/db_connection.php';

function StudentLogoutService($username) {
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


function StudentLoginService($username, $password, $device_token) {
    global $conn; // Use global DB connection

    // Sanitize input
    $username = $conn->real_escape_string($username);

    // Prepare the statement to get the hashed password
    $stmt = $conn->prepare("SELECT password FROM user_login WHERE username = ?");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the statement'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    // Check if a hashed password was found
    if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
        http_response_code(401); // Unauthorized
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Proceed to call the stored procedure
    $stmt = $conn->prepare("CALL LoginStudent(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Decode the JSON fields
        $parent_details = json_decode($user_data['parent_details'], true);
        $student_details = json_decode($user_data['student_details'], true);
        $class_details = json_decode($user_data['class_details'], true);

        $full_details = [
            'parent_details' => $parent_details,
            'student_details' => $student_details,
            'class_details' => $class_details,
        ];

        $stmt->close();

        // Store the device token in the user_login table
        $update_stmt = $conn->prepare("UPDATE user_login SET device_token = ? WHERE username = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $device_token, $username);
            $update_stmt->execute();
            $update_stmt->close();
        }
        return ['status' => true, 'data' => $full_details];
    }

    $stmt->close();
    http_response_code(401); // Unauthorized
    return ['status' => false, 'message' => 'Invalid username or password'];
}

function searchStudentByFaculty($enrollment) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetStudentInfo(?)");
        $stmt->bind_param("i", $enrollment);
        $stmt->execute();

        $result = $stmt->get_result();
        $studentData = $result->fetch_assoc();

        return [
            'status' => true,
            'data' => $studentData,
            'message' => 'Student Data retrieved successfully'];
    } catch (Exception $e) {
        error_log("Exception in getFeedbackByFacultyService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

?>