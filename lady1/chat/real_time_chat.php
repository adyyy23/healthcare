<?php
session_start();
require_once '../config/database.php';
require_once '../config/twilio.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Generate Twilio token
try {
    $token = generateTwilioToken($user['id']);
} catch (Exception $e) {
    error_log("Error generating Twilio token: " . $e->getMessage());
    $token = null;
}

// Get conversation partner information if provided
$partner_id = $_GET['partner_id'] ?? null;
$partner = null;
if ($partner_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$partner_id]);
    $partner = $stmt->fetch();
}

// Function to save message to database
function saveMessageToDatabase($sender_id, $receiver_id, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$sender_id, $receiver_id, $message]);
    } catch (Exception $e) {
        error_log("Error saving message to database: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Chat - HealthCare Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://sdk.twilio.com/js/conversations/releases/1.0.0/twilio-conversations.min.js"></script>
    <style>
        .chat-container {
            height: calc(100vh - 200px);
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .message {
            margin-bottom: 1rem;
            max-width: 70%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 0.75rem;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .message.sent .message-content {
            background: #4e73df;
            color: #fff;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .message-input {
            display: flex;
            gap: 0.5rem;
        }
        .message-input input {
            flex: 1;
        }
        .error-message {
            color: #dc3545;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="chat-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>
                    <?php if ($partner): ?>
                        Chat with <?php echo htmlspecialchars($partner['name']); ?>
                    <?php else: ?>
                        Select a conversation
                    <?php endif; ?>
                </h4>
                <a href="../<?php echo $_SESSION['role']; ?>/messages.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Messages
                </a>
            </div>
            
            <?php if (!$token): ?>
            <div class="error-message">
                Unable to initialize real-time chat. Please try again later.
            </div>
            <?php endif; ?>
            
            <div id="chat-messages" class="chat-messages"></div>
            
            <?php if ($partner && $token): ?>
            <div class="message-input">
                <input type="text" id="message-input" class="form-control" placeholder="Type your message...">
                <button onclick="sendMessage()" class="btn btn-primary">
                    <i class="bi bi-send"></i>
                </button>
            </div>
            <?php endif; ?>
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
                client = await Twilio.Conversations.Client.create('<?php echo $token; ?>');
                console.log('Twilio client initialized');

                <?php if ($partner): ?>
                // Get or create conversation
                const channelName = "chat_<?php echo min($_SESSION['user_id'], $partner_id); ?>_<?php echo max($_SESSION['user_id'], $partner_id); ?>";
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
                    // Save received message to database
                    saveMessageToDatabase(message.author, '<?php echo $_SESSION['user_id']; ?>', message.body);
                });

                // Load previous messages
                const messages = await conversation.getMessages();
                messages.forEach(message => {
                    displayMessage(message);
                });
                <?php endif; ?>

            } catch (error) {
                console.error('Error initializing chat:', error);
                showError('Failed to initialize chat. Please try again later.');
            }
        }

        // Display a message in the chat
        function displayMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.author === '<?php echo $_SESSION['user_id']; ?>' ? 'sent' : 'received'}`;
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.textContent = message.body;
            
            const timeDiv = document.createElement('div');
            timeDiv.className = 'message-time';
            timeDiv.textContent = new Date(message.dateCreated).toLocaleTimeString();
            
            messageDiv.appendChild(contentDiv);
            messageDiv.appendChild(timeDiv);
            messagesContainer.appendChild(messageDiv);
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Function to send a message
        async function sendMessage() {
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();
            
            if (message) {
                try {
                    // Save message to database
                    const response = await fetch('save_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            sender_id: <?php echo $user['id']; ?>,
                            receiver_id: <?php echo $partner['id']; ?>,
                            message: message
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Send message through Twilio
                        await conversation.sendMessage(message);
                        messageInput.value = '';
                    } else {
                        throw new Error(result.error || 'Failed to save message');
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    alert('Failed to send message. Please try again.');
                }
            }
        }

        // Show error message
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            messagesContainer.insertBefore(errorDiv, messagesContainer.firstChild);
            
            // Remove error message after 5 seconds
            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }

        // Handle enter key
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Initialize chat when page loads
        if ('<?php echo $token; ?>') {
            initializeChat();
        }
    </script>
</body>
</html> 