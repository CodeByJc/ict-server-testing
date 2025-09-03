<?php

require_once __DIR__ . '/../db/db_connection_temp.php';

function UpdatePasswordService($username, $currentPass, $newPass) {
    global $conn; // Use global DB connection

    // Fetch the hashed password from the database
    $query = "SELECT password FROM user_login WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404); // Not Found
        return ["status" => false, "message" => "Username not found"];
    }

    $row = $result->fetch_assoc();
    $hashedPassword = $row['password'];
    $currentHashedPassword = password_hash($currentPass,PASSWORD_DEFAULT);
    
    // Verify the current password (plain text) against the stored hash
    if (!password_verify($currentPass, $hashedPassword)) {
        http_response_code(401); // Unauthorized
        return ["status" => false, "message" => "Current password is incorrect"];
    }

    // Hash the new password
    $newHashedPassword = password_hash($newPass, PASSWORD_DEFAULT);

    // Update the password in the database
    $updateQuery = "UPDATE user_login SET password = ? WHERE username = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $newHashedPassword, $username);

    if ($updateStmt->execute()) {
        http_response_code(200); // OK
        return ["status" => true, "message" => "Password updated successfully"];
    } else {
        http_response_code(500); // Internal Server Error
        return ["status" => false, "message" => "Failed to update password"];
    }
}
?>