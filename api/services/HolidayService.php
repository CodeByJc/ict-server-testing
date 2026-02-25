<?php

require_once __DIR__ . '/../db/db_connection.php';

// Add Holiday
function AddHolidayService($holidayName, $holidayDate) {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL AddHoliday(?, ?)");
        $stmt->bind_param("ss", $holidayName, $holidayDate);
        $stmt->execute();
        return ['status' => true];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

// Edit Holiday
function EditHolidayService($holidayID, $holidayName, $holidayDate) {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL EditHoliday(?, ?, ?)");
        $stmt->bind_param("iss", $holidayID, $holidayName, $holidayDate);
        $stmt->execute();
        return ['status' => true];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

// Delete Holiday
function DeleteHolidayService($holidayID) {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL DeleteHoliday(?)");
        $stmt->bind_param("i", $holidayID);
        $stmt->execute();
        return ['status' => true];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

// Get All Holidays
function GetAllHolidaysService() {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL GetAllHolidays()");
        $stmt->execute();
        $result = $stmt->get_result();
        $holidays = [];
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row;
        }
        return ['status' => true, 'data' => $holidays];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

// Get Next Upcoming Holiday
function GetNextUpcomingHolidayService() {
    global $conn;
    try {
        $stmt = $conn->prepare("CALL GetNextUpcomingHoliday()");
        $stmt->execute();
        $result = $stmt->get_result();
        $holiday = $result->fetch_assoc(); // Only one holiday will be retrieved

        if ($holiday) {
            return ['status' => true, 'data' => $holiday];
        } else {
            return ['status' => false, 'message' => 'No upcoming holiday found'];
        }
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}
