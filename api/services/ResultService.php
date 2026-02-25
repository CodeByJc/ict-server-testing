<?php

require_once __DIR__ . '/../db/db_connection.php';

function getSemResultsByStudent($studentId, $sem_info_id) {
    global $conn;

    $studentId = (int)$studentId;
    $sem_info_id = (int)$sem_info_id;

    $sql = "CALL fetch_student_sem_results($studentId, $sem_info_id)";

    $semesters = [];
    $subjects = [];

    try {
        // Use multi_query so we can fetch multiple resultsets returned by the stored procedure
        if (!$conn->multi_query($sql)) {
            throw new Exception('Multi query failed: ' . $conn->error);
        }

        $resultsetIndex = 0;
        do {
            if ($result = $conn->store_result()) {
                $rows = [];
                while ($row = $result->fetch_assoc()) {
                    $rows[] = $row;
                }
                $result->free();

                if ($resultsetIndex === 0) {
                    $semesters = $rows;
                } elseif ($resultsetIndex === 1) {
                    $subjects = $rows;
                } else {
                    // ignore any further resultsets (not expected)
                }

                $resultsetIndex++;
            } else {
                // No resultset (could be an OK packet). Continue.
            }
            // advance to next resultset (if any)
        } while ($conn->more_results() && $conn->next_result());

        return ['status' => true, 'data' => ['semesters' => $semesters, 'subjects' => $subjects]];
    } catch (Exception $e) {
        // Make sure to clear any remaining results
        while ($conn->more_results() && $conn->next_result()) {
            if ($unusedResult = $conn->store_result()) {
                $unusedResult->free();
            }
        }
        return ['status' => false, 'message' => $e->getMessage()];
    }
}
?>