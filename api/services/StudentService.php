<?php
require_once __DIR__ . '/../db/db_connection.php';

/**
 * Logout Student Service
 */
function StudentLogoutService($username) {
    global $conn;

    $username = $conn->real_escape_string($username);
    $update_stmt = $conn->prepare("UPDATE user_login SET device_token = NULL WHERE username = ?");
    if (!$update_stmt) {
        return ['status' => false, 'message' => 'Failed to prepare update statement'];
    }

    $update_stmt->bind_param("s", $username);
    $update_stmt->execute();
    $update_stmt->close();

    return ['status' => true, 'message' => 'User logged out successfully'];
}


/**
 * Login Student Service
 */
function StudentLoginService($username, $password, $device_token) {
    global $conn;

    $username = $conn->real_escape_string($username);
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

    // Stored procedure call
    $stmt = $conn->prepare("CALL LoginStudent(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare stored procedure'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();

        $parent_details = json_decode($user_data['parent_details'], true);
        $student_details = json_decode($user_data['student_details'], true);
        $class_details = json_decode($user_data['class_details'], true);

        $full_details = [
            'parent_details' => $parent_details,
            'student_details' => $student_details,
            'class_details' => $class_details,
        ];

        $stmt->close();

        // Update device token
        $update_stmt = $conn->prepare("UPDATE user_login SET device_token = ? WHERE username = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ss", $device_token, $username);
            $update_stmt->execute();
            $update_stmt->close();
        }

        return ['status' => true, 'data' => $full_details];
    }

    $stmt->close();
    return ['status' => false, 'message' => 'Invalid username or password'];
}


/**
 * Search Student Info by Faculty
 */
function searchStudentByFaculty($enrolment) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetStudentInfo(?)");
        $stmt->bind_param("i", $enrolment);
        $stmt->execute();

        $result = $stmt->get_result();
        $studentData = $result->fetch_assoc();

        return [
            'status' => true,
            'data' => $studentData,
            'message' => 'Student data retrieved successfully'
        ];
    } catch (Exception $e) {
        error_log("Error in searchStudentByFaculty: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}


function GetBatchesByBranchService() {
    global $conn;

    try {
        // Secure query
        $stmt = $conn->prepare("SELECT id, CONCAT(batch_start_year	, ' - ', batch_end_year) AS batch_name FROM batch_info ORDER BY batch_name ASC");
        $stmt->execute();
        $result = $stmt->get_result();

        $batches = [];
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }

        if (empty($batches)) {
            return ['status' => false, 'message' => 'No batches found for this branch'];
        }

        return ['status' => true, 'data' => $batches];
    } catch (Exception $e) {
        error_log("Error in GetBatchesByBranchService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
?>
