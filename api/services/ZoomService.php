<?php

require_once __DIR__ . '/../db/db_connection.php';

function AddZoomLinkService($zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL AddZoomLink(?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId);
        $stmt->execute();

        return ['status' => true, 'message' => 'Zoom link added successfully'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function EditZoomLinkService($id, $zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL EditZoomLink(?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssii", $id, $zoomLink, $zoomDate, $zoomTime, $zoomTitle, $semInfoId, $facultyInfoId);
        $stmt->execute();

        return ['status' => true, 'message' => 'Zoom link updated successfully'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function DeleteZoomLinkService($id) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL DeleteZoomLink(?)");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return ['status' => true, 'message' => 'Zoom link deleted successfully'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

function GetUpcomingZoomLinksService($id) {
    global $conn;

    try {
        $stmt = $conn->prepare("CALL GetUpcomingZoomLinks(?)");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        $links = [];
        while ($row = $result->fetch_assoc()) {
            $links[] = $row;
        }

        return ['status' => true, 'data' => $links];
    } catch (Exception $e) {
        return ['status' => false, 'message' => $e->getMessage()];
    }
}
?>