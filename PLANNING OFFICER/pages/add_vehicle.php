<?php
/**
 * Compatibility shim.
 *
 * Vehicle creation now lives in vehicle_fleet.php so the vehicle and its driver
 * are registered through one validated workflow. Old bookmarks and the former
 * sidebar entry land here, then open the fleet page with the Add Vehicle dialog
 * already visible.
 */
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Vehicle</title>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            margin: 0;
            font-family: "Poppins", "Segoe UI", Arial, sans-serif;
            color: #6b7280;
        }

        .redirect-note {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 18px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            font-size: 13px;
        }

        .redirect-note .material-icons {
            color: #1E5E3A;
        }
    </style>
</head>
<body>
    <div class="redirect-note">
        <span class="material-icons">directions_car</span>
        Opening the vehicle fleet...
    </div>

    <script>
    (function () {
        'use strict';
        var target = 'pages/vehicle_fleet.php?open=add';
        try {
            if (window.parent && typeof window.parent.loadPage === 'function') {
                window.parent.loadPage(target);
                return;
            }
        } catch (error) {
            // Cross-origin or detached frame: fall through to a full navigation.
        }
        window.location.href = 'vehicle_fleet.php?open=add';
    })();
    </script>
</body>
</html>
