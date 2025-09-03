<?php

require_once __DIR__ . '/../db/db_connection.php';

function TotalAttendanceService($studentId) {
    global $conn;

    $stmt = $conn->prepare("CALL TotalAttendance(?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }

    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();

    $attendanceData = [];
    while ($row = $result->fetch_assoc()) {
        $attendanceData[] = $row;
    }

    $stmt->close();
    $conn->close();

    if (count($attendanceData) > 0) {
        return ['status' => true, 'data' => $attendanceData];
    }

    return ['status' => false, 'message' => 'No attendance records'];
}
function AttendanceByDateService($studentId, $date) {
    global $conn;

    $stmt = $conn->prepare("CALL AttendanceByDate(?, ?)");
    if (!$stmt) {
        return ['status' => false, 'message' => 'Failed to prepare the stored procedure'];
    }

    $stmt->bind_param("is", $studentId, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $attendanceData = [];
    while ($row = $result->fetch_assoc()) {
        $attendanceData[] = $row;
    }

    $stmt->close();
    $conn->close();

    if (count($attendanceData) > 0) {
        return ['status' => true, 'data' => $attendanceData];
    }

    return ['status' => false, 'message' => 'No attendance records'];
}

function getAttendanceList($subjectId, $facultyId, $classId, $cDate, $sTime) {
    global $conn; // Use global DB connection

    // Prepare the stored procedure to get attendance list
    $stmt = $conn->prepare("CALL GetAttendanceStudentsList(?,?,?,?,?)");

    if ($stmt) {
        $stmt->bind_param("iiiss", $subjectId, $classId, $facultyId, $cDate, $sTime); 
        $stmt->execute();
        $result = $stmt->get_result();
        $attendanceData = [];

        while ($row = $result->fetch_assoc()) {
            $attendanceData[] = $row;
        }

        $stmt->close();

        if (count($attendanceData) > 0) {
            return $attendanceData; // Return the attendance data
        } else {
            return ['message' => 'No students in this class']; // No data found
        }
    } else {
        return ['message' => 'Failed to prepare the stored procedure']; // Error preparing statement
    }
}


function uploadAttendance($attendanceEntries) {
    global $conn; // Use global DB connection

    $validStatuses = ['pr', 'ab', 'oe', 'gl'];
    $uploadResults = [];

    // Loop through each entry and insert attendance
    foreach ($attendanceEntries as $entry) {
        if (isset($entry['student_info_id'], $entry['subject_info_id'], $entry['faculty_info_id'], $entry['class_start_time'], 
                  $entry['class_end_time'], $entry['date'], $entry['status'], $entry['lec_type'])) {

            $studentId = $entry['student_info_id'];
            $subjectId = $entry['subject_info_id'];
            $facultyId = $entry['faculty_info_id'];
            $class_start_time = $entry['class_start_time'];
            $class_end_time = $entry['class_end_time'];
            $date = $entry['date'];
            $status = $entry['status'];
            $lec_type = $entry['lec_type'];

            // Check if status is valid
            if (!in_array($status, $validStatuses)) {
                $uploadResults[] = ['message' => "Invalid status value for student ID: $studentId"];
                continue;
            }

            // Prepare the stored procedure for uploading attendance
            $stmt = $conn->prepare("CALL UploadAttendance(?,?,?,?,?,?,?,?)");

            if ($stmt) {
                $stmt->bind_param("iiisssss", $subjectId, $facultyId, $studentId, $date, $status, $class_start_time, $class_end_time, $lec_type);
                $stmt->execute();

                // Check if the insert was successful
                if ($stmt->affected_rows > 0) {
                    $uploadResults[] = ['message' => "Attendance uploaded successfully for student ID: $studentId"];
                } else {
                    $uploadResults[] = ['message' => "Failed to upload attendance for student ID: $studentId"];
                }

                $stmt->close();
            } else {
                $uploadResults[] = ['message' => 'Failed to prepare the insert statement'];
            }
        } else {
            $uploadResults[] = ['message' => 'Missing required fields for an entry'];
        }
    }

    return $uploadResults;
}

function getFacultyScheduleByDate($FacultyId, $Date) {
    global $conn;
    // Prepare the statement to call the stored procedure
    $stmt = $conn->prepare("CALL GetFacultyScheduleByDate(?, ?)");

    if ($stmt) {
        $stmt->bind_param("is", $FacultyId, $Date);
        $stmt->execute();
        $result = $stmt->get_result();

        $schedule_data = [];
        
        while ($row = $result->fetch_assoc()) {
            $schedule_data[] = $row;
        }

        $stmt->close();

        return $schedule_data; // Return the fetched schedule data
    } else {
        return []; // Return an empty array if the query fails
    }
}

function getEngagedStudentsByCC($FacultyId) {
    global $conn;
    // Prepare the statement to call the stored procedure
    $stmt = $conn->prepare("CALL GetEngagedStudentByCC(?)");

    if ($stmt) {
        $stmt->bind_param("i", $FacultyId);
        $stmt->execute();
        $result = $stmt->get_result();

        $students_data = [];
        
        while ($row = $result->fetch_assoc()) {
            $students_data[] = $row;
        }

        $stmt->close();

        return $students_data; // Return the list of engaged students
    } else {
        return []; // Return an empty array if the query fails
    }
}

function upsertEngagedStudent($input) {

    global $conn;

    // Sanitize input data
    $student_info_id = $conn->real_escape_string($input['student_info_id']);
    $reason = $conn->real_escape_string($input['reason']);
    $type = $conn->real_escape_string($input['type']);  // Assumes 'oe' or 'gl'
    $faculty_info_id = $conn->real_escape_string($input['faculty_info_id']);
    $start_date = $conn->real_escape_string($input['start_date']);
    $end_date = $conn->real_escape_string($input['end_date']);

    // Prepare the stored procedure call
    $stmt = $conn->prepare("CALL UpsertEngagedStudent(?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        // Bind parameters to the statement
        $stmt->bind_param("ississ", $student_info_id, $reason, $type, $faculty_info_id, $start_date, $end_date);
        
        // Execute the statement
        if ($stmt->execute()) {
            $stmt->close();
            return ['message' => 'Engaged student info inserted/updated successfully'];
        } else {
            // Handle execution failure
            $stmt->close();
            return ['message' => 'Failed to execute stored procedure', 'error' => $stmt->error];
        }
    } else {
        // Handle preparation failure
        return ['message' => 'Failed to prepare the stored procedure'];
    }
}

function getStudentsByCC($FacultyId) {
    global $conn;
    $stmt = $conn->prepare("CALL GetStudentByCC(?)");

    if ($stmt) {
        $stmt->bind_param("i", $FacultyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $students_data = [];

        while ($row = $result->fetch_assoc()) {
            $students_data[] = $row;
        }

        $stmt->close();

        if (count($students_data) > 0) {
            return $students_data; // Return data as an array
        } else {
            return ['message' => 'No students'];
        }
    } else {
        return ['message' => 'Failed to prepare the stored procedure'];
    }
}

function getExtraSchedule($FacultyId) {
    global $conn;
    $stmt = $conn->prepare("CALL GetExtraSchedule(?)");

    if ($stmt) {
        $stmt->bind_param("i", $FacultyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule_data = [];

        while ($row = $result->fetch_assoc()) {
            $schedule_data[] = $row;
        }

        $stmt->close();

        if (count($schedule_data) > 0) {
            return $schedule_data; // Return data as an array
        } else {
            return ['message' => 'No schedule records'];
        }
    } else {
        return ['message' => 'Failed to prepare the stored procedure'];
    }
}


function getExtraAttendanceStudentsList($SubjectId, $FacultyId, $CDate) {
    global $conn;

    $stmt = $conn->prepare("CALL GetExtraAttendanceStudentsList(?,?,?)");

    if ($stmt) {
        $stmt->bind_param("iis", $SubjectId, $FacultyId, $CDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_data = [];

        while ($row = $result->fetch_assoc()) {
            $attendance_data[] = $row;
        }

        $stmt->close();

        if (count($attendance_data) > 0) {
            return $attendance_data; // Return data as an array
        } else {
            return ['message' => 'No students found'];
        }
    } else {
        return ['message' => 'Failed to prepare the stored procedure'];
    }
}

function uploadExtraAttendance($attendanceData) {
    global $conn;
    $response = [];

    foreach ($attendanceData as $entry) {
        if (
            isset($entry['student_info_id']) &&
            isset($entry['subject_info_id']) &&
            isset($entry['faculty_info_id']) &&
            isset($entry['date']) &&
            isset($entry['count'])
        ) {
            $studentId = $entry['student_info_id'];
            $subjectId = $entry['subject_info_id'];
            $facultyId = $entry['faculty_info_id'];
            $date = $entry['date'];
            $count = $entry['count'];

            $stmt = $conn->prepare("CALL UploadExtraAttendance(?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiisi", $subjectId, $facultyId, $studentId, $date, $count);

                try {
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $response[] = ['message' => "Attendance uploaded successfully for student ID: $studentId"];
                    } else {
                        $response[] = ['message' => "Failed to upload attendance for student ID: $studentId"];
                    }
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() == 1062) { 
                        $response[] = ['message' => "Duplicate entry detected for student ID: $studentId. Skipping..."];
                    } else {
                        $response[] = ['message' => 'An error occurred: ' . $e->getMessage()];
                    }
                }

                $stmt->close();
            } else {
                $response[] = ['message' => 'Failed to prepare the insert statement for student ID: ' . $studentId];
            }
        } else {
            $response[] = ['message' => 'Missing required fields for an entry'];
        }
    }

    return $response;
}



// For faculty

function isHolidayOrSunday($date) {
    global $conn;
    try {
        // Check if it's Sunday
        $dayOfWeek = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
        if ($dayOfWeek == 0) {
            return true;
        }

        // Check if it's a holiday
        $stmt = $conn->prepare("SELECT id FROM holiday_info WHERE holiday_date = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    } catch (Exception $e) {
        return false; // Default to false if there's an error
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function    FetchAttendanceInfoService($facultyId, $date) {
    global $conn;
    try {
        if (isHolidayOrSunday($date)) {
            return [
                'status' => false,
                'message' => 'Today is a holiday or Sunday',
                'data' => null
            ];
        }

        $stmt = $conn->prepare("SELECT * FROM faculty_attendance_info WHERE faculty_info_id = ? AND date = ?");
        $stmt->bind_param("is", $facultyId, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_assoc();
        return [
            'status' => true,
            'data' => $attendance ? $attendance : null,
            'message' => $attendance ? 'Record found' : 'No record found for this date'
        ];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function PunchInService($facultyId,$date,$punchInTime) {
    global $conn;
    try {
        if (isHolidayOrSunday($date)) {
            return [
                'status' => false,
                'message' => 'Cannot punch in on a holiday or Sunday'
            ];
        }

        $checkStmt = $conn->prepare("SELECT id FROM faculty_attendance_info WHERE faculty_info_id = ? AND date = ?");
        $checkStmt->bind_param("is", $facultyId, $date);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            return ['status' => false, 'message' => 'Attendance already recorded for today'];
        }
        
        $stmt = $conn->prepare("INSERT INTO faculty_attendance_info (punch_in, date, faculty_info_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $punchInTime, $date, $facultyId);
        $stmt->execute();
        
        return ['status' => true, 'message' => 'Punch in recorded successfully'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
        if (isset($checkStmt)) $checkStmt->close();
    }
}

function PunchOutService($facultyId, $date, $punchOutTime) {
    global $conn;
    try {
        if (isHolidayOrSunday($date)) {
            return [
                'status' => false,
                'message' => 'Cannot punch out on a holiday or Sunday'
            ];
        }

        // Adjust punch out time if after 6:30 PM
        $punchOutDateTime = DateTime::createFromFormat('H:i:s', $punchOutTime);
        $sixThirtyPM = DateTime::createFromFormat('H:i:s', '18:30:00');
        if ($punchOutDateTime > $sixThirtyPM) {
            $punchOutTime = '18:30:00';
        }

        $stmt = $conn->prepare("UPDATE faculty_attendance_info SET punch_out = ? WHERE faculty_info_id = ? AND date = ? AND punch_out IS NULL");
        $stmt->bind_param("sis", $punchOutTime, $facultyId, $date);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            return ['status' => true, 'message' => 'Punch out recorded successfully'];
        }
        return ['status' => false, 'message' => 'No punch in record found or already punched out'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function GetAttendanceHistoryService($facultyId) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM faculty_attendance_info WHERE faculty_info_id = ? ORDER BY date DESC");
        $stmt->bind_param("i", $facultyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        return [
            'status' => true,
            'data' => $history,
            'message' => count($history) > 0 ? 'History retrieved successfully' : 'No attendance history found'
        ];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

?>