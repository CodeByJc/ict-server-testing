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
        INSERT INTO announcements 
        (faculty_id, Announcement_title, announcement_description, batch_id, Announcement_date, Announcement_type_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issisi", $faculty_id, $title, $description, $batch_id, $date, $type_id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement created successfully'];
    }

    return ['status' => false, 'message' => 'Failed to create announcement'];
}

function getAllAnnouncementService($batch_id) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            a.Announcement_id,
            a.faculty_id,
            CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
            a.Announcement_title,
            a.Announcement_date,
            a.announcement_description,
            a.Announcement_type_id,
            a.batch_id
        FROM 
            announcements a
        JOIN 
            faculty_info f ON a.faculty_id = f.id
        WHERE 
            a.batch_id = ?
        ORDER BY 
            a.Announcement_date DESC
    ");
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    return ['status' => true, 'data' => $data];
}

function getAnnouncementByIdService($id) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            a.Announcement_id,
            a.faculty_id,
            f.faculty_name,
            a.Announcement_title,
            a.Announcement_date,
            a.Announcement_type_id,
            a.batch_id,
            a.announcement_description
        FROM 
            announcements a
        JOIN 
            faculty_info f ON a.faculty_id = f.id
        WHERE 
            a.Announcement_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        return ['status' => true, 'data' => $result];
    }

    return ['status' => false, 'message' => 'Announcement not found'];
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
        UPDATE announcements 
        SET Announcement_title = ?, announcement_description = ?, batch_id = ?, Announcement_date = ?, Announcement_type_id = ? 
        WHERE Announcement_id = ?
    ");
    $stmt->bind_param("ssisii", $title, $description, $batch_id, $date, $type_id, $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement updated successfully'];
    }

    return ['status' => false, 'message' => 'Update failed'];
}

function deleteAnnouncementService($id) {
    global $conn;

    $stmt = $conn->prepare("DELETE FROM announcements WHERE Announcement_id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Announcement deleted successfully'];
    }

    return ['status' => false, 'message' => 'Delete failed'];
}
