<?php
require_once __DIR__ . '/../db/db_connection.php';

function addFeedbackService($review, $faculty_info_id, $student_info_id,$sem_info_id) {
    global $conn;

    try {
        if (empty($review) || empty($faculty_info_id) || empty($student_info_id) || empty($sem_info_id)) {
            return ['status' => false, 'message' => 'Review, faculty_info_id, and student_info_id are required'];
        }

        $stmt = $conn->prepare("INSERT INTO anonymous_review (review, faculty_info_id, student_info_id,sem_info_id) VALUES (?, ?, ?,?)");
        $stmt->bind_param("siii", $review, $faculty_info_id, $student_info_id,$sem_info_id);

        if ($stmt->execute()) {
            error_log("Feedback added successfully");
            return ['status' => true, 'message' => 'Feedback added successfully'];
        } else {
            error_log("Failed to add feedback: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to add feedback'];
        }
    } catch (Exception $e) {
        error_log("Exception in addFeedbackService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function getFeedbackByStudentService($student_id) {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT concat(fi.first_name,' ',fi.last_name) as faculty_name,ai.* FROM anonymous_review ai JOIN faculty_info fi ON ai.faculty_info_id=fi.id WHERE student_info_id = ? ORDER BY date DESC");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $feedbacks = [];

        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }

        return [
            'status' => true,
            'data' => $feedbacks,
            'message' => count($feedbacks) > 0 ? 'Feedback retrieved successfully' : 'No feedback found'
        ];
    } catch (Exception $e) {
        error_log("Exception in getFeedbackByStudentService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function getFeedbackByFacultyService($faculty_id) {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT ar.*,si.sem,si.edu_type FROM anonymous_review ar JOIN sem_info si ON ar.sem_info_id=si.id WHERE faculty_info_id = ? ORDER BY viewed ASC,date DESC");
        $stmt->bind_param("i", $faculty_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $feedbacks = [];

        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
        }

        return [
            'status' => true,
            'data' => $feedbacks,
            'message' => count($feedbacks) > 0 ? 'Feedback retrieved successfully' : 'No feedback found'
        ];
    } catch (Exception $e) {
        error_log("Exception in getFeedbackByFacultyService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function updateFeedbackViewedService($feedback_id) {
    global $conn;

    try {
        $stmt = $conn->prepare("UPDATE anonymous_review SET viewed = TRUE WHERE id = ?");
        $stmt->bind_param("i", $feedback_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return ['status' => true, 'message' => 'Feedback marked as viewed'];
            } else {
                return ['status' => false, 'message' => 'Feedback not found or already viewed'];
            }
        } else {
            error_log("Failed to update feedback viewed status: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to update feedback'];
        }
    } catch (Exception $e) {
        error_log("Exception in updateFeedbackViewedService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}