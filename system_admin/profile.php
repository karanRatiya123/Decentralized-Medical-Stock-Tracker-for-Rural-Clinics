<?php
session_start();

$system_admin_name = $system_admin_name ?? '';
$username = $username ?? '';
$contact = $contact ?? '';
$email = $email ?? '';
$role = $role ?? '';
$clearance = $clearance ?? '';
$pending_phc_requests = $pending_phc_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. SYSTEM ADMIN SIDEBAR
         ============================== -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-server brand-icon" style="color: #8b5cf6;"></i>
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>System Master Admin</p>
            </div>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>System Dashboard</span></a></li>
            <li><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts & Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user-cog"></i><span>Admin Profile</span></a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $system_admin_name; ?></h4>
                    <p>Access Level: Root</p>
                </div>
            </div>
            <a href="../login.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </nav>

    <!-- ==============================
         2. MAIN CONTENT AREA
         ============================== -->
    <main class="main-content">
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>System Administrator Profile</h2>
                <p>Manage root credentials, security keys, and platform-wide administrative settings</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
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
                <span id="profileBannerText">System admin profile information updated successfully!</span>
            </div>

            <div class="profile-layout-grid">
                
                <!-- LEFT COLUMN: SYSTEM ADMIN IDENTITY CARD -->
                <div class="profile-identity-card">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3><?php echo $system_admin_name; ?></h3>
                    <p class="role-badge"><?php echo $role; ?></p>
                    <p class="district-info"><i class="fas fa-shield-alt"></i> Security Clearance: <strong><?php echo $clearance; ?></strong></p>
                    
                    <div class="security-note">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Root access allows global database management, district allocation, and master catalog control. Keep credentials secure.</span>
                    </div>
                </div>

                <!-- RIGHT COLUMN: EDIT PROFILE & PASSWORD FORM -->
                <div class="profile-form-panel">
                    <div class="panel-header">
                        <h3 class="section-title">Edit Root Administrative Details</h3>
                    </div>
                    <form id="systemProfileForm" class="admin-settings-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_fullname">Master Administrator Name</label>
                                <input type="text" id="admin_fullname" value="<?php echo $system_admin_name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_username">Root Username</label>
                                <input type="text" id="admin_username" value="<?php echo $username; ?>" required readonly style="opacity: 0.7; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_contact">Contact Helpline Number</label>
                                <input type="text" id="admin_contact" value="<?php echo $contact; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Official IT Cell Email</label>
                                <input type="email" id="admin_email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>

                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

                        <div class="panel-header" style="margin-bottom: 15px;">
                            <h3 class="section-title">Change Master Security Password</h3>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Master Password</label>
                                <input type="password" id="current_password" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Master Password</label>
                                <input type="password" id="new_password" placeholder="Leave blank to keep current">
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-profile">Save Master Profile Changes</button>
                    </form>
                </div>

            </div>

        </div> <!-- End Dashboard Container -->
        
        <footer class="main-footer" style="display:block; padding: 20px 0; text-align: center; color: var(--text-secondary); font-size: 12px;">
            <p>&copy; 2026 MedStock Tracker. System Administration Portal.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const profileForm = document.getElementById("systemProfileForm");
        const successBanner = document.getElementById("profileSuccessBanner");

        if (profileForm) {
            profileForm.addEventListener("submit", (e) => {
                e.preventDefault();
                if (successBanner) {
                    successBanner.style.display = "flex";
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(() => {
                        successBanner.style.display = "none";
                    }, 4000);
                }
            });
        }
    });
</script>
</body>
</html>