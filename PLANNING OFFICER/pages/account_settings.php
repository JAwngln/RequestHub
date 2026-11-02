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

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">

    <style>
        /* ================= ACCOUNT SETTINGS STYLES ================= */
        .paper-form {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
            margin: 0 auto;
            max-width: 1180px;
            width: 100%;
            padding: 24px 28px 28px;
        }

        .paper-form h1 {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1.25;
            margin: 0;
            text-align: left;
            text-shadow: none;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .page-heading {
            flex: 1 1 280px;
            min-width: 220px;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 12.5px;
            line-height: 1.5;
        }

        /* Toolbar: segmented section switcher + signed-in account pill */
        .vr-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .vr-tabs {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            max-width: 100%;
            padding: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f3f4f6;
            overflow-x: auto;
        }

        .vr-tab {
            appearance: none;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: #4b5563;
            padding: 8px 16px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color 0.18s ease, color 0.18s ease;
        }

        .vr-tab:hover {
            background: rgba(255, 255, 255, 0.7);
            color: #111827;
        }

        .vr-tab.active {
            background: #ffffff;
            color: #1E5E3A;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.08);
        }

        .vr-tab:focus-visible {
            outline: 2px solid #1E5E3A;
            outline-offset: 2px;
        }

        .settings-account {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 38px;
            padding: 0 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #1E5E3A;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            max-width: 100%;
        }

        .settings-account .material-icons {
            font-size: 18px;
        }

        .settings-account span:last-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .section {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 14px;
            background: #ffffff;
            scroll-margin-top: 16px;
        }

        .section:last-of-type {
            margin-bottom: 0;
        }

        .section h2 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin: 0 0 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f3f4f6;
        }

        .section h2 .material-icons {
            font-size: 18px;
            color: #1E5E3A;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #6b7280;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .form-group input {
            width: 100%;
            height: 40px;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-family: inherit;
            font-size: 13.5px;
            box-sizing: border-box;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1E5E3A;
            box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 4px;
        }

        .settings-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 38px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .settings-btn-primary {
            background: #1E5E3A;
            color: #ffffff;
        }

        .settings-btn-primary:hover {
            background: #174a2e;
        }

        .settings-btn-ghost {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .settings-btn-ghost:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .settings-btn-danger {
            background: #ffffff;
            border-color: #fecdca;
            color: #b42318;
        }

        .settings-btn-danger:hover {
            background: #fef3f2;
            border-color: #fda29b;
        }

        .delete-section {
            background: #fffbfa;
            border-color: #fecdca;
        }

        .delete-section h2 {
            color: #b42318;
            border-bottom-color: #fee4e2;
        }

        .delete-section h2 .material-icons {
            color: #b42318;
        }

        .delete-section p {
            margin: 0 0 16px;
            color: #6b7280;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .message {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
            border: 1px solid transparent;
            font-size: 13px;
        }

        .message.success {
            background: #ecfdf3;
            border-color: #abefc6;
            color: #067647;
        }

        .message.error {
            background: #fef3f2;
            border-color: #fecdca;
            color: #b42318;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 15% auto;
            padding: 26px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-content h3 {
            color: #111827;
            margin: 0 0 8px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .modal-content p {
            color: #6b7280;
            margin: 0 0 20px;
            font-size: 13px;
            line-height: 1.5;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .modal-buttons .settings-btn {
            flex: 1;
        }

        @media (max-width: 900px) {
            .paper-form {
                padding: 18px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 14px;
            }

            .page-heading {
                flex: 0 0 auto;
            }
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .form-actions {
                justify-content: stretch;
            }

            .settings-btn {
                width: 100%;
            }

            .modal-buttons {
                flex-direction: column-reverse;
            }

            .vr-toolbar {
                align-items: stretch;
            }

            .vr-tabs {
                width: 100%;
            }

            .vr-tab {
                flex: 1 1 0;
                min-width: 0;
                padding: 8px 6px;
                font-size: 11px;
                text-align: center;
            }

            .settings-account {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

        <div class="paper-form">
            <div class="page-header">
                <div class="page-heading">
                    <h1>ACCOUNT SETTINGS</h1>
                    <p class="page-subtitle">Update your profile details, password, and account access.</p>
                </div>
            </div>

            <div class="vr-toolbar">
                <div class="vr-tabs" role="tablist" aria-label="Account settings sections">
                    <button type="button" class="vr-tab active" data-section="personal" role="tab" aria-selected="true" onclick="showSettingsSection('personal')">Personal Information</button>
                    <button type="button" class="vr-tab" data-section="password" role="tab" aria-selected="false" onclick="showSettingsSection('password')">Change Password</button>
                    <button type="button" class="vr-tab" data-section="delete" role="tab" aria-selected="false" onclick="showSettingsSection('delete')">Delete Account</button>
                </div>

                <span class="settings-account" title="<?php echo htmlspecialchars($email); ?>">
                    <span class="material-icons">account_circle</span>
                    <span><?php echo htmlspecialchars($email); ?></span>
                </span>
            </div>

            <!-- Personal Information Section -->
            <div class="section" id="section-personal">
                <h2><i class="material-icons">person</i> Personal Information</h2>
                <div id="personalInfoMessage" class="message"></div>
                <form id="personalInfoForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="settings-btn settings-btn-primary">Update Information</button>
                    </div>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="section" id="section-password">
                <h2><i class="material-icons">lock</i> Change Password</h2>
                <div id="passwordMessage" class="message"></div>
                <form id="passwordForm">
                    <div class="form-grid">
                        <div class="form-group full-width">
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
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="settings-btn settings-btn-primary">Change Password</button>
                    </div>
                </form>
            </div>

            <!-- Delete Account Section -->
            <div class="section delete-section" id="section-delete">
                <h2><i class="material-icons">delete_forever</i> Delete Account</h2>
                <p>Once you delete your account, there is no going back. Please be certain.</p>
                <button type="button" class="settings-btn settings-btn-danger" onclick="showDeleteModal()">Delete Account</button>
            </div>
        </div>

    <!-- Personal Info Confirmation Modal -->
    <div id="personalInfoModal" class="modal">
        <div class="modal-content">
            <h3>Update Information?</h3>
            <p>Are you sure you want to update your personal information?</p>
            <div class="modal-buttons">
                <button type="button" class="settings-btn settings-btn-ghost" onclick="hidePersonalInfoModal()">No, Cancel</button>
                <button type="button" class="settings-btn settings-btn-primary" onclick="confirmPersonalInfoUpdate()">Yes, Update</button>
            </div>
        </div>
    </div>

    <!-- Password Confirmation Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h3>Change Password?</h3>
            <p>Are you sure you want to change your password?</p>
            <div class="modal-buttons">
                <button type="button" class="settings-btn settings-btn-ghost" onclick="hidePasswordModal()">No, Cancel</button>
                <button type="button" class="settings-btn settings-btn-primary" onclick="confirmPasswordChange()">Yes, Change</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Delete Account?</h3>
            <p>Are you sure you want to delete your account? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button type="button" class="settings-btn settings-btn-ghost" onclick="hideDeleteModal()">No, Cancel</button>
                <button type="button" class="settings-btn settings-btn-danger" onclick="deleteAccount()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Segmented section switcher (mirrors the Vehicle Requests status tabs).
        // All sections stay visible; the selected tab highlights and scrolls to it.
        function showSettingsSection(section) {
            document.querySelectorAll('.vr-tab[data-section]').forEach(function (tab) {
                const isActive = tab.dataset.section === section;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            const panel = document.getElementById('section-' + section);
            if (panel) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

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
            
            fetch('/Prototype/PLANNING OFFICER/pages/update_account.php', {
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
            
            fetch('/Prototype/PLANNING OFFICER/pages/change_password.php', {
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
            fetch('/Prototype/PLANNING OFFICER/pages/delete_account.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAppAlert('Your account has been deleted successfully.', 'Account Deleted', function () {
                        window.location.href = '../../auth/logout.php';
                    }, { type: 'success' });
                } else {
                    hideDeleteModal();
                    showAppAlert('Error: ' + data.message, 'Unable to Delete Account', null, { type: 'error' });
                }
            })
            .catch(error => {
                hideDeleteModal();
                showAppAlert('An error occurred. Please try again.', 'Something Went Wrong', null, { type: 'error' });
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
<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
