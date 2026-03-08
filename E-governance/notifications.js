// Notification-related frontend logic
// This file centralizes all functions used by dashboard.html or other pages
// for loading and manipulating user notifications.

/**
 * Fetch notifications from the server and render them in the dashboard
 * @param {number} userId - ID of the logged-in user
 */
async function loadNotifications(userId) {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_notifications',
                user_id: userId,
                limit: 10
            })
        });

        const data = await response.json();
        const notificationsList = document.getElementById('notificationsList');

        if (data.success && data.notifications.length > 0) {
            notificationsList.innerHTML = '';
            data.notifications.forEach(notif => {
                const timeAgo = getTimeAgo(notif.created_at);
                const readClass = notif.is_read ? '' : 'unread';
                
                const notifHTML = `
                    <div class="notification-item ${readClass}" onclick="markNotificationAsRead(${notif.notif_id})">
                        <div class="notif-icon">${notif.icon}</div>
                        <div class="notif-content">
                            <p class="notif-title">${notif.subject}</p>
                            <p class="notif-message">${notif.message}</p>
                            <small>${timeAgo}</small>
                        </div>
                    </div>
                `;
                notificationsList.innerHTML += notifHTML;
            });
        } else {
            notificationsList.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No notifications yet</p>';
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        document.getElementById('notificationsList').innerHTML = '<p style="padding: 20px; color: red;">Error loading notifications</p>';
    }
}

/**
 * Convert a timestamp into a human-friendly "time ago" string
 * @param {string} createdAt - ISO timestamp from database
 * @returns {string} - e.g. "2 hours ago", "3 days ago"
 */
function getTimeAgo(createdAt) {
    const now = new Date();
    const created = new Date(createdAt);
    const diffMs = now - created;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) {
        return 'just now';
    } else if (diffMins < 60) {
        return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    } else if (diffDays < 30) {
        return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    } else {
        const diffMonths = Math.floor(diffDays / 30);
        return `${diffMonths} month${diffMonths > 1 ? 's' : ''} ago`;
    }
}

/**
 * Mark a notification as read on the server and refresh the list
 * @param {number} notifId - Notification identifier
 */
async function markNotificationAsRead(notifId) {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'mark_as_read',
                notif_id: notifId
            })
        });

        const data = await response.json();
        if (data.success) {
            const user = getUserSession();
            if (user) {
                loadNotifications(user.id);
            }
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}
