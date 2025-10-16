<?php

require_once __DIR__ . '/../db/db_connection.php';

function FacultyLoginService($username, $password) {
    global $conn; // Use global DB connection

    // Sanitize and validate input
    $username = $conn->real_escape_string($username);

    // Check if the username exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_login WHERE username = ?");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the statement for username check'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($usernameExists);
    $stmt->fetch();
    $stmt->close();

    // If username does not exist, return an appropriate message
    if ($usernameExists == 0) {
        return ['status' => false, 'message' => 'Username not found'];
    }

    // Proceed to query the hashed password for faculty
    $stmt = $conn->prepare("SELECT password FROM user_login WHERE username = ? AND role = 'faculty' AND isactive = 1");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the statement'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    // Check if a hashed password was found
    if (!$hashedPassword) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Verify the password against the hashed password
    if (!password_verify($password, $hashedPassword)) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }

    // Proceed to call the stored procedure
    $stmt = $conn->prepare("CALL LoginFaculty(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $conn->close();

    if ($result && $result->num_rows > 0) {
        $faculties_details = $result->fetch_assoc();

        return [
            'status' => true,
            'data' => [
                'faculties_details' => $faculties_details,
            ],
        ];
    }

    return ['status' => false, 'message' => 'Invalid username or password'];
}

function GetFacultyListByStudentService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetFacultyListByStudent(?)");
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

function GetMentorByStudentService($studentId) {
    global $conn; 

    $stmt = $conn->prepare("CALL GetStudentMentorDetailsById(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $mentor_data = $result->fetch_assoc();
        $stmt->close();
        return ['status' => true, 'data' => $mentor_data];
    } else {
        $stmt->close();
        return ['status' => false, 'message' => 'Mentor not found'];
    }
}

?>
