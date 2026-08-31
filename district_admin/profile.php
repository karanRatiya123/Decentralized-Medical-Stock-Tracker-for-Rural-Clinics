<?php
session_start();

$admin_name = $admin_name ?? '';
$username = $username ?? '';
$district = $district ?? '';
$contact = $contact ?? '';
$email = $email ?? '';
$role = $role ?? '';
$pending_requests = $pending_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/district_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. DISTRICT ADMIN SIDEBAR
         ============================== -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-shield-alt brand-icon"></i>
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>District Admin</p>
            </div>
        </div>
        
        <ul class="nav-links">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="phcs.php">
                    <i class="fas fa-hospital"></i>
                    <span>PHC Centers</span>
                </a>
            </li>
            <li>
                <a href="staff.php">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff Management</span>
                </a>
            </li>
            <li>
                <a href="inventory.php">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory Monitor</span>
                </a>
            </li>
            <li>
                <a href="medicine_requests.php">
                    <i class="fas fa-file-invoice"></i>
                    <span>Medicine Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_requests; ?></span>
                </a>
            </li>
            <li>
                <a href="alerts.php">
                    <i class="fas fa-bell"></i>
                    <span>Alerts & Expiry</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>District Reports</span>
                </a>
            </li>
            <li class="active">
                <a href="profile.php">
                    <i class="fas fa-user-shield"></i>
                    <span>Admin Profile</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $admin_name; ?></h4>
                    <p>District: <?php echo $district; ?></p>
                </div>
            </div>
            <a href="../login.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- ==============================
         2. MAIN CONTENT AREA
         ============================== -->
    <main class="main-content">
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>District Administrator Profile</h2>
                <p>Manage account credentials and administrative settings for <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme">
                    <i class="fas fa-sun"></i>
                </button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="dashboard-container">

            <!-- SUCCESS NOTIFICATION BANNER -->
            <div class="alert-banner success-banner" id="profileSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="profileBannerText">Profile information updated successfully!</span>
            </div>

            <div class="profile-layout-grid">
                
                <!-- LEFT COLUMN: ADMIN IDENTITY CARD -->
                <div class="profile-identity-card">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3><?php echo $admin_name; ?></h3>
                    <p class="role-badge"><?php echo $role; ?></p>
                    <p class="district-info"><i class="fas fa-map-marker-alt"></i> District Jurisdiction: <strong><?php echo $district; ?></strong></p>
                    
                    <div class="security-note">
                        <i class="fas fa-lock"></i>
                        <span>Superadmin clearance level granted for medical inventory and PHC governance.</span>
                    </div>
                </div>

                <!-- RIGHT COLUMN: EDIT PROFILE & PASSWORD FORM -->
                <div class="profile-form-panel">
                    <div class="panel-header">
                        <h3 class="section-title">Edit Administrative Details</h3>
                    </div>
                    <form id="adminProfileForm" class="admin-settings-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_fullname">Full Name</label>
                                <input type="text" id="admin_fullname" value="<?php echo $admin_name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_username">Login Username</label>
                                <input type="text" id="admin_username" value="<?php echo $username; ?>" required readonly style="opacity: 0.7; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_contact">Contact Number</label>
                                <input type="text" id="admin_contact" value="<?php echo $contact; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Official Email Address</label>
                                <input type="email" id="admin_email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>

                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                        <div class="panel-header" style="margin-bottom: 15px;">
                            <h3 class="section-title">Change Password</h3>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" placeholder="Leave blank to keep current">
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-profile">Save Profile Changes</button>
                    </form>
                </div>

            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Portal.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script src="../js/district_profile.js"></script>
</body>
</html>