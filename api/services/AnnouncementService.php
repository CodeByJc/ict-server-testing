<?php
require_once __DIR__ . '/../db/db_connection.php';

function createAnnouncementService($input) {
    global $conn;

    $faculty_id = $input['faculty_id'] ?? null;
    $title = $input['Announcement_title'] ?? null;
    $description = $input['announcement_description'] ?? null;
    $batch_id = $input['batch_id'] ?? null;
    $type_id = $input['Announcement_type_id'] ?? null;
    $date = $input['Announcement_date'] ?? date('Y-m-d');

    if (!$faculty_id || !$title || !$description || !$batch_id) {
        return ['status' => false, 'message' => 'Missing required fields'];
    }

    $stmt = $conn->prepare("
        INSERT INTO announcements_info 
        (faculty_id, Announcement_title, announcement_description, batch_id, Announcement_date, Announcement_type_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issisi", $faculty_id, $title, $description, $batch_id, $date, $type_id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement created successfully'];
    }

    return ['status' => false, 'message' => 'Failed to create announcement'];
}

function getAllAnnouncementService($batch_id = null, $faculty_id = null) {
    global $conn;
    $data = [];
    $query = "
        SELECT 
            a.Announcement_id,
            a.faculty_id,
            CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
            a.Announcement_title,
            a.Announcement_date,
            a.announcement_description,
            a.Announcement_type_id,
            a.batch_id
        FROM announcements_info a
        JOIN faculty_info f ON a.faculty_id = f.id
    ";
    $params = [];
    $types = "";
    $where = [];
    if ($batch_id !== null && $batch_id > 0) {
        $where[] = "a.batch_id = ?";
        $params[] = $batch_id;
        $types .= "i";
    }
    if ($faculty_id !== null && $faculty_id > 0) {
        $where[] = "a.faculty_id = ?";
        $params[] = $faculty_id;
        $types .= "i";
    }
    if ($where) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    $query .= " ORDER BY a.Announcement_date DESC";

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();
    return ['status' => true, 'data' => $data];
}


function getAnnouncementByIdService($faculty_id) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            a.Announcement_id,
            a.faculty_id,
            CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
            a.Announcement_title,
            a.Announcement_date,
            a.Announcement_type_id,
            a.batch_id,
            a.announcement_description
        FROM 
            announcements_info a
        JOIN 
            faculty_info f ON a.faculty_id = f.id
        WHERE 
            a.faculty_id = ?
    ");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        return ['status' => true, 'data' => $result];
    }

    return ['status' => false, 'message' => 'Announcement not found'];
}

function deleteAnnouncementService($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM announcements_info WHERE Announcement_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement deleted successfully'];
    }

    return ['status' => false, 'message' => 'Delete failed'];
}

function updateAnnouncementService($id, $input) {
    global $conn;

    $title = $input['Announcement_title'] ?? null;
    $description = $input['announcement_description'] ?? null;
    $batch_id = $input['batch_id'] ?? null;
    $type_id = $input['Announcement_type_id'] ?? null;
    $date = $input['Announcement_date'] ?? date('Y-m-d');

    if (!$title || !$description || !$batch_id) {
        return ['status' => false, 'message' => 'Missing required fields'];
    }

    $stmt = $conn->prepare("
        UPDATE announcements_info
        SET Announcement_title = ?, announcement_description = ?, batch_id = ?, Announcement_date = ?, Announcement_type_id = ? 
        WHERE Announcement_id = ?
    ");
    $stmt->bind_param("ssisii", $title, $description, $batch_id, $date, $type_id, $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement updated successfully'];
    }

    return ['status' => false, 'message' => 'Update failed'];
}

function getAnnouncementFieldsService() {
    global $conn;

    $stmt = null;
    $stmt2 = null;

    try {
        // ---------- Fetch Batches ----------
        $stmt = $conn->prepare("
            SELECT id, CONCAT(batch_start_year, ' - ', batch_end_year) AS batch_name
            FROM batch_info
            ORDER BY batch_name ASC
        ");
        if (!$stmt) {
            return ['status' => false, 'message' => 'Failed to prepare batches query: ' . $conn->error];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $batches = [];
        while ($row = $result->fetch_assoc()) {
            $batches[] = $row;
        }
        $stmt->close();
        $stmt = null;

        // ---------- Fetch Announcement Types ----------
        $stmt2 = $conn->prepare("SELECT * FROM `announcement_type_list` ORDER BY Announcement_type_id ASC;");
        if (!$stmt2) {
            return ['status' => false, 'message' => 'Failed to prepare announcement types query: ' . $conn->error];
        }

        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $announcement_types = [];
        while ($row = $result2->fetch_assoc()) {
            $announcement_types[] = $row;
        }
        $stmt2->close();
        $stmt2 = null;

        // ---------- Return Data ----------
        return [
            'status' => true,
            'data' => [
                'batches' => $batches,
                'announcement_types' => $announcement_types
            ]
        ];

    } catch (Exception $e) {
        error_log("Error in getAnnouncementFieldsService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Database error: ' . $e->getMessage()];
    } finally {
        // Ensure statements are closed if still open
        if ($stmt) $stmt->close();
        if ($stmt2) $stmt2->close();
    }
}
?>
