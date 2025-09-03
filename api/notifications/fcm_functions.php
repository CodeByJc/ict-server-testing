<?php
require_once __DIR__ . '/../db/db_connection.php';
require __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

function sendFCMNotification($username, $title, $body) {
    global $conn;

    // Fetch device_token from user_login table
    $stmt = $conn->prepare("SELECT device_token FROM user_login WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($deviceToken);
    $stmt->fetch();
    $stmt->close();

    // If no device_token found, return success without sending notification
    if (!$deviceToken) {
        return json_encode(['success' => true, 'message' => 'No device token found, notification skipped']);
    }

    $serviceAccountPath = __DIR__ . '/firebase_credentials.json';

    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $messaging = $factory->createMessaging();

    $notification = Notification::create($title, $body);
    $message = CloudMessage::withTarget('token', $deviceToken)
        ->withNotification($notification)
        ->withData(['extra_data' => 'Some Extra Data']);

    try {
        $response = $messaging->send($message);
        return json_encode(['success' => true, 'response' => $response]);
    } catch (\Exception $e) {
        return json_encode(['success' => false, 'message' => 'Notification not sent', 'error' => $e->getMessage()]);
    }
}

function sendZoomLinkNotification($sem_info_id, $title, $body) {
    global $conn;

    // Fetch all device_tokens from user_login table
    $stmt = $conn->prepare("SELECT ul.device_token  
        FROM user_login ul  
        JOIN parents_info pi ON ul.username = pi.user_login_id  
        JOIN student_info si ON pi.user_login_id = si.gr_no  
        WHERE si.sem_info_id = ?  
        AND ul.device_token IS NOT NULL  
        AND ul.device_token <> ''");
    $stmt->bind_param("i", $sem_info_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $deviceTokens = [];
    while ($row = $result->fetch_assoc()) {
        $deviceTokens[] = $row['device_token'];
    }
    $stmt->close();

    // If no device tokens found, return success without sending notification
    if (empty($deviceTokens)) {
        return json_encode(['success' => FALSE, 'message' => 'No device tokens found, notification skipped']);
    }

    $serviceAccountPath = __DIR__ . '/firebase_credentials.json';

    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $messaging = $factory->createMessaging();

    $notification = Notification::create($title, $body);
    
    $messages = [];
    foreach ($deviceTokens as $token) {
        $messages[] = CloudMessage::withTarget('token', $token)
            ->withNotification($notification)
            ->withData(['extra_data' => 'Some Extra Data']);
    }

    try {
        $response = $messaging->sendAll($messages);
        return json_encode(['success' => true, 'response' => $response]);
    } catch (\Exception $e) {
        return json_encode(['success' => false, 'message' => 'Notification not sent', 'error' => $e->getMessage()]);
    }
}



?>
