<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}

// Direct database query to get users
require_once '../../connect.php';

$users = [];
$error = '';

try {
    $sql = "SELECT id, firstName, lastName, email, password, role FROM users ORDER BY id DESC";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        $error = "Database query failed: " . $conn->error;
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

$conn->close();
?>

<link href="/Prototype/assets/fonts/material-icons.css" rel="stylesheet">
<link href="/Prototype/assets/fonts/poppins.css" rel="stylesheet">
    <style>
        /* ================= USER ACCOUNTS STYLES ================= */

        /* Pull the card closer to the top bar; scoped to this page because the
           style block is removed with the fragment on navigation. */
        #mainContent {
            padding-top: 16px;
        }

        .paper-form {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
            margin: 0 auto;
            max-width: 1180px;
            padding: 16px 28px 28px;
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
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding-bottom: 12px;
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

        /* Inline search group: input + Search + Clear on one row, right of the title */
        .filter-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex: 0 0 auto;
            flex-wrap: nowrap;
        }

        .filter-field {
            flex: 0 0 300px;
            min-width: 0;
        }

        .search-input {
            width: 100%;
            height: 38px;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #ffffff;
            color: #111827;
            font-family: inherit;
            font-size: 13px;
            box-sizing: border-box;
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .search-input:focus {
            outline: none;
            border-color: #1E5E3A;
            box-shadow: 0 0 0 3px rgba(30, 94, 58, 0.12);
        }

        .filter-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 38px;
            padding: 0 16px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .filter-btn-primary {
            background: #1E5E3A;
            color: #ffffff;
        }

        .filter-btn-primary:hover {
            background: #174a2e;
        }

        .filter-btn-ghost {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .filter-btn-ghost:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Status segmented control */
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

        .user-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            white-space: nowrap;
        }

        .user-count .material-icons {
            font-size: 18px;
        }

        /* User cards */
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .user-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px 20px;
            transition: border-color 0.18s ease;
        }

        .user-card:hover {
            border-color: #d1d5db;
        }

        .user-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .user-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .user-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #eef5f0;
            color: #1E5E3A;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .user-name {
            color: #111827;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .user-id {
            color: #6b7280;
            font-size: 11.5px;
            margin-top: 2px;
        }

        /* Status chip */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            padding: 4px 10px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1.5;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.85;
        }

        .status-badge.role-faculty {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .status-badge.role-planning_officer {
            background: #f5f3ff;
            border-color: #ddd6fe;
            color: #6d28d9;
        }

        .status-badge.role-admin {
            background: #ecfdf3;
            border-color: #abefc6;
            color: #067647;
        }

        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px 22px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .detail-label {
            color: #6b7280;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .detail-value {
            color: #1f2937;
            font-size: 13.5px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .user-password {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            color: #1f2937;
            font-size: 13.5px;
        }

        .user-password .password-real {
            overflow-wrap: anywhere;
        }

        .user-password .material-icons {
            color: #6b7280;
        }

        .password-eye {
            cursor: pointer;
            transition: color 0.18s ease;
        }

        .password-eye:hover {
            color: #1E5E3A;
        }

        .user-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            padding: 0 16px;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .action-btn .material-icons {
            font-size: 16px;
        }

        .delete-btn {
            border: 1px solid #fecdca;
            background: #ffffff;
            color: #b42318;
        }

        .delete-btn:hover {
            background: #fef3f2;
            border-color: #fda29b;
        }

        .error-message,
        .no-users {
            padding: 34px 20px;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            background: #f9fafb;
            color: #6b7280;
            text-align: center;
        }

        .error-message {
            border-color: #fecdca;
            background: #fef3f2;
            color: #b42318;
        }

        .no-users .material-icons {
            font-size: 40px;
            color: #d1d5db;
            margin-bottom: 8px;
        }

        .no-users p {
            margin: 0;
            font-size: 12.5px;
        }

        .empty-message {
            padding: 34px 20px;
            border: 1px dashed #d1d5db;
            border-radius: 10px;
            background: #f9fafb;
            color: #6b7280;
            text-align: center;
        }

        .empty-message h3 {
            margin: 0 0 6px;
            color: #374151;
            font-size: 15px;
        }

        .empty-message p {
            margin: 0;
            font-size: 12.5px;
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

            .filter-controls {
                width: 100%;
                justify-content: flex-start;
            }

            .filter-field {
                flex: 1 1 auto;
                min-width: 0;
            }
        }

        @media (max-width: 600px) {
            .filter-controls {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                align-items: end;
            }

            .filter-field {
                grid-column: 1 / -1;
            }

            .filter-btn {
                width: 100%;
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

            .user-card {
                padding: 16px;
            }

            .user-details {
                grid-template-columns: 1fr;
            }

            .user-card-header {
                gap: 10px;
            }

            .action-btn {
                width: 100%;
            }
        }

        /* ================= MODAL ================= */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            color: #111827;
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .close {
            color: #9ca3af;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close:hover {
            color: #111827;
        }

        .user-detail {
            margin-bottom: 16px;
        }

        .user-detail label {
            font-weight: 600;
            color: #6b7280;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 5px;
            display: block;
        }

        .user-detail-value {
            padding: 10px 12px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            color: #1f2937;
            font-size: 13.5px;
            word-break: break-all;
        }

        .user-detail-value.password {
            background: #fef3f2;
            border-color: #fecdca;
            color: #b42318;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .role-badge.role-faculty {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .role-badge.role-planning_officer {
            background: #f5f3ff;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .role-badge.role-admin {
            background: #ecfdf3;
            color: #067647;
            border: 1px solid #abefc6;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1E5E3A;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div class="paper-form">
            <div class="page-header">
                <div class="page-heading">
                    <h1>USER ACCOUNTS</h1>
                    <p class="page-subtitle">Manage and review all registered users in the system.</p>
                </div>

                <div class="filter-controls" role="search" aria-label="Search users">
                    <div class="filter-field">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email, or role..." aria-label="Search users by name, email, or role" onkeyup="filterUsers()">
                    </div>
                    <button type="button" class="filter-btn filter-btn-primary" onclick="filterUsers()">Search</button>
                    <button type="button" class="filter-btn filter-btn-ghost" onclick="clearUserSearch()">Clear</button>
                </div>
            </div>

            <div class="vr-toolbar">
                <div class="vr-tabs" role="tablist" aria-label="Filter users by role">
                    <button type="button" class="vr-tab active" data-role="all" role="tab" aria-selected="true" onclick="filterUsersByRole('all')">All Users</button>
                    <button type="button" class="vr-tab" data-role="faculty" role="tab" aria-selected="false" onclick="filterUsersByRole('faculty')">Faculty</button>
                    <button type="button" class="vr-tab" data-role="planning_officer" role="tab" aria-selected="false" onclick="filterUsersByRole('planning_officer')">Planning Officer</button>
                    <button type="button" class="vr-tab" data-role="admin" role="tab" aria-selected="false" onclick="filterUsersByRole('admin')">Admin</button>
                </div>

                <span class="user-count" id="userCount">
                    <span class="material-icons">group</span>
                    <span id="userCountText"><?php echo count($users); ?> Users</span>
                </span>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="material-icons" style="font-size: 24px; margin-bottom: 10px;">error</i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php elseif (empty($users)): ?>
                <div class="no-users">
                    <i class="material-icons">person_off</i>
                    <p>No users found in the system</p>
                </div>
            <?php else: ?>
                <div class="user-list" id="userList">
                    <?php foreach ($users as $user): ?>
                        <?php
                            $fullName = trim($user['firstName'] . ' ' . $user['lastName']);
                            $initials = strtoupper(substr($user['firstName'] ?? '', 0, 1) . substr($user['lastName'] ?? '', 0, 1));
                            $roleLabel = str_replace('_', ' ', $user['role']);
                        ?>
                        <div class="user-card" data-user-id="<?php echo (int)$user['id']; ?>" data-role="<?php echo htmlspecialchars($user['role']); ?>">
                            <div class="user-card-header">
                                <div class="user-identity">
                                    <span class="user-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></span>
                                    <div>
                                        <div class="user-name"><?php echo htmlspecialchars($fullName ?: 'Unnamed user'); ?></div>
                                        <div class="user-id">User #<?php echo (int)$user['id']; ?></div>
                                    </div>
                                </div>
                                <span class="status-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo htmlspecialchars($roleLabel); ?>
                                </span>
                            </div>

                            <div class="user-details">
                                <div class="detail-item">
                                    <span class="detail-label">Full Name</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($fullName ?: '—'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Password</span>
                                    <span class="user-password">
                                        <span class="password-text">••••••••</span>
                                        <span class="password-real" style="display: none;"><?php echo htmlspecialchars($user['password']); ?></span>
                                        <i class="material-icons password-eye" style="font-size: 16px;" onclick="toggleSinglePassword(this)">visibility</i>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Role</span>
                                    <span class="detail-value"><?php echo htmlspecialchars(ucwords($roleLabel)); ?></span>
                                </div>
                            </div>

                            <div class="user-actions">
                                <button class="action-btn delete-btn" onclick="deleteUser(<?php echo (int)$user['id']; ?>)">
                                    <i class="material-icons">delete</i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="empty-message" id="noUserMatches" style="display: none;">
                        <h3>No users match your search</h3>
                        <p>Try a different name, email, role, or filter.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>User Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="userDetailsContent">
                <div style="text-align: center; padding: 40px;">
                    <div class="loading-spinner"></div>
                    <p>Loading user details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.userRoleFilter = 'all';

        function filterUsersByRole(status) {
            window.userRoleFilter = status;

            document.querySelectorAll('.vr-tab[data-role]').forEach(tab => {
                const isActive = tab.dataset.role === status;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            filterUsers();
        }

        function filterUsers() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const cards = document.querySelectorAll('#userList .user-card');
            let visible = 0;

            cards.forEach(card => {
                const roleMatches = window.userRoleFilter === 'all'
                    || card.dataset.role === window.userRoleFilter;
                const textMatches = card.textContent.toLowerCase().includes(searchTerm);
                const matches = roleMatches && textMatches;
                card.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });

            const noMatches = document.getElementById('noUserMatches');
            if (noMatches) {
                noMatches.style.display = (cards.length > 0 && visible === 0) ? '' : 'none';
            }

            updateUserCount(visible);
        }

        function clearUserSearch() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.value = '';
            filterUsers();
        }

        function viewUser(userId) {
            const modal = document.getElementById('viewUserModal');
            const content = document.getElementById('userDetailsContent');
            
            // Show loading state
            content.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="loading-spinner"></div>
                    <p>Loading user details...</p>
                </div>
            `;
            
            // Show modal
            modal.style.display = 'block';
            
            // Fetch user details
            fetch('/Prototype/PLANNING OFFICER/pages/user_management.php?action=view_user&id=' + userId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        content.innerHTML = `
                            <div class="user-detail">
                                <label>User ID</label>
                                <div class="user-detail-value">${user.id}</div>
                            </div>
                            <div class="user-detail">
                                <label>Full Name</label>
                                <div class="user-detail-value">${user.firstName} ${user.lastName}</div>
                            </div>
                            <div class="user-detail">
                                <label>Email Address</label>
                                <div class="user-detail-value">${user.email}</div>
                            </div>
                            <div class="user-detail">
                                <label>Password</label>
                                <div class="user-detail-value password">${user.password}</div>
                            </div>
                            <div class="user-detail">
                                <label>User Role</label>
                                <div class="user-detail-value">
                                    <span class="role-badge role-${user.role}">${user.role.replace('_', ' ').toUpperCase()}</span>
                                </div>
                            </div>
                        `;
                    } else {
                        content.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #dc3545;">
                                <h3>Error</h3>
                                <p>${data.error}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching user details:', error);
                    content.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <h3>Error</h3>
                            <p>Failed to load user details: ${error.message}</p>
                            <p style="font-size: 12px; margin-top: 10px;">Please check the browser console for more details.</p>
                        </div>
                    `;
                });
        }

        function deleteUser(userId) {
            const button = event && event.target ? event.target.closest('button') : null;
            showAppConfirm('Are you sure you want to delete this user? This action cannot be undone.', 'Delete User?', function () {
                // Show loading state on the button
                const originalContent = button ? button.innerHTML : '';
                if (button) {
                    button.innerHTML = '<div class="loading-spinner"></div> Deleting...';
                    button.disabled = true;
                }
                
                // Use AJAX to delete user
                fetch('/Prototype/PLANNING OFFICER/pages/user_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_user&id=${userId}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Response text:', text);
                            throw new Error('Server returned non-JSON response. Check console for details.');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        showMessage('User deleted successfully', 'success');
                        // Remove the user card from the list
                        const userCard = button ? button.closest('.user-card') : null;
                        if (userCard) {
                            userCard.remove();
                        }
                        // Update user count
                        filterUsers();
                    } else {
                        showMessage('Error deleting user: ' + (data.error || 'Unknown error'), 'error');
                        // Restore button state
                        if (button) {
                            button.innerHTML = originalContent;
                            button.disabled = false;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error deleting user:', error);
                    showMessage('Error deleting user. Please try again.', 'error');
                    // Restore button state
                    if (button) {
                        button.innerHTML = originalContent;
                        button.disabled = false;
                    }
                });
            }, null, { type: 'danger', okText: 'Delete', cancelText: 'Cancel' });
        }

        function closeModal() {
            document.getElementById('viewUserModal').style.display = 'none';
        }

        function toggleSinglePassword(eyeIcon) {
            const passwordContainer = eyeIcon.parentElement;
            const passwordText = passwordContainer.querySelector('.password-text');
            const passwordReal = passwordContainer.querySelector('.password-real');
            
            if (passwordReal.style.display === 'none') {
                // Always require admin password to reveal
                showAppPrompt('Enter admin password to reveal passwords:', 'Admin Verification', function () {
                    revealSinglePassword(passwordText, passwordReal, eyeIcon);
                }, null, {
                    type: 'confirm',
                    okText: 'Reveal',
                    cancelText: 'Cancel',
                    inputType: 'password',
                    placeholder: 'Admin password',
                    autocomplete: 'current-password',
                    validate: function (value) {
                        return value === 'admin' ? '' : 'Incorrect admin password';
                    }
                });
            } else {
                // Hide password without requiring password
                hideSinglePassword(passwordText, passwordReal, eyeIcon);
            }
        }

        function revealSinglePassword(passwordText, passwordReal, eyeIcon) {
            passwordText.style.display = 'none';
            passwordReal.style.display = 'inline';
            eyeIcon.textContent = 'visibility_off';
        }

        function hideSinglePassword(passwordText, passwordReal, eyeIcon) {
            passwordText.style.display = 'inline';
            passwordReal.style.display = 'none';
            eyeIcon.textContent = 'visibility';
        }

        function updateUserCount(count) {
            const countEl = document.getElementById('userCountText');
            if (!countEl) return;
            if (typeof count !== 'number') {
                count = document.querySelectorAll('#userList .user-card').length;
            }
            countEl.textContent = count + ' User' + (count === 1 ? '' : 's');
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s;
                max-width: 300px;
            `;
            
            if (type === 'success') {
                messageDiv.style.background = '#28a745';
            } else {
                messageDiv.style.background = '#dc3545';
            }
            
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.animation = 'fadeOut 0.3s';
                setTimeout(() => {
                    document.body.removeChild(messageDiv);
                }, 300);
            }, 3000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewUserModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        (function() {
            const targetUserId = Number(window.notificationTargetUserId) || 0;
            if (targetUserId) {
                const targetUser = document.querySelector(`#userList .user-card[data-user-id="${targetUserId}"]`);
                if (targetUser) {
                    targetUser.style.outline = '3px solid #1E5E3A';
                    targetUser.style.outlineOffset = '3px';
                    targetUser.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    viewUser(targetUserId);
                }
                window.notificationTargetUserId = '';
            }
        })();
    </script>
<?php include __DIR__ . '/../includes/custom_alert_modal.php'; ?>
