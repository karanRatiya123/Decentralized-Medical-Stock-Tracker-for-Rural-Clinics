<?php
session_start();

$phc_name = $phc_name ?? '';
$taluka = $taluka ?? '';
$district = $district ?? '';
$staff_name = $staff_name ?? '';
$staff_id = $staff_id ?? '';
$staff_email = $staff_email ?? '';
$staff_phone = $staff_phone ?? '';
$staff_role = $staff_role ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. FIXED SIDEBAR NAVIGATION
         ============================== -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-clinic-medical brand-icon"></i>
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>Tracker</p>
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
        <a href="patients.php">
            <i class="fas fa-users"></i>
            <span>Patients</span>
        </a>
    </li>
    <li>
        <a href="medicine_issue.php">
            <i class="fas fa-pills"></i>
            <span>Medicine Issue</span>
        </a>
    </li>
    <li>
        <a href="inventory.php">
            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>
        </a>
    </li>
    <li>
        <a href="stock_in.php">
            <i class="fas fa-arrow-down"></i>
            <span>Stock Received</span>
        </a>
    </li>
    <li>
        <a href="medicine_requests.php">
            <i class="fas fa-file-invoice"></i>
            <span>Medicine Requests</span>
        </a>
    </li>
    <li>
        <a href="doctors.php">
            <i class="fas fa-user-md"></i>
            <span>Doctors</span>
        </a>
    </li>
    <li>
        <a href="reports.php">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
    </li>
    <li>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </li>
</ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $staff_name; ?></h4>
                    <p>Role: PHC Staff</p>
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
                <h2>Staff Profile & Settings</h2>
                <p><?php echo $phc_name; ?> - Manage Account Details</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Settings...">
                </div>
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
                <span>Profile details updated successfully!</span>
            </div>

            <!-- PROFILE GRID -->
            <div class="profile-grid">
                
                <!-- LEFT CARD: Staff Identity & PHC Details -->
                <div class="profile-card">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3><?php echo $staff_name; ?></h3>
                    <p class="profile-role-tag"><?php echo $staff_role; ?></p>
                    
                    <div class="profile-meta-list">
                        <div class="meta-item">
                            <span class="meta-label"><i class="fas id-badge"></i> Staff ID:</span>
                            <span class="meta-value"><?php echo $staff_id; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label"><i class="fas fa-hospital"></i> Assigned PHC:</span>
                            <span class="meta-value"><?php echo $phc_name; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label"><i class="fas fa-map-marker-alt"></i> Location:</span>
                            <span class="meta-value"><?php echo $taluka; ?>, <?php echo $district; ?></span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT CARD: Edit Profile & Password Form -->
                <div class="profile-card">
                    <div class="panel-header" style="margin-bottom: 20px;">
                        <h3 class="section-title">Edit Account Information</h3>
                    </div>

                    <form id="profileForm" class="profile-form">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" value="<?php echo $staff_name; ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" value="<?php echo $staff_email; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Mobile Number</label>
                                <input type="text" id="phone" value="<?php echo $staff_phone; ?>" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label for="currentPassword">Current Password (Required for changes)</label>
                            <input type="password" id="currentPassword" placeholder="********" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="newPassword">New Password (Optional)</label>
                                <input type="password" id="newPassword" placeholder="Leave blank to keep current">
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input type="password" id="confirmPassword" placeholder="Confirm password">
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-profile">Save Changes</button>
                    </form>
                </div>

            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
            <p>Developed for Rural PHCs</p>
        </footer>

    </main>
</div>

<!-- Scripts -->
<script src="../js/theme.js"></script>
<script src="../js/profile.js"></script>
</body>
</html>