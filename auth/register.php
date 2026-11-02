<?php
session_start();
include '../connect.php';

/* ================================
   REGISTER USER
================================ */
if (isset($_POST['signUp'])) {

    $firstName = trim($_POST['fName']);
    $lastName  = trim($_POST['lName']);
    $email     = trim($_POST['email']);
    $password  = trim($_POST['password']);
    $role      = 'faculty'; // Only faculty can register

    // Store password as plain text (no hashing)
    $plainPassword = $password;

    // Check if email already exists
    $check = $conn->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Registration</title><link rel='stylesheet' href='style.css'></head><body>";
        include __DIR__ . '/../Faculty/includes/custom_alert_modal.php';
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var back = function(){ window.history.back(); };
            if (typeof showAppAlert === 'function') {
                showAppAlert('Email already exists', 'Error', back, { type: 'error' });
            } else if (window.showAppAlert) {
                window.showAppAlert('Email already exists', 'Error', back, { type: 'error' });
            } else {
                alert('Email already exists');
                back();
            }
        });
        </script></body></html>";
        exit();
    }

    // Insert user with faculty role
    $insert = $conn->prepare(
        "INSERT INTO users (firstName, lastName, email, password, role)
         VALUES (?, ?, ?, ?, ?)"
    );
    $insert->bind_param("sssss", $firstName, $lastName, $email, $plainPassword, $role);

    if ($insert->execute()) {
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Registration</title><link rel='stylesheet' href='style.css'></head><body>";
        include __DIR__ . '/../Faculty/includes/custom_alert_modal.php';
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var go = function(){ window.location.href='login.php'; };
            if (typeof showAppAlert === 'function') {
                showAppAlert('Registration successful! Please login.', 'Success', go, { type: 'success' });
            } else if (window.showAppAlert) {
                window.showAppAlert('Registration successful! Please login.', 'Success', go, { type: 'success' });
            } else {
                alert('Registration successful! Please login.');
                go();
            }
        });
        </script></body></html>";
        exit();
    } else {
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Registration</title><link rel='stylesheet' href='style.css'></head><body>";
        include __DIR__ . '/../Faculty/includes/custom_alert_modal.php';
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var back = function(){ window.history.back(); };
            if (typeof showAppAlert === 'function') {
                showAppAlert('Registration failed. Please try again.', 'Error', back, { type: 'error' });
            } else if (window.showAppAlert) {
                window.showAppAlert('Registration failed. Please try again.', 'Error', back, { type: 'error' });
            } else {
                alert('Registration failed. Please try again.');
                back();
            }
        });
        </script></body></html>";
        exit();
    }
}

/* ================================
   LOGIN USER (for login form on register page)
================================ */
if (isset($_POST['signIn'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        error_log("User found: " . $row['email'] . ", role: " . $row['role']);

        if ($password === $row['password'] || password_verify($password, $row['password'])) {

            error_log("Password verified successfully");

            // SAVE USER DATA TO SESSION
            $_SESSION['email'] = $row['email'];
            $_SESSION['firstName'] = $row['firstName'];
            $_SESSION['lastName']  = $row['lastName'];
            $_SESSION['role']      = $row['role'];

            error_log("Session data saved, redirecting based on role: " . $row['role']);

            // REDIRECT TO DASHBOARD BASED ON ROLE
            $userRole = trim($row['role']);
            if ($userRole === 'faculty') {
                header("Location: ../Faculty/home.php");
            } elseif ($userRole === 'planning_officer') {
                header("Location: ../PLANNING OFFICER/home.php");
            } else {
                header("Location: login.php");
            }
            exit();
        } else {
            error_log("Password verification failed for: " . $email);
            echo "<script>
            alert('Wrong password');
            window.history.back();
            </script>";
            exit();
        }

    } else {
        error_log("User not found: " . $email);
        echo "<script>
        alert('User not found');
        window.history.back();
        </script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CVSU Transportation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Research Title -->
<div class="research-title">
    <h1>
        Automated Transportation Request System for the Planning Office <br>
        and Inventory Management System for the Physical Plant and Security Services (PPSS) of CvSU Naic
    </h1>
</div>

<!-- Background -->
<div class="background"></div>

<!-- REGISTER FORM -->
<div class="container" id="signup">
    <div class="container-bg-text">
        ReqHub: A Web-based Transportation Request System for the Planning Office
        and Inventory Management System for the Physical Plant and Security Services (PPSS) of CvSU Naic
    </div>
    <div class="container-content">
    <h1>Faculty Registration</h1>
    
    <form method="post" action="register.php">
        <div class="input-group">
            <i class="material-icons">person</i>
            <input type="text" name="fName" placeholder=" " required>
            <label>First Name</label>
        </div>

        <div class="input-group">
            <i class="material-icons">person</i>
            <input type="text" name="lName" placeholder=" " required>
            <label>Last Name</label>
        </div>

        <div class="input-group">
            <i class="material-icons">email</i>
            <input type="email" name="email" placeholder=" " required>
            <label>Email</label>
        </div>

        <div class="input-group">
            <i class="material-icons">lock</i>
            <input type="password" name="password" placeholder=" " required>
            <label>Password</label>
        </div>

        <button type="submit" name="signUp" class="btn">REGISTER</button>
    </form>

    <div class="links">
        <p>Already have an account?</p>
        <button id="signInButton">LOGIN</button>
    </div>
    </div>
</div>

<!-- LOGIN FORM -->
<div class="container" id="signIn" style="display:none;">
    <div class="container-bg-text">
        ReqHub: A Web-based Transportation Request System for the Planning Office
        and Inventory Management System for the Physical Plant and Security Services (PPSS) of CvSU Naic
    </div>
    <div class="container-content">
    <h1>Login</h1>
    
    <form method="post" action="register.php">
        <div class="input-group">
            <i class="material-icons">email</i>
            <input type="email" name="email" placeholder=" " required>
            <label>Email</label>
        </div>

        <div class="input-group">
            <i class="material-icons">lock</i>
            <input type="password" name="password" placeholder=" " required>
            <label>Password</label>
        </div>

        <button type="submit" name="signIn" class="btn">LOGIN</button>
    </form>

    <div class="links">
        <p>Don't have an account?</p>
        <button id="signUpButton">CREATE ACCOUNT</button>
    </div>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>
