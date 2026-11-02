<?php
session_start();
require_once '../../connect.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Get current user info
$email = $_SESSION['email'];
$firstName = $_SESSION['firstName'] ?? '';
$lastName = $_SESSION['lastName'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Faculty</title>
    <link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
    <link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

    
    <style>
        .settings-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .settings-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .settings-header h1 {
            color: #1c6b2f;
            margin: 0;
        }

        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .section h2 {
            color: #555;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        #mainContent .btn {
            padding: 12px 24px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        #mainContent .primary {
            background: white;
            color: #1c6b2f;
        }

        #mainContent .secondary {
            background: #1c6b2f;
            color: white;
            border: 1px solid white;
        }

        #mainContent .primary:hover,
        #mainContent .secondary:hover {
            background-color: white;
            color: #1c6b2f;
            border: 1px solid #1c6b2f;
            transition: 0.3s;
        }

        .message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .delete-section {
            background: #fff5f5;
            border: 1px solid #fed7d7;
        }

        .delete-section h2 {
            color: #c53030;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .modal-content h3 {
            color: #1c6b2f;
            margin-top: 0;
        }

        .modal-content p {
            color: #666;
            margin: 20px 0;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .modal-buttons button {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <h1>Account Settings</h1>
        </div>

        <!-- Personal Information Section -->
        <div class="section">
            <h2><i class="material-icons">person</i> Personal Information</h2>
            <div id="personalInfoMessage" class="message"></div>
            <form id="personalInfoForm">
                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                <button type="submit" class="btn secondary">Update Information</button>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="section">
            <h2><i class="material-icons">lock</i> Change Password</h2>
            <div id="passwordMessage" class="message"></div>
            <form id="passwordForm">
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <input type="password" id="currentPassword" name="currentPassword" required>
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="newPassword" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                </div>
                <button type="submit" class="btn secondary">Change Password</button>
            </form>
        </div>

        <!-- Delete Account Section -->
        <div class="section delete-section">
            <h2><i class="material-icons">delete_forever</i> Delete Account</h2>
            <p style="color: #666; margin-bottom: 15px;">
                Once you delete your account, there is no going back. Please be certain.
            </p>
            <button type="button" class="btn secondary" onclick="showDeleteModal()">Delete Account</button>
        </div>
    </div>

    <!-- Personal Info Confirmation Modal -->
    <div id="personalInfoModal" class="modal">
        <div class="modal-content">
            <h3>Update Information?</h3>
            <p>Are you sure you want to update your personal information?</p>
            <div class="modal-buttons">
                <button type="button" class="btn secondary" onclick="hidePersonalInfoModal()">No, Cancel</button>
                <button type="button" class="btn secondary" onclick="confirmPersonalInfoUpdate()">Yes, Update</button>
            </div>
        </div>
    </div>

    <!-- Password Confirmation Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>Change Password?</h3>
            <p>Are you sure you want to change your password?</p>
            <div class="modal-buttons">
                <button type="button" class="btn secondary" onclick="hidePasswordModal()">No, Cancel</button>
                <button type="button" class="btn secondary" onclick="confirmPasswordChange()">Yes, Change</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Delete Account?</h3>
            <p>Are you sure you want to delete your account? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button type="button" class="btn secondary" onclick="hideDeleteModal()">No, Cancel</button>
                <button type="button" class="btn primary" onclick="deleteAccount()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Personal Information Form
        document.getElementById('personalInfoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            showPersonalInfoModal();
        });

        function showPersonalInfoModal() {
            document.getElementById('personalInfoModal').style.display = 'block';
        }

        function hidePersonalInfoModal() {
            document.getElementById('personalInfoModal').style.display = 'none';
        }

        function confirmPersonalInfoUpdate() {
            hidePersonalInfoModal();
            
            const form = document.getElementById('personalInfoForm');
            const formData = new FormData(form);
            const messageDiv = document.getElementById('personalInfoMessage');
            
            fetch('/Prototype/Faculty/pages/update_account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message;
                }
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message error';
                messageDiv.textContent = 'An error occurred. Please try again.';
            });
        }

        // Password Form
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('passwordMessage');
            
            // Validate passwords match
            if (newPassword !== confirmPassword) {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message error';
                messageDiv.textContent = 'New passwords do not match.';
                return;
            }
            
            showPasswordModal();
        });

        function showPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }

        function hidePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }

        function confirmPasswordChange() {
            hidePasswordModal();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const messageDiv = document.getElementById('passwordMessage');
            
            const formData = new FormData();
            formData.append('currentPassword', currentPassword);
            formData.append('newPassword', newPassword);
            
            fetch('/Prototype/Faculty/pages/change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    document.getElementById('passwordForm').reset();
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message;
                }
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message error';
                messageDiv.textContent = 'An error occurred. Please try again.';
            });
        }

        // Delete Account Modal
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'block';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteAccount() {
            fetch('/Prototype/Faculty/pages/delete_account.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Account deleted successfully.');
                    window.location.href = '../../auth/logout.php';
                } else {
                    alert('Error: ' + data.message);
                    hideDeleteModal();
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                hideDeleteModal();
            });
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const personalInfoModal = document.getElementById('personalInfoModal');
            const passwordModal = document.getElementById('passwordModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == personalInfoModal) {
                hidePersonalInfoModal();
            }
            if (event.target == passwordModal) {
                hidePasswordModal();
            }
            if (event.target == deleteModal) {
                hideDeleteModal();
            }
        }
    </script>
    
    <style>
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .account-settings {
            padding: 20px;
            margin: 10px;
        }
        
        .account-settings h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .settings-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .settings-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #1c6b2f;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-group label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-group p {
            font-size: 14px;
            margin: 0;
        }
        
        #mainContent .btn {
            padding: 10px 20px;
            font-size: 14px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #1c6b2f;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .modal {
            padding: 20px;
        }
        
        .modal-content {
            padding: 20px;
            margin: 10px;
        }
        
        .modal-content h2 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-size: 12px;
            margin-bottom: 5px;
            display: block;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .modal-buttons button {
            flex: 1;
            padding: 10px;
            font-size: 12px;
        }
    }
    
    @media (max-width: 480px) {
        .account-settings {
            padding: 15px;
            margin: 5px;
        }
        
        .account-settings h1 {
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .settings-section {
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .settings-section h2 {
            font-size: 16px;
            margin-bottom: 12px;
        }
        
        .info-group {
            margin-bottom: 12px;
        }
        
        .info-group label {
            font-size: 11px;
        }
        
        .info-group p {
            font-size: 13px;
        }
        
        #mainContent .btn {
            padding: 8px 16px;
            font-size: 12px;
            margin: 3px;
        }
        
        .modal-content {
            padding: 15px;
            margin: 5px;
        }
        
        .modal-content h2 {
            font-size: 16px;
            margin-bottom: 12px;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            font-size: 11px;
        }
        
        .form-group input {
            padding: 8px;
            font-size: 13px;
        }
        
        .modal-buttons {
            gap: 8px;
            margin-top: 12px;
        }
        
        .modal-buttons button {
            padding: 8px;
            font-size: 11px;
        }
    }
    </style>
</body>
</html>
