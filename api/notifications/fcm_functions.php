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


function sendBatchNotification($batch_info_id, $title, $body) {
    // 🔹 Manually list your device tokens here
    $deviceTokens = [
        'flUSZ76eTUC-vgukTcvyUD:APA91bGCk2HhG3_FTZShbIdBXZCCvm-3jk_y9N9WQjGDRC4Y2ksG7v00k01l6CriLQ8Cbfn4ZGvu7F6Zv7V7gBO2Gh6ffxbXjeJG-F01nbc6lik6JAAuFiI',
        'eOjw_8CtSH-tLOUvHqkN1R:APA91bGThee-XOc3gKK3GChhzNSJ2q121ZDWY-01ykRvDVuDuGXcwNjRhbbbRdpQYHD_3Y0euOc6gujR-Cf2xQKaVJme3ubcG7WthoWjKVnx-Fm6tXka1C8',
        'deTUz0jvTcuahzpzAmOpLY:APA91bHvVYfF-anYWNhdge1Ls9x4n8XhLodH8jL8UufaOOHrs-6ztz4pspMZoPNfvXxJND_dLnKuATp6KXC3ycNChHtvFP9EASGhSI3aPKCzHZ5Bm2D7feo',
        'cQwvDifZS96E4JYPzT9IQv:APA91bGcWthZsYBpjzXajyEg8xrmHUmRwmZOFyFGyijyjvMAylPwzkn63IekZYJHN2-Z9LBC7LDZNNCgE5ilIM7ArUnkrXUCLNDTpjLyQJIlxMEQ3MfWUhw',
        'cEimHkL4R_SZGtURGUb55d:APA91bFhMJoqJrzjXacJhvwjvD8ue3j4AtrDjTsmmtK0m-nNQVHwWFeYrCC1MXB0h1tHjH-evUKCFVfM6CDuTaa4-Dr2YsaRuHF3ifF5sBl27EXwUE65VxU'
    ];

    // 🔹 Basic validation
    if (empty($deviceTokens)) {
        return json_encode(['success' => false, 'message' => 'No device tokens found']);
    }

    // 🔹 Firebase setup
    $serviceAccountPath = __DIR__ . '/firebase_credentials.json';
    if (!file_exists($serviceAccountPath)) {
        return json_encode(['success' => false, 'message' => 'Firebase credentials missing']);
    }

    try {
        $factory = (new Factory)->withServiceAccount($serviceAccountPath);
        $messaging = $factory->createMessaging();

        // 🔹 Create the notification
        $notification = Notification::create('Batch Test', 'This is a manual test message for all batch users.');

        // 🔹 Build message objects
        $messages = [];
        foreach ($deviceTokens as $token) {
            $messages[] = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData(['type' => 'manual_test']);
        }

        // 🔹 Send all (in chunks of 500)
        $successCount = 0;
        $failureCount = 0;
        foreach (array_chunk($messages, 500) as $chunk) {
            $response = $messaging->sendAll($chunk);
            $successCount += $response->successes()->count();
            $failureCount += $response->failures()->count();
        }

        return json_encode([
            'success' => true,
            'message' => 'Manual batch notification test completed',
            'total' => count($deviceTokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);
    } catch (\Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Notification failed',
            'error' => $e->getMessage()
        ]);
    }
}

// function sendBatchNotification($batch_info_id, $title, $body) {
//     global $conn;

//     error_log("DEBUG: Starting sendBatchNotification for batch_info_id: $batch_info_id");

//     // Fetch tokens (not used, but kept for structure/logging)
//     $stmt = $conn->prepare("
//         SELECT ul.device_token
//         FROM user_login ul
//         JOIN student_info si ON ul.username = si.enrollment_no
//         WHERE si.batch_info_id = ?
//           AND ul.device_token IS NOT NULL
//           AND ul.device_token <> ''
//     ");
//     $stmt->bind_param("i", $batch_info_id);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $stmt->close();

//     // Hardcoded test device token (only this one will be used)
//     $deviceToken = "cQwvDifZS96E4JYPzT9IQv:APA91bGcWthZsYBpjzXajyEg8xrmHUmRwmZOFyFGyijyjvMAylPwzkn63IekZYJHN2-Z9LBC7LDZNNCgE5ilIM7ArUnkrXUCLNDTpjLyQJIlxMEQ3MfWUhw";

//     error_log("DEBUG: Sending test notification to single device token.");

//     // Initialize Firebase
//     $serviceAccountPath = __DIR__ . '/firebase_credentials.json';
//     $factory = (new Factory)->withServiceAccount($serviceAccountPath);
//     $messaging = $factory->createMessaging();

//     // Build the message
//     $notification = Notification::create($title, $body);
//     $message = CloudMessage::withTarget('token', $deviceToken)
//         ->withNotification($notification)
//         ->withData(['extra_data' => 'Some Extra Data']);

//     // Send message
//     try {
//         $response = $messaging->send($message);
//         error_log("DEBUG: Notification sent successfully.");
//         return json_encode(['success' => true, 'response' => $response]);
//     } catch (\Exception $e) {
//         error_log("ERROR: Notification failed: " . $e->getMessage());
//         return json_encode(['success' => false, 'message' => 'Notification not sent', 'error' => $e->getMessage()]);
//     }
// }


?>
