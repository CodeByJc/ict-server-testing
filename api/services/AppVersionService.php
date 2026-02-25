<?php

require_once __DIR__ . '/../db/db_connection.php';

function AppVersionService($login, $code) {
    global $conn; // Use global DB connection

    // Prepare SQL query to check if login and code match
    $query = "SELECT COUNT(*) as count FROM version_info WHERE login = ? AND code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $login, $code); // Bind parameters to the query
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        $row = $result->fetch_assoc();
        $stmt->close();

        // Check if the login and code match
        if ($row['count'] > 0) {
            http_response_code(200); // OK
            return ['status' => true, 'message' => 'Login and code match'];
        } else {
            http_response_code(426); // Upgrade Required
            return ['status' => false, 'message' => 'Your app version is outdated. Please update to continue.'];
        }
    } else {
        $stmt->close();
        http_response_code(500); // Internal Server Error
        return ['status' => false, 'message' => 'Query execution failed'];
    }
}

?>