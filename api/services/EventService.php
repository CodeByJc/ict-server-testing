<?php
require_once __DIR__ . '/../db/db_connection.php';

function createEventService($title, $details, $datetime = null) {
    global $conn;

    try {
        if (empty($title) || empty($details)) {
            return ['status' => false, 'message' => 'Title and details are required'];
        }

        $datetime = $datetime ?: date('Y-m-d H:i:s');

        error_log("Inserting Event: title=$title, datetime=$datetime");

        $stmt = $conn->prepare("INSERT INTO events_info (title, details, datetime) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $details, $datetime);

        if ($stmt->execute()) {
            error_log("Event created successfully");
            return ['status' => true, 'message' => 'Event created successfully'];
        } else {
            error_log("Failed to create event: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to create event'];
        }

    } catch (Exception $e) {
        error_log("Exception in createEventService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function getAllEventsService() {
    global $conn;

    try {
        $result = $conn->query("SELECT * FROM events_info ORDER BY datetime");
        $events = [];

        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }

        return [
            'status' => true,
            'data' => $events,
            'message' => count($events) > 0 ? 'Events retrieved successfully' : 'No events found'
        ];
    } catch (Exception $e) {
        error_log("Exception in getAllEventsService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function getEventByIdService($id) {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM events_info WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if ($event) {
            return ['status' => true, 'data' => $event];
        } else {
            return ['status' => false, 'message' => 'Event not found'];
        }
    } catch (Exception $e) {
        error_log("Exception in getEventByIdService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function updateEventService($id, $title, $details, $datetime = null) {
    global $conn;

    try {
        if (empty($id) || empty($title) || empty($details)) {
            return ['status' => false, 'message' => 'ID, title and details are required'];
        }

        $datetime = $datetime ?: date('Y-m-d H:i:s');

        $stmt = $conn->prepare("UPDATE events_info SET title = ?, details = ?, datetime = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $details, $datetime, $id);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Event updated successfully'];
        } else {
            error_log("Failed to update event: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to update event'];
        }
    } catch (Exception $e) {
        error_log("Exception in updateEventService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}

function deleteEventService($id) {
    global $conn;

    try {
        $stmt = $conn->prepare("DELETE FROM events_info WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Event deleted successfully'];
        } else {
            error_log("Failed to delete event: " . $stmt->error);
            return ['status' => false, 'message' => 'Failed to delete event'];
        }
    } catch (Exception $e) {
        error_log("Exception in deleteEventService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
function getUpcomingEventService() {
    global $conn;

    try {
        $stmt = $conn->prepare("SELECT * FROM events_info WHERE datetime > NOW() ORDER BY datetime ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if ($event) {
            return ['status' => true, 'data' => $event, 'message' => 'Upcoming event retrieved'];
        } else {
            return ['status' => false, 'message' => 'No upcoming event found'];
        }
    } catch (Exception $e) {
        error_log("Exception in getUpcomingEventService: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error: ' . $e->getMessage()];
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
