<?php
// Get unread notifications count
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE user_id = ? AND is_read = FALSE
");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();

// Get recent notifications
$stmt = $pdo->prepare("
    SELECT * 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<!-- Notifications Dropdown -->
<div class="dropdown">
    <button class="btn btn-link position-relative" type="button" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-bell text-gray-600" style="font-size: 1.25rem;"></i>
        <?php if ($unread_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?php echo $unread_count; ?>
        </span>
        <?php endif; ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="notificationsDropdown" style="width: 300px;">
        <h6 class="dropdown-header">Notifications</h6>
        <?php if (empty($notifications)): ?>
        <div class="dropdown-item text-center py-3">
            <i class="bi bi-bell-slash text-muted" style="font-size: 2rem;"></i>
            <p class="mb-0 mt-2 text-muted">No notifications</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
        <a class="dropdown-item d-flex align-items-center py-2 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>" 
           href="#">
            <div class="me-3">
                <?php if ($notification['type'] === 'appointment'): ?>
                <i class="bi bi-calendar-check text-primary"></i>
                <?php elseif ($notification['type'] === 'message'): ?>
                <i class="bi bi-chat-dots text-success"></i>
                <?php else: ?>
                <i class="bi bi-bell text-secondary"></i>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1">
                <p class="mb-0"><?php echo htmlspecialchars($notification['message']); ?></p>
                <small class="text-muted">
                    <?php echo date('M d, h:i A', strtotime($notification['created_at'])); ?>
                </small>
            </div>
            <div class="ms-2 d-flex flex-column align-items-end gap-1">
                <?php if (!$notification['is_read']): ?>
                    <button class="btn btn-sm btn-outline-success mark-read-btn" data-notification-id="<?php echo $notification['id']; ?>">Mark as Read</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary mark-unread-btn" data-notification-id="<?php echo $notification['id']; ?>">Mark as Unread</button>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-center" href="#" onclick="markAllNotificationsAsRead()">
            Mark all as read
        </a>
        <?php endif; ?>
    </div>
</div>

<script>
function markAllNotificationsAsRead() {
    fetch('mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to refresh the notification count from database
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Use event delegation for all button clicks
document.addEventListener('click', function(e) {
    // Handle Mark as Read button
    if (e.target.classList.contains('mark-read-btn')) {
        e.stopPropagation();
        const notificationId = e.target.dataset.notificationId;
        
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove bg-light from notification
                const notificationItem = e.target.closest('.dropdown-item');
                if (notificationItem) {
                    notificationItem.classList.remove('bg-light');
                }
                
                // Update button
                e.target.textContent = 'Mark as Unread';
                e.target.className = 'btn btn-sm btn-outline-secondary mark-unread-btn';
                e.target.removeAttribute('onclick');
                
                // Update badge count
                const badge = document.querySelector('#notificationsDropdown .badge');
                if (badge) {
                    let count = parseInt(badge.textContent) - 1;
                    if (count > 0) {
                        badge.textContent = count;
                    } else {
                        badge.remove();
                    }
                }
            }
        });
    }
    
    // Handle Mark as Unread button
    if (e.target.classList.contains('mark-unread-btn')) {
        e.stopPropagation();
        const notificationId = e.target.dataset.notificationId;
        
        fetch('mark_notification_unread.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add bg-light to notification
                const notificationItem = e.target.closest('.dropdown-item');
                if (notificationItem) {
                    notificationItem.classList.add('bg-light');
                }
                
                // Update button
                e.target.textContent = 'Mark as Read';
                e.target.className = 'btn btn-sm btn-outline-success mark-read-btn';
                e.target.removeAttribute('onclick');
                
                // Update badge count
                const badge = document.querySelector('#notificationsDropdown .badge');
                if (badge) {
                    let count = parseInt(badge.textContent) + 1;
                    badge.textContent = count;
                } else {
                    // Create new badge
                    const notifBtn = document.getElementById('notificationsDropdown');
                    if (notifBtn) {
                        const span = document.createElement('span');
                        span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                        span.textContent = '1';
                        notifBtn.appendChild(span);
                    }
                }
            }
        });
    }
});
</script> 