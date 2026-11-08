<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning Officer Dashboard</title>
    <link rel="stylesheet" href="home.css?v=2">
    <link rel="stylesheet" href="/Prototype/assets/css/theme.css">
    <link rel="stylesheet" href="/Prototype/assets/css/form-validation.css">
    <script src="/Prototype/assets/js/theme.js"></script>
    <style>
        /* Live Notification Styles */
        .top-right .notification-badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            width: auto;
            padding: 0 4px;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-sizing: border-box;
            text-align: center;
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
        
        /* ===== Sidebar navigation ===== */
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
            letter-spacing: 0.08em;
            line-height: 1.4;
            color: #ffffff;
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

        .sidebar-badge {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            display: none;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 20px;
            padding: 0 6px;
            border: none;
            border-radius: 999px;
            background: #dc3545;
            color: #ffffff;
            font-family: inherit;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            z-index: 10;
        }

        .sidebar-badge.show {
            display: inline-flex;
        }

        .sidebar-badge:hover,
        .sidebar-badge:focus-visible {
            background: #b02a37;
            outline: 2px solid #ffffff;
            outline-offset: 1px;
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
        
       /* Fix topbar layout on mobile */
@media (max-width: 768px) {
    .topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1001; /* above sidebar overlay */
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

    /* Hamburger always visible */
    .hamburger-menu {
        display: flex !important;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        flex-shrink: 0;
    }

    .hamburger-menu .material-icons {
        font-size: 26px;
        color: white;
    }

    /* Top left: show logo and clipped text */
    .top-left {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0; /* allows text to shrink */
        overflow: hidden;
    }

    .top-left img {
        display: block !important; /* show logo */
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

    /* Top right: show bell + badge */
    .top-right {
        display: flex !important;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        position: relative;
        cursor: pointer;
    }

    /* Hide "NOTIFICATIONS" text on mobile, keep bell */
    .top-right > span:first-child {
        display: none;
    }

    .bell {
        display: block !important;
        font-size: 26px !important;
        color: white !important;
    }

    .notification-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        width: 16px;
        height: 16px;
        font-size: 9px;
        display: flex !important;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .notif-box {
        right: 0;
        top: 45px;
        width: 270px;
    }

    /* Sidebar slides in over content, below topbar */
    .sidebar {
        position: fixed;
        top: 55px; /* below topbar */
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
        z-index: 1000;
        transition: left 0.3s ease;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .sidebar.active {
        left: 0;
    }

    /* Flat navigation remains touch friendly on mobile */
    .sidebar .nav-link {
        min-height: 46px;
        padding: 12px;
        font-size: 13px;
        touch-action: manipulation;
    }

    .sidebar .nav-link:active {
        background: rgba(255, 255, 255, 0.12);
    }

    /* Overlay covers content area only */
    .overlay {
        position: fixed;
        top: 55px;
        left: 0;
        width: 100%;
        height: calc(100vh - 55px);
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
    }

    .overlay.active {
        display: block;
    }
}

/* ===== DESKTOP RESET — ensures sidebar always shows on large screens ===== */
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
    }

    .container {
        margin-top: 0 !important;
        height: calc(100vh - 60px) !important;
    }

    .topbar {
        position: relative !important;
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
    }

    /* Show NOTIFICATIONS text on desktop */
    .top-right > span:first-child {
        display: inline !important;
    }
}
/* No sticky hover highlight on touch devices */
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

@media (max-width: 400px) {
    .top-left span {
        max-width: none;
        font-size: clamp(8px, 2.6vw, 10px);
    }

    .top-left img {
        width: 28px;
        height: 28px;
    }
}
            
            </style>

    

</head>

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

        <div class="notif-box" id="notifBox" onclick="event.stopPropagation()">
            <div id="notificationList">
                <p>Loading notifications...</p>
            </div>
            <div class="notification-actions">
                <button class="clear-all-btn" onclick="clearAllNotifications()">Clear All</button>
            </div>
        </div>
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    </div>
</header>

<div class="container">
    <div class="overlay" onclick="closeSidebar()"></div>

    <aside class="sidebar">
        <div class="sidebar-profile">
            <h2>PLANNING OFFICER</h2>
            <span class="sidebar-role">Dashboard</span>
        </div>

        <nav class="sidebar-nav" aria-label="Planning Officer navigation">
            <div class="nav-item">
                <button type="button" class="nav-link active" data-page="pages/vehicle_requested.php" aria-current="page"
                        onclick="loadPage('pages/vehicle_requested.php'); clearSidebarBadge('vehicle');">
                    <span class="material-icons nav-icon">directions_car</span>
                    <span class="nav-text">Vehicle Requested</span>
                </button>
                <button class="sidebar-badge" id="badgeVehicle" type="button"
                        title="Show all pending vehicle requests"
                        aria-label="Show all pending vehicle requests"
                        onclick="openPending('vehicle')">0</button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/vehicle_fleet.php"
                        onclick="loadPage('pages/vehicle_fleet.php')">
                    <span class="material-icons nav-icon">garage</span>
                    <span class="nav-text">Vehicle Fleet</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/requested_items.php"
                        onclick="loadPage('pages/requested_items.php'); clearSidebarBadge('items');">
                    <span class="material-icons nav-icon">inventory_2</span>
                    <span class="nav-text">Requested Items</span>
                </button>
                <span class="sidebar-badge" id="badgeItems">0</span>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/calendar.php"
                        onclick="loadPage('pages/calendar.php')">
                    <span class="material-icons nav-icon">calendar_today</span>
                    <span class="nav-text">Calendar</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/inventory_management.php"
                        onclick="loadPage('pages/inventory_management.php')">
                    <span class="material-icons nav-icon">inventory</span>
                    <span class="nav-text">Inventory Management</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/user_accounts.php"
                        onclick="loadPage('pages/user_accounts.php')">
                    <span class="material-icons nav-icon">manage_accounts</span>
                    <span class="nav-text">User Accounts</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/activity_logs.php"
                        onclick="loadPage('pages/activity_logs.php')">
                    <span class="material-icons nav-icon">history</span>
                    <span class="nav-text">Activity Logs</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/account_settings.php"
                        onclick="loadPage('pages/account_settings.php')">
                    <span class="material-icons nav-icon">settings</span>
                    <span class="nav-text">Account Settings</span>
                </button>
            </div>

            <div class="nav-item">
                <button type="button" class="nav-link" data-page="pages/data_analytics.php"
                        onclick="loadPage('pages/data_analytics.php')">
                    <span class="material-icons nav-icon">insights</span>
                    <span class="nav-text">Data Analytics</span>
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
        <!-- Content will be loaded here dynamically -->
    </main>
</div>

<?php include __DIR__ . '/includes/custom_alert_modal.php'; ?>

<script src="/Prototype/assets/js/form-validation.js"></script>
<script src="simple_dashboard_home.js"></script>
<script src="pages/planningofficer_script.js"></script>
<script src="../auth/auto-logout.js"></script>

<script>
// Keep the sidebar in sync with the page currently shown in the content area
function setActiveNav(page) {
    const path = String(page || '').split('?')[0];
    document.querySelectorAll('.sidebar-nav .nav-link[data-page]').forEach(link => {
        const isActive = link.dataset.page === path;
        link.classList.toggle('active', isActive);
        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

// Load page function similar to Faculty system
function loadPage(page) {
    setActiveNav(page);
    if (window.__notificationTargetClearTimer) {
        window.clearTimeout(window.__notificationTargetClearTimer);
        window.__notificationTargetClearTimer = null;
    }
    const targetParams = new URLSearchParams(String(page).split('?')[1] || '');
    window.notificationTargetId = targetParams.get('request_id') || '';
    window.notificationTargetAlignedId = '';
    window.notificationTargetUserId = targetParams.get('user_id') || '';
    fetch(page)
        .then(res => res.text())
        .then(html => {
            document.getElementById("mainContent").innerHTML = html;
            
            // Execute scripts if loading vehicle_requested.php
            if (page.includes('vehicle_requested.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing inline script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading calendar.php
            if (page.includes('calendar.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing calendar script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading user_accounts.php
            if (page.includes('user_accounts.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing user accounts script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }

            // Execute scripts if loading activity_logs.php
            if (page.includes('activity_logs.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading inventory_management.php
            if (page.includes('inventory_management.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing inventory management script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading account_settings.php
            if (page.includes('account_settings.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing account settings script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading add_vehicle.php
            if (page.includes('add_vehicle.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing add vehicle script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading vehicle_fleet.php
            if (page.includes('vehicle_fleet.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing vehicle fleet script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading data_analytics.php
            if (page.includes('data_analytics.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing data analytics script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
            
            // Execute scripts if loading requested_items.php
            if (page.includes('requested_items.php')) {
                // Wait a bit for DOM to be ready
                setTimeout(() => {
                    // Execute ALL inline scripts from loaded content
                    const scripts = document.getElementById("mainContent").querySelectorAll('script');
                    scripts.forEach(script => {
                        if (!script.src) { // Only execute inline scripts
                            console.log("Executing requested items script");
                            const newScript = document.createElement('script');
                            newScript.textContent = script.textContent;
                            document.body.appendChild(newScript);
                        }
                    });
                }, 100);
            }
        })
        .catch(() => {
            document.getElementById("mainContent").innerHTML =
                "<h2 style='text-align:center'>Page not found</h2>";
        });
}

// Clean up URL if it has unwanted query parameters
if (window.location.search && (window.location.search.includes('firstName=') || window.location.search.includes('lastName=') || window.location.search.includes('email='))) {
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Load vehicle requested page by default when page loads
window.addEventListener('load', function() {
    loadPage('pages/vehicle_requested.php');
});

// Live Notification System for Planning Officer
let notificationCount = 0;
let lastNotificationCheck = Date.now();

// Load notifications from server
async function loadNotifications() {
    try {
        const response = await fetch('/Prototype/database_requests.php?action=get_notifications&role=planning_officer');
        const data = await response.json();
        
        const notificationList = document.getElementById('notificationList');
        const badge = document.getElementById('notificationBadge');
        
        if (data && data.notifications && data.notifications.length > 0) {
            notificationList.replaceChildren();
            data.notifications.forEach(notification => {
                const isUnread = Number(notification.is_read) === 0;
                const item = document.createElement('div');
                item.className = `notification-item${isUnread ? ' new-notification' : ''}`;
                item.setAttribute('role', 'button');
                item.setAttribute('tabindex', '0');
                item.setAttribute('aria-label', `${notification.title}: ${notification.message}`);
                const header = document.createElement('div');
                header.className = 'notification-header';
                const title = document.createElement('span');
                title.className = 'notification-title';
                title.textContent = notification.title || '';
                const time = document.createElement('span');
                time.className = 'notification-time';
                time.dataset.createdAt = notification.created_at_iso || notification.created_at || '';
                time.textContent = formatNotificationTime(time.dataset.createdAt);
                header.append(title, time);
                const message = document.createElement('div');
                message.className = 'notification-message';
                message.textContent = notification.message || '';
                item.append(header, message);
                if (isUnread) {
                    const indicator = document.createElement('div');
                    indicator.className = 'notification-indicator';
                    item.appendChild(indicator);
                }
                item.dataset.notificationId = notification.id;
                item.dataset.requestId = notification.request_id || notification.related_id || '';
                item.dataset.requestType = notification.request_type || notification.type || '';
                const openNotification = event => handleNotificationClick(notification, event);
                item.addEventListener('click', openNotification);
                item.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openNotification(event);
                    }
                });
                notificationList.appendChild(item);
            });
            if (badge) {
                const unread = typeof data.unread_count !== 'undefined' ? data.unread_count : data.notifications.length;
                badge.textContent = unread;
                badge.style.display = unread > 0 ? 'flex' : 'none';
            }
        } else {
            if (notificationList) {
                notificationList.innerHTML = '<p>No new notifications</p>';
            }
            if (badge) {
                badge.style.display = 'none';
            }
        }
        
        lastNotificationCheck = Date.now();
    } catch (error) {
        console.error('Error loading notifications:', error);
        const notificationList = document.getElementById('notificationList');
        if (notificationList) {
            notificationList.innerHTML = '<p>Error loading notifications</p>';
        }
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
    document.querySelectorAll('#notificationList .notification-time[data-created-at]').forEach(el => {
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
    const requestId = target.relatedId ? `?request_id=${encodeURIComponent(target.relatedId)}` : '';
    const userId = target.relatedId ? `?user_id=${encodeURIComponent(target.relatedId)}` : '';
    switch (target.type) {
        case 'item_request':
            return `pages/requested_items.php${requestId}`;
        case 'account_update':
            return `pages/user_accounts.php${userId}`;
        case 'vehicle_request':
        default:
            return `pages/vehicle_requested.php${requestId}`;
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

// Keep the deep-link id around briefly: the SPA loader can render a page more
// than once, and clearing the id on the first render would let the next render
// wipe the highlight.
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

function markNotificationReadLocally(itemElement) {
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

// Handle notification click - navigate directly to the relevant page and record.
async function handleNotificationClick(notification, event) {
    if (event) event.stopPropagation();
    const target = getNotificationTarget(notification);
    const page = buildNotificationPage(target);

    document.getElementById('notifBox').style.display = 'none';

    const isUnread = Number(notification.is_read) === 0;
    if (isUnread) {
        markNotificationReadLocally(event ? event.currentTarget : null);
    }

    // Navigate immediately; the database update runs in the background so the
    // dropdown does not delay the page transition.
    loadPage(page);
    if (!isUnread) return;

    const marked = await markNotificationRead(notification.id);
    if (marked) {
        loadNotifications();
        updateSidebarBadges();
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

// Auto-load vehicle requests page when page loads (dashboard.php no longer exists)
document.addEventListener('DOMContentLoaded', function() {
    loadPage('pages/vehicle_requested.php');
    // Start live notifications
    startNotificationPolling();
    // Start sidebar badge polling
    startSidebarBadgePolling();
});

// ===== Sidebar Pending Request Badges =====
function startSidebarBadgePolling() {
    loadBadgeSeenThenPoll();
}

async function loadBadgeSeenThenPoll() {
    try {
        const vRes = await fetch('/Prototype/database_requests.php?action=get_badge_seen&section=vehicle');
        const vData = await vRes.json();
        if (vData.seen_at) localStorage.setItem('badge_seen_vehicle', vData.seen_at);

        const iRes = await fetch('/Prototype/database_requests.php?action=get_badge_seen&section=items');
        const iData = await iRes.json();
        if (iData.seen_at) localStorage.setItem('badge_seen_items', iData.seen_at);
    } catch (e) {
        console.error('Error loading badge seen timestamps:', e);
    }
    updateSidebarBadges();
    setInterval(updateSidebarBadges, 15000);
}

// The badge counts pending requests across every year, but the vehicle list defaults
// to the current year. Deep-linking with ?show=pending clears the year and month
// filters, so a badge reading "1" always lands on exactly one visible row instead of
// an apparently empty list.
function openPending(section) {
    if (section === 'vehicle') {
        loadPage('pages/vehicle_requested.php?show=pending');
    } else {
        loadPage('pages/requested_items.php');
    }
    clearSidebarBadge(section);
}

async function updateSidebarBadges() {
    try {
        const vSeen = localStorage.getItem('badge_seen_vehicle') || '';
        const iSeen = localStorage.getItem('badge_seen_items') || '';
        const params = new URLSearchParams();
        if (vSeen) params.set('vehicle_seen', vSeen);
        if (iSeen) params.set('items_seen', iSeen);

        const res = await fetch('/Prototype/database_requests.php?action=get_pending_counts&' + params.toString());
        const data = await res.json();
        
        const vBadge = document.getElementById('badgeVehicle');
        const iBadge = document.getElementById('badgeItems');
        
        if (vBadge) {
            if (data.vehicle_pending > 0) {
                vBadge.textContent = data.vehicle_pending;
                vBadge.classList.add('show');
            } else {
                vBadge.classList.remove('show');
            }
        }
        if (iBadge) {
            if (data.items_pending > 0) {
                iBadge.textContent = data.items_pending;
                iBadge.classList.add('show');
            } else {
                iBadge.classList.remove('show');
            }
        }
    } catch (e) {
        console.error('Error fetching pending counts:', e);
    }
}

async function clearSidebarBadge(section) {
    try {
        const formData = new FormData();
        formData.append('section', section);
        const res = await fetch('/Prototype/database_requests.php?action=set_badge_seen', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.seen_at) {
            localStorage.setItem('badge_seen_' + section, data.seen_at);
        }
    } catch (e) {
        console.error('Error setting badge seen:', e);
    }
    const badgeId = section === 'vehicle' ? 'badgeVehicle' : 'badgeItems';
    const badge = document.getElementById(badgeId);
    if (badge) badge.classList.remove('show');
}
</script>
</body>
</html>
