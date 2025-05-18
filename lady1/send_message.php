<?php
session_start();
require_once 'config/database.php';
require_once 'config/twilio.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['receiver_id']) || !isset($_POST['message'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    // Get sender and receiver information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->execute([$_POST['receiver_id']]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create or get Twilio chat channel
    $client = getTwilioClient();
    $channelName = "chat_" . min($_SESSION['user_id'], $_POST['receiver_id']) . "_" . max($_SESSION['user_id'], $_POST['receiver_id']);
    
    try {
        $channel = $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
            ->channels
            ->create([
                'uniqueName' => $channelName,
                'friendlyName' => "Chat between {$sender['name']} and {$receiver['name']}"
            ]);
    } catch (Exception $e) {
        // Channel might already exist, try to fetch it
        $channel = $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
            ->channels($channelName)
            ->fetch();
    }

    // Add users to channel if not already members
    try {
        $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
            ->channels($channelName)
            ->members
            ->create($_SESSION['user_id']);
    } catch (Exception $e) {
        // Member might already exist
    }

    try {
        $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
            ->channels($channelName)
            ->members
            ->create($_POST['receiver_id']);
    } catch (Exception $e) {
        // Member might already exist
    }

    // Send message through Twilio
    $message = $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
        ->channels($channelName)
        ->messages
        ->create([
            'body' => $_POST['message'],
            'from' => $_SESSION['user_id']
        ]);

    // Store message in local database
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, twilio_message_sid, is_read) 
        VALUES (?, ?, ?, ?, FALSE)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $_POST['receiver_id'],
        $_POST['message'],
        $message->sid
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message_sid' => $message->sid
    ]);
} catch(Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?> 