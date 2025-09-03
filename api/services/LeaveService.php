<?php
require_once __DIR__ . '/../db/db_connection.php';

function leaveRequestService($studentId, $reason, $file) {
    global $conn;
    try {
        // Validate inputs
        if (empty($studentId) || empty($reason)) {
            return ['status' => false, 'message' => 'Student ID and reason are required'];
        }

        // Handle file upload
        $documentProof = null;
        if ($file && isset($file['name'])) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeType = $file['type'];
            $isValidFile = ($mimeType === 'application/pdf' || $mimeType === 'application/octet-stream') && $extension === 'pdf';

            if ($isValidFile) {
                $uploadDir = '/ict-server/web/faculty/leave_doc_proof/';
                $timestamp = date('YmdHi');
                $originalFilename = pathinfo($file['name'], PATHINFO_FILENAME);
                $newFilename = "{$timestamp}{$originalFilename}.{$extension}";
                $uploadPath = $uploadDir . $newFilename;
                $fullServerPath = $_SERVER['DOCUMENT_ROOT'] . $uploadPath;

                // Log file details
                error_log("File details: " . print_r($file, true));
                error_log("Target path: $fullServerPath");

                // Ensure directory exists and is writable
                $dirPath = dirname($fullServerPath);
                if (!file_exists($dirPath)) {
                    if (!mkdir($dirPath, 0777, true)) {
                        error_log("Failed to create directory: $dirPath");
                        return ['status' => false, 'message' => 'Failed to create upload directory'];
                    }
                }
                if (!is_writable($dirPath)) {
                    error_log("Directory not writable: $dirPath");
                    return ['status' => false, 'message' => 'Upload directory is not writable'];
                }

                // Move the uploaded file
                if (move_uploaded_file($file['tmp_name'], $fullServerPath)) {
                    $documentProof = $uploadPath;
                    error_log("File successfully moved to: $fullServerPath");
                } else {
                    error_log("Failed to move file from {$file['tmp_name']} to $fullServerPath");
                    return ['status' => false, 'message' => 'Failed to upload document'];
                }
            } else {
                error_log("Invalid file type or extension: MIME=$mimeType, Ext=$extension");
            }
        } else {
            error_log("No file provided: " . print_r($file, true));
        }

        // Insert into database
        error_log("Inserting into DB: studentId=$studentId, reason=$reason, documentProof=$documentProof");
        $stmt = $conn->prepare("INSERT INTO leave_info (student_info_id, reason, document_proof, leave_status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iss", $studentId, $reason, $documentProof);
        if ($stmt->execute()) {
            error_log("Database insert successful");
            return ['status' => true, 'message' => 'Leave request submitted successfully'];
        } else {
            error_log("Database insert failed: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to save leave request'];
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function getLeaveHistoryService($studentId) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT * FROM leave_info WHERE student_info_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];

        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }

        return [
            'status' => true,
            'data' => $history,
            'message' => count($history) > 0 ? 'Leave history retrieved successfully' : 'No leave history found'
        ];
    } catch (Exception $e) {
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
?>