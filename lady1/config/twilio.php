<?php
// Twilio Configuration
define('TWILIO_ACCOUNT_SID', 'YOUR_ACCOUNT_SID');
define('TWILIO_AUTH_TOKEN', 'YOUR_AUTH_TOKEN');
define('TWILIO_CONVERSATIONS_SERVICE_SID', 'YOUR_CONVERSATIONS_SERVICE_SID');

// Twilio Conversations API endpoint
define('TWILIO_CONVERSATIONS_API_URL', 'https://conversations.twilio.com/v1');

// Function to generate access token for Twilio Conversations
function generateTwilioToken($identity) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $accessToken = new Twilio\Jwt\AccessToken(
        TWILIO_ACCOUNT_SID,
        TWILIO_AUTH_TOKEN,
        TWILIO_CONVERSATIONS_SERVICE_SID,
        $identity
    );
    
    $grant = new Twilio\Jwt\Grants\ConversationsGrant();
    $grant->setServiceSid(TWILIO_CONVERSATIONS_SERVICE_SID);
    
    $accessToken->addGrant($grant);
    
    return $accessToken->toJWT();
}

// Function to create or get conversation
function getOrCreateConversation($client, $uniqueName) {
    try {
        $conversation = $client->conversations->conversations($uniqueName)->fetch();
    } catch (Exception $e) {
        $conversation = $client->conversations->conversations->create([
            'uniqueName' => $uniqueName
        ]);
    }
    return $conversation;
}

// Function to add participant to conversation
function addParticipantToConversation($conversation, $identity) {
    try {
        $participant = $conversation->participants->create([
            'identity' => $identity
        ]);
        return $participant;
    } catch (Exception $e) {
        // Participant might already exist
        return null;
    }
}

// Initialize Twilio Client
require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

function getTwilioClient() {
    return new Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
}

function createTwilioChatUser($identity, $friendlyName) {
    $client = getTwilioClient();
    try {
        $user = $client->chat->v2->services(TWILIO_CHAT_SERVICE_SID)
            ->users
            ->create($identity, [
                'friendlyName' => $friendlyName
            ]);
        return $user;
    } catch (Exception $e) {
        error_log("Error creating Twilio chat user: " . $e->getMessage());
        return null;
    }
}
?> 