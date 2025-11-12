<?php

require_once __DIR__ . '/../db/db_connection.php';

function RecentlyPlacedService() {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL recentlyPlacedStudents()");
        $stmt->execute();
        $result = $stmt->get_result();
        $studentsList = [];
        while ($row = $result->fetch_assoc()) {
            $studentsList[] = $row;
        }

        return ['status' => true, 'data' => $studentsList];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function GetCompanyListService() {
    global $conn; // Assuming $conn is your database connection object

    try {
        $stmt = $conn->prepare("SELECT * FROM company_info order by company_name");
        $stmt->execute();
        $result = $stmt->get_result();
        $companyList = [];
        while ($row = $result->fetch_assoc()) {
            $companyList[] = $row;
        }
        return ['status' => true, 'data' => $companyList];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function GetCampusDriveStudentListService($studentId,$batchId) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetCampusDriveByStudent(?,?)");
        $stmt->bind_param("ii",$studentId,$batchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $campusDriveList = [];
        while ($row = $result->fetch_assoc()) {
            $campusDriveList[] = $row;
        }
        return ['status' => true, 'data' => $campusDriveList];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function StatusUpdateCampusDriveService($studentId, $driveId, $status) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL statusUpdateCampusDrive(?, ?, ?)");
        $stmt->bind_param("iis", $studentId, $driveId, $status);
        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Status updated successfully'];
        } else {
            return ['status' => false, 'message' => 'Failed to update status'];
        }
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function GetCampusDriveRoundsByStudentService($studentId,$batchId) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetCampusDriveRoundsByStudent(?,?)");
        $stmt->bind_param("ii",$studentId,$batchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $campusDriveRoundsList = [];
        while ($row = $result->fetch_assoc()) {
            $campusDriveRoundsList[] = $row;
        }
        return ['status' => true, 'data' => $campusDriveRoundsList];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function addOldDataService($enrollmentNo, $companyInfoId, $date, $data) {
    global $conn;
    try {
        // Step 1: Find student_info_id from enrollment_no
        $stmt = $conn->prepare("SELECT id FROM student_info WHERE enrollment_no = ?");
        $stmt->bind_param("s", $enrollmentNo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['status' => false, 'message' => 'Student not found for enrollment number: ' . $enrollmentNo];
        }

        $studentInfo = $result->fetch_assoc();
        $studentInfoId = $studentInfo['id'];
        $stmt->close();

        // Step 2: Insert into interview_bank
        $stmt = $conn->prepare("INSERT INTO interview_bank (student_info_id, company_info_id, date, data) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $studentInfoId, $companyInfoId, $date, $data);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Interview data added successfully'];
        } else {
            error_log("Database insert failed: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to save interview data'];
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
?>
