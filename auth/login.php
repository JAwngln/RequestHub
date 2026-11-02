<?php
session_start();
include '../connect.php';

$loginSuccess = false;
$loginRedirect = '';

// Check if user is already logged in
if (isset($_SESSION['email'])) {
    // Redirect based on role to appropriate dashboard
    $userRole = trim($_SESSION['role']);
    if ($userRole === 'faculty') {
        header("Location: ../Faculty/home.php");
    } elseif ($userRole === 'planning_officer') {
        header("Location: ../PLANNING OFFICER/home.php");
    } else {
        header("Location: login.php");
    }
    exit();
}

// Handle login submission
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

        if ($password === $row['password'] || password_verify($password, $row['password'])) {
            // Save user data to session
            $_SESSION['email'] = $row['email'];
            $_SESSION['firstName'] = $row['firstName'];
            $_SESSION['lastName'] = $row['lastName'];
            $_SESSION['role'] = $row['role'];

            // Set flag for custom modal
            $userRole = trim($row['role']);
            $loginSuccess = true;
            $loginRedirect = ($userRole === 'faculty') ? '../Faculty/home.php' : (($userRole === 'planning_officer') ? '../PLANNING OFFICER/home.php' : 'login.php');
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
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
    <title>Login - CVSU Transportation System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include __DIR__ . '/../Faculty/includes/custom_alert_modal.php'; ?>

<!-- Background -->
<div class="background"></div>

<!-- LOGIN FORM -->
<div class="container" id="signIn">
    <div class="container-content">
    <img src="../ppss-logo.png" alt="PPSS Logo" class="logo">
    <h1>Login</h1>
    
    <?php if (isset($error)): ?>
        <div style="background: #ff6b6b; color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="login.php">
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

<!-- REGISTER FORM -->
<div class="container" id="signup" style="display:none;">
    <div class="container-content">
    <img src="../ppss-logo.png" alt="PPSS Logo" class="logo">
    <h1>Register</h1>
    
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

<script src="script.js"></script>
<?php if ($loginSuccess && isset($loginRedirect)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var go = function() { window.location.href = '<?php echo addslashes($loginRedirect); ?>'; };
        if (typeof showAppAlert === 'function') {
            showAppAlert('Login successful', 'Success', go, { type: 'success' });
        } else if (window.showAppAlert) {
            window.showAppAlert('Login successful', 'Success', go, { type: 'success' });
        } else {
            alert('Login successful');
            go();
        }
    });
</script>
<?php endif; ?>
</body>
</html>
