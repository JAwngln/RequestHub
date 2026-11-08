<?php
session_start();

// Allow page to stay without redirect
if (!isset($_SESSION['email'])) {
    // Redirect to login instead of showing error
    header("Location: ../auth/login.php");
    exit();
}
if (trim($_SESSION['role'] ?? '') !== 'faculty') {
    if (trim($_SESSION['role'] ?? '') === 'planning_officer') {
        header("Location: ../PLANNING%20OFFICER/home.php");
    } else {
        header("Location: ../auth/login.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FACULTY Dashboard</title>

    <link rel="stylesheet" href="home.css?v=2">
    <link rel="stylesheet" href="/Prototype/assets/css/theme.css">
    <link rel="stylesheet" href="/Prototype/assets/css/form-validation.css">
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <script src="/Prototype/assets/js/theme.js"></script>
    <style>
        html, body {
            overflow: hidden;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        .content {
            height: calc(100vh - 80px);
            overflow-y: auto;
            position: relative;
        }
        #mainContent {
            height: 100%;
            overflow-y: auto;
        }
        
        /* Live Notification Styles */
        .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 9px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .notification-item {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.new-notification {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
        }

        /* Temporary emphasis applied to a request card opened from a notification */
        .request-item.notification-highlight {
            outline: 3px solid #1E5E3A;
            outline-offset: 3px;
            background-color: #f0fdf4;
            scroll-margin-top: 24px;
            animation: notificationHighlightPulse 1.4s ease-in-out 2;
        }

        @keyframes notificationHighlightPulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(30, 94, 58, 0.35);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(30, 94, 58, 0);
            }
        }

        .notif-box {
            display: none;
            flex-direction: column;
            width: min(340px, calc(100vw - 24px));
            max-height: min(70vh, 480px);
            overflow: hidden;
            box-sizing: border-box;
        }

        #notificationList {
            height: min(55vh, 360px);
            max-height: min(55vh, 360px);
            min-height: 0;
            overflow-x: hidden;
            overflow-y: scroll;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            flex: 0 1 auto;
        }

        .notification-actions {
            flex: 0 0 auto;
        }

        .notification-item:focus-visible {
            outline: 2px solid #1c6b2f;
            outline-offset: -2px;
        }
        
        .notification-content strong {
            display: block;
            margin-bottom: 4px;
            color: #2c3e50;
        }
        
        .notification-content p {
            margin: 0 0 4px 0;
            font-size: 14px;
            color: #6c757d;
        }
        
        .notification-content small {
            color: #adb5bd;
            font-size: 12px;
        }
        
        .notification-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 8px;
            height: 8px;
            background: #2196f3;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }
        
        .top-right {
            position: relative;
        }
        
        .notification-actions {
            padding: 10px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        
        .clear-all-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }
        
        .clear-all-btn:hover {
            background: #c82333;
        }
        
        /* Responsive Design */
        .hamburger-menu {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            margin-right: 10px;
        }
        
        .hamburger-menu .material-icons {
            font-size: 24px;
            color: #333;
        }
        
        /* ===== Mobile: fixed topbar + sliding drawer sidebar (matches Planning Officer) ===== */
        @media (max-width: 768px) {
            .topbar {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1001;
                height: 55px;
                padding: 0 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            /* Push content down so it doesn't hide under fixed topbar */
            .container {
                margin-top: 55px;
                height: calc(100vh - 55px);
            }

            .hamburger-menu {
                display: flex !important;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                cursor: pointer;
                padding: 6px;
                flex-shrink: 0;
                -webkit-tap-highlight-color: transparent;
            }

            .hamburger-menu .material-icons {
                font-size: 26px;
                color: white;
            }

            .top-left {
                display: flex;
                align-items: center;
                gap: 8px;
                flex: 1;
                min-width: 0;
                overflow: hidden;
            }

            .top-left img {
                display: block !important;
                width: 30px;
                height: 30px;
                object-fit: contain;
                flex-shrink: 0;
                border-radius: 50%;
                background: white;
                padding: 2px;
                box-sizing: border-box;
            }

            .top-left span {
                display: block !important;
                font-size: clamp(8px, 2.6vw, 11px);
                color: white;
                white-space: nowrap;
                max-width: none;
            }

            .top-right {
                display: flex !important;
                align-items: center;
                gap: 6px;
                flex-shrink: 0;
                position: relative;
                cursor: pointer;
            }

            .top-right > span:first-child {
                display: none !important;
            }

            .bell {
                display: block !important;
                font-size: 26px !important;
                color: white !important;
            }

            .notif-box {
                right: 0;
                top: 45px;
                width: 270px;
            }

            .notification-badge {
                width: 16px;
                height: 16px;
                font-size: 9px;
            }

            .sidebar {
                position: fixed;
                top: 55px;
                left: -270px;
                width: 260px !important;
                height: calc(100vh - 55px);
                background: linear-gradient(#0e3f1e, #0a2f16);
                color: white;
                padding: 20px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                box-shadow: 2px 0 8px rgba(0,0,0,0.2);
                transition: left 0.3s ease;
                z-index: 1000;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            .sidebar.active {
                left: 0;
            }

            /* Consistent sidebar buttons on mobile */
            .sidebar .btn {
                width: 100%;
                margin: 0;
                padding: 12px 10px;
                border-radius: 20px;
                border: 1px solid white;
                cursor: pointer;
                font-weight: bold;
                font-size: 14px;
                min-height: 44px;
                background: #1c6b2f;
                color: white;
                transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
                -webkit-tap-highlight-color: transparent;
                touch-action: manipulation;
            }

            .sidebar .btn.logout {
                background: #8aa08f;
                margin-top: auto;
            }

            .sidebar .btn:hover {
                background-color: white;
                color: #1c6b2f;
                border: 1px solid #1c6b2f;
            }

            .sidebar .btn:active {
                transform: scale(0.98);
            }

            .content,
            #mainContent {
                height: 100% !important;
                padding: 20px 15px;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 55px;
                left: 0;
                width: 100%;
                height: calc(100vh - 55px);
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

            .overlay.active {
                display: block;
            }
        }

        /* No sticky hover highlight on touch devices */
        @media (hover: none), (pointer: coarse) {
            .sidebar .btn:hover {
                background-color: #1c6b2f !important;
                color: white !important;
                border: 1px solid white !important;
            }
            .sidebar .btn.logout:hover {
                background-color: #8aa08f !important;
                color: white !important;
                border: 1px solid white !important;
            }
        }

        /* ===== Desktop: restore static sidebar ===== */
        @media (min-width: 769px) {
            .sidebar {
                position: relative !important;
                left: 0 !important;
                top: auto !important;
                width: 270px !important;
                height: auto !important;
                display: flex !important;
                z-index: auto !important;
                overflow-y: visible !important;
                box-shadow: none !important;
            }

            .container {
                margin-top: 0 !important;
                height: calc(100vh - 60px) !important;
            }

            .topbar {
                position: relative !important;
                height: 60px !important;
                z-index: auto !important;
            }

            .overlay {
                display: none !important;
            }

            .hamburger-menu {
                display: none !important;
            }

            .top-left img {
                display: block !important;
                width: 35px !important;
            }

            .top-left span {
                display: block !important;
                font-size: inherit !important;
                max-width: none !important;
                white-space: normal !important;
                color: white;
            }

            .top-right > span:first-child {
                display: inline !important;
            }
        }

        @media (max-width: 400px) {
            .top-left span {
                max-width: none;
                font-size: clamp(8px, 2.6vw, 10px);
            }

            .top-left img {
                width: 28px;
                height: 28px;
            }

            .notif-box {
                width: 240px;
            }
        }

        /* ===== Flat sidebar navigation ===== */
        .sidebar {
            gap: 0;
            padding: 22px 16px 18px;
        }

        .sidebar-profile {
            padding: 4px 12px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 14px;
        }

        .sidebar-profile h2 {
            margin: 0;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.06em;
            line-height: 1.4;
            color: #ffffff;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .sidebar-role {
            display: block;
            margin-top: 4px;
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            min-height: 44px;
            padding: 11px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: rgba(255, 255, 255, 0.76);
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
            cursor: pointer;
            transition: background-color 0.18s ease, color 0.18s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .nav-link .nav-icon {
            flex-shrink: 0;
            font-size: 19px;
            opacity: 0.75;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #ffffff;
        }

        .nav-link:focus-visible {
            outline: 2px solid rgba(255, 255, 255, 0.65);
            outline-offset: 2px;
        }

        .nav-link.active,
        .nav-link[aria-current="page"] {
            background: #1E5E3A;
            color: #ffffff;
            box-shadow: inset 3px 0 0 #6fbf8b;
        }

        .nav-link.active .nav-icon,
        .nav-link[aria-current="page"] .nav-icon {
            opacity: 1;
        }

        .sidebar-footer {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer .links {
            margin: 0;
            padding: 0 12px 10px;
            font-size: 10px;
            letter-spacing: 0.05em;
            text-align: left;
        }

        .sidebar-footer .links a {
            margin-top: 6px;
            color: rgba(255, 255, 255, 0.5);
        }

        .sidebar-footer .links a:hover {
            color: #ffffff;
        }

        .logout-link {
            color: rgba(255, 255, 255, 0.65);
        }

        .logout-link:hover {
            background: rgba(220, 53, 69, 0.16);
            color: #ffb4b4;
        }

        @media (max-width: 768px) {
            .sidebar .nav-link {
                min-height: 46px;
                padding: 12px;
                font-size: 13px;
                touch-action: manipulation;
            }

            .sidebar .nav-link:active {
                background: rgba(255, 255, 255, 0.12);
            }
        }

        @media (hover: none), (pointer: coarse) {
            .sidebar .nav-link:hover {
                background: transparent;
                color: rgba(255, 255, 255, 0.76);
            }

            .sidebar .nav-link.active:hover,
            .sidebar .nav-link[aria-current="page"]:hover {
                background: #1E5E3A;
                color: #ffffff;
            }

            .sidebar .logout-link:hover {
                background: transparent;
                color: rgba(255, 255, 255, 0.65);
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="top-left">
        <button class="hamburger-menu" onclick="toggleSidebar()">
            <span class="material-icons">menu</span>
        </button>
        <img src="../ppss-logo.png">
        <span>PHYSICAL PLANT AND SECURITY SERVICE</span>
    </div>

    <div class="top-right" onclick="toggleNotif()">
        <button type="button" class="theme-toggle" onclick="event.stopPropagation(); toggleTheme();"
                aria-label="Switch to dark mode" title="Switch to dark mode">
            <span class="material-icons theme-toggle-icon">dark_mode</span>
            <span class="theme-toggle-text">Dark</span>
        </button>
        <span>NOTIFICATIONS</span>  
        <span class="material-icons bell">notifications</span>
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>

        <div class="notif-box" id="notifBox" onclick="event.stopPropagation()">
            <div id="notificationList">
                <p>Loading notifications...</p>
            </div>
            <div class="notification-actions">
                <button class="clear-all-btn" onclick="clearAllNotifications()">Clear All</button>
            </div>
        </div>
    </div>
</header>

<div class="container">
    <div class="overlay" onclick="closeSidebar()"></div>

    <aside class="sidebar">
        <div class="sidebar-profile">
            <h2>
                <?php 
                    echo $_SESSION['firstName'] . " " . $_SESSION['lastName']; 
                ?>
            </h2>
            <span class="sidebar-role">Faculty</span>
        </div>

        <nav class="sidebar-nav" aria-label="Faculty navigation">
            <div class="nav-item">
                <button type="button" class="nav-link active" data-page="pages/vehicle.php" aria-current="page"
                        onclick="loadPage('pages/vehicle.php')">
                    <span class="material-icons nav-icon">directions_car</span>
                    <span class="nav-text">Vehicle Request</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/request.php"
                        onclick="loadPage('pages/request.php')">
                    <span class="material-icons nav-icon">inventory_2</span>
                    <span class="nav-text">Request Item</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/calendar.php"
                        onclick="loadPage('pages/calendar.php')">
                    <span class="material-icons nav-icon">calendar_today</span>
                    <span class="nav-text">Calendar</span>
                </button>
            </div>

        </nav>

        <div class="sidebar-footer">
            <div class="links">
                <a href="https://cvsu.edu.ph/naic-campus" target="_blank">VISIT CVSU NAIC OFFICIAL WEBSITE</a>
            </div>
            <button type="button" class="nav-link logout-link" onclick="window.location.href='../auth/logout.php'">
                <span class="material-icons nav-icon">logout</span>
                <span class="nav-text">Logout</span>
            </button>
        </div>
    </aside>

    <main class="content" id="mainContent">
        <!-- Request page with requested items list will auto-load here -->
    </main>

    <script>
        // Make user session data available to all pages
        window.userFirstName = '<?php echo $_SESSION['firstName'] ?? ''; ?>';
        window.userLastName = '<?php echo $_SESSION['lastName'] ?? ''; ?>';
        window.userEmail = '<?php echo $_SESSION['email'] ?? ''; ?>';
        window.userRole = <?php echo json_encode($_SESSION['role'] ?? ''); ?>;
        
        // Clean up URL if it has unwanted query parameters
        if (window.location.search && (window.location.search.includes('firstName=') || window.location.search.includes('lastName=') || window.location.search.includes('email='))) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Live Notification System
        let notificationCount = 0;
        let lastNotificationCheck = Date.now();

        // Load notifications from server
        async function loadNotifications() {
            try {
                const response = await fetch('/Prototype/database_requests.php?table=notifications&action=get_notifications');
                const data = await response.json();
                
                const notificationList = document.getElementById('notificationList');
                const badge = document.getElementById('notificationBadge');
                
                if (data && data.notifications && data.notifications.length > 0) {
                    let newCount = 0;
                    notificationList.replaceChildren();
                    
                    data.notifications.forEach(notif => {
                        const isNew = Date.parse(notif.created_at_iso || notif.created_at) > lastNotificationCheck;
                        const isUnread = Number(notif.is_read) === 0;
                        if (isNew && isUnread) newCount++;

                        const item = document.createElement('div');
                        item.className = `notification-item${isNew ? ' new-notification' : ''}`;
                        item.setAttribute('role', 'button');
                        item.setAttribute('tabindex', '0');
                        item.setAttribute('aria-label', `${notif.title}: ${notif.message}`);
                        const content = document.createElement('div');
                        content.className = 'notification-content';
                        const title = document.createElement('strong');
                        title.textContent = notif.title || '';
                        const message = document.createElement('p');
                        message.textContent = notif.message || '';
                        const time = document.createElement('small');
                        time.dataset.createdAt = notif.created_at_iso || notif.created_at || '';
                        time.textContent = formatNotificationTime(time.dataset.createdAt);
                        content.append(title, message, time);
                        item.appendChild(content);
                        if (isUnread) {
                            const indicator = document.createElement('div');
                            indicator.className = 'notification-indicator';
                            item.appendChild(indicator);
                        }
                        item.dataset.notificationId = notif.id;
                        item.dataset.requestId = notif.request_id || notif.related_id || '';
                        item.dataset.requestType = notif.request_type || notif.type || '';
                        const openNotification = event => handleNotificationClick(notif, event);
                        item.addEventListener('click', openNotification);
                        item.addEventListener('keydown', event => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                openNotification(event);
                            }
                        });
                        notificationList.appendChild(item);
                    });
                    
                    // Update badge with unread count from server
                    const unread = typeof data.unread_count !== 'undefined' ? data.unread_count : 0;
                    if (unread > 0) {
                        badge.textContent = unread;
                        badge.style.display = 'flex';
                        // Play notification sound only when a brand new notification arrives
                        if (newCount > 0) {
                            playNotificationSound();
                        }
                    } else {
                        badge.style.display = 'none';
                    }
                    
                    notificationCount = unread;
                } else {
                    notificationList.innerHTML = '<p>No new notifications</p>';
                    if (badge) badge.style.display = 'none';
                }
                
                lastNotificationCheck = Date.now();
            } catch (error) {
                console.error('Error loading notifications:', error);
                document.getElementById('notificationList').innerHTML = '<p>Error loading notifications</p>';
            }
        }

        // Format notification time
        function formatNotificationTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
            if (diffMins < 1440) return `${Math.floor(diffMins / 60)} hour${Math.floor(diffMins / 60) > 1 ? 's' : ''} ago`;
            return date.toLocaleDateString();
        }

        // Keep the "x ago" labels current without waiting for the next poll.
        function refreshNotificationTimes() {
            document.querySelectorAll('#notificationList .notification-content small[data-created-at]').forEach(el => {
                el.textContent = formatNotificationTime(el.dataset.createdAt);
            });
        }

        // Route a notification to the page that owns the referenced record.
        // related_id is the database id of a vehicle/item request, and type tells
        // us which request page should receive the deep link.
        function getNotificationTarget(notification) {
            const payload = (() => {
                if (!notification || !notification.payload) return {};
                if (typeof notification.payload === 'object') return notification.payload;
                try {
                    return JSON.parse(notification.payload) || {};
                } catch (error) {
                    return {};
                }
            })();

            const rawType = String(
                payload.request_type || payload.type ||
                notification.request_type || notification.type || ''
            ).toLowerCase();
            const relatedId = Number(
                payload.request_id || payload.related_id ||
                notification.request_id || notification.related_id
            ) || 0;

            if (rawType.includes('vehicle')) {
                return { type: 'vehicle_request', relatedId };
            }
            if (rawType.includes('item') || rawType.includes('inventory')) {
                return { type: 'item_request', relatedId };
            }
            if (rawType.includes('account') || rawType.includes('user')) {
                return { type: 'account_update', relatedId };
            }
            return { type: rawType || 'vehicle_request', relatedId };
        }

        function buildNotificationPage(target) {
            const suffix = target.relatedId ? `?request_id=${encodeURIComponent(target.relatedId)}` : '';
            switch (target.type) {
                case 'item_request':
                    return `pages/request.php${suffix}`;
                case 'vehicle_request':
                default:
                    return `pages/vehicle.php${suffix}`;
            }
        }

        // Give the target card a temporary highlight and bring it into view.
        function highlightNotificationTarget(target) {
            if (!target) return;
            target.classList.add('notification-highlight');
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => target.classList.remove('notification-highlight'), 4000);
        }
        window.highlightNotificationTarget = highlightNotificationTarget;

        // Keep the deep-link id around briefly: the SPA loader can render a page
        // more than once (inline script + vehicle.js/request.js), and clearing the
        // id on the first render would let the next render wipe the highlight.
        window.scheduleNotificationTargetClear = function (targetId, delay) {
            const id = Number(targetId) || 0;
            if (!id || window.__notificationTargetClearTimer) return;
            window.__notificationTargetClearTimer = window.setTimeout(() => {
                if (Number(window.notificationTargetId) === id) {
                    window.notificationTargetId = '';
                }
                window.notificationTargetAlignedId = '';
                window.__notificationTargetClearTimer = null;
            }, delay || 4500);
        };

        function markNotificationReadLocally(notificationId, itemElement) {
            if (itemElement) {
                itemElement.classList.remove('new-notification');
                const indicator = itemElement.querySelector('.notification-indicator');
                if (indicator) indicator.remove();
            }
            const badge = document.getElementById('notificationBadge');
            if (!badge) return;
            const current = parseInt(badge.textContent, 10) || 0;
            const next = Math.max(0, current - 1);
            badge.textContent = next;
            badge.style.display = next > 0 ? 'flex' : 'none';
        }

        async function markNotificationRead(notificationId) {
            if (!notificationId) return false;
            try {
                const response = await fetch('/Prototype/database_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_notification_read&notification_id=${encodeURIComponent(notificationId)}`
                });
                const result = await response.json();
                return response.ok && result.success;
            } catch (error) {
                console.error('Error marking notification as read:', error);
                return false;
            }
        }

        // Handle notification click - route to the record it references, mark it
        // read, then update the badge without blocking the navigation.
        async function handleNotificationClick(notification, event) {
            if (event) event.stopPropagation();
            const target = getNotificationTarget(notification);
            const page = buildNotificationPage(target);

            document.getElementById('notifBox').style.display = 'none';

            const isUnread = Number(notification.is_read) === 0;
            if (isUnread) {
                markNotificationReadLocally(notification.id, event ? event.currentTarget : null);
            }

            // Navigate straight away so the click feels instant; the read update
            // continues in the background and the list is refreshed when done.
            loadPage(page);
            if (!isUnread) return;

            const marked = await markNotificationRead(notification.id);
            if (marked) {
                loadNotifications();
            }
        }

        // Play notification sound
        function playNotificationSound() {
            // Create a simple beep sound using Web Audio API
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        }

        // Clear all notifications
        async function clearAllNotifications() {
            try {
                const response = await fetch('/Prototype/database_requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=clear_all_notifications'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Clear the notification list
                    const notificationList = document.getElementById('notificationList');
                    if (notificationList) {
                        notificationList.innerHTML = '<p>No new notifications</p>';
                    }
                    // Hide the badge
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                    // Reset local counters
                    notificationCount = 0;
                    lastNotificationCheck = Date.now();
                    // Close the notification dropdown after clearing
                    const notifBox = document.getElementById('notifBox');
                    if (notifBox) {
                        notifBox.style.display = 'none';
                    }
                } else {
                    console.error('Failed to clear notifications:', result.error);
                }
            } catch (error) {
                console.error('Error clearing notifications:', error);
            }
        }

        // Toggle the notification dropdown
        function toggleNotif() {
            const box = document.getElementById('notifBox');
            const isHidden = box.style.display === 'none' || box.style.display === '';
            box.style.display = isHidden ? 'flex' : 'none';
            if (isHidden) {
                loadNotifications();
            }
        }

        // Start notification polling
        function startNotificationPolling() {
            // Load notifications immediately
            loadNotifications();
            
            // Poll often so new notifications arrive close to realtime
            setInterval(loadNotifications, 5000);
            // Keep the "x ago" labels accurate between polls
            setInterval(refreshNotificationTimes, 10000);
        }

        // Calendar navigation functions for AJAX-loaded calendar
        window.changeYear = function(direction) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentYear = parseInt(urlParams.get('year') || 2026);
            let newYear = currentYear + direction;
            
            // Validate year range (2026-2050)
            if (newYear < 2026) newYear = 2026;
            if (newYear > 2050) newYear = 2050;
            
            loadPage(`pages/calendar.php?year=${newYear}`);
        };

        window.goToToday = function() {
            loadPage(`pages/calendar.php?year=2026`);
        };
        
        // Hamburger menu functions
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        window.navigateToMonth = function(monthNumber) {
            // This function will be defined in the calendar page itself
            // The calendar page handles the scrolling and highlighting
            console.log('Month clicked:', monthNumber);
        };

        // Auto-load vehicle request page on page load so faculty can access vehicle requests instantly
        document.addEventListener('DOMContentLoaded', function() {
            loadPage('pages/vehicle.php');
            // Start live notifications
            startNotificationPolling();
        });
    </script>

</div>

<script src="/Prototype/assets/js/form-validation.js"></script>
<script src="database_requests.js"></script>
<script src="shared_requests.js"></script>
<script src="main.js?v=3"></script>
<script src="../auth/auto-logout.js"></script>
</body>
</html>
