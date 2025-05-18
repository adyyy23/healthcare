<?php
session_start();
require_once 'config/database.php';
require_once 'config/twilio.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate Twilio token for the current user
$token = generateTwilioToken($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <script src="https://sdk.twilio.com/js/conversations/releases/1.0.0/twilio-conversations.min.js"></script>
    <style>
        .chat-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .chat-messages {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .message {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
        }
        .message.sent {
            background-color: #e3f2fd;
            margin-left: 20%;
        }
        .message.received {
            background-color: #f5f5f5;
            margin-right: 20%;
        }
        .message-input {
            display: flex;
            gap: 10px;
        }
        .message-input input {
            flex: 1;
            padding: 8px;
        }
        .message-input button {
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .message-input button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <h2>Chat with Doctor</h2>
        <div id="chat-messages" class="chat-messages"></div>
        <div class="message-input">
            <input type="text" id="message-input" placeholder="Type your message...">
            <button onclick="sendMessage()">Send</button>
        </div>
    </div>

    <script>
        let client;
        let conversation;
        const messagesContainer = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');

        // Initialize Twilio Conversations client
        async function initializeChat() {
            try {
                client = await Twilio.Conversations.Client.create('<?php echo $token->token; ?>');
                console.log('Twilio client initialized');

                // Get or create conversation
                const channelName = "chat_<?php echo $_SESSION['user_id']; ?>_<?php echo $_GET['doctor_id']; ?>";
                try {
                    conversation = await client.getConversationByUniqueName(channelName);
                } catch (error) {
                    console.log('Creating new conversation');
                    conversation = await client.createConversation({
                        uniqueName: channelName
                    });
                }

                // Set up message listener
                conversation.on('messageAdded', message => {
                    displayMessage(message);
                });

                // Load previous messages
                const messages = await conversation.getMessages();
                messages.forEach(message => {
                    displayMessage(message);
                });

            } catch (error) {
                console.error('Error initializing chat:', error);
            }
        }

        function displayMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.author === '<?php echo $_SESSION['user_id']; ?>' ? 'sent' : 'received'}`;
            messageDiv.textContent = message.body;
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        async function sendMessage() {
            const messageText = messageInput.value.trim();
            if (!messageText) return;

            try {
                await conversation.sendMessage(messageText);
                messageInput.value = '';
            } catch (error) {
                console.error('Error sending message:', error);
            }
        }

        // Handle Enter key
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Initialize chat when page loads
        initializeChat();
    </script>
</body>
</html> 