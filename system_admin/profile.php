<?php
session_start();
require_once '../config/database.php';

// ── ROUTE GUARD (System Admin Only) ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];

// ── SAFE MIGRATION CHECK FOR SYSTEM_ADMIN CONTACT & EMAIL COLUMNS ─────────────
$chk_col = $conn->query("SHOW COLUMNS FROM system_admin LIKE 'contact_no'");
if ($chk_col && $chk_col->num_rows === 0) {
    $conn->query("ALTER TABLE system_admin ADD COLUMN contact_no varchar(15) DEFAULT NULL, ADD COLUMN email varchar(100) DEFAULT NULL");
}

// ── HANDLE PROFILE UPDATE (AJAX / POST) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $fullname     = trim($_POST['admin_fullname'] ?? '');
    $contact      = trim($_POST['admin_contact'] ?? '');
    $email        = trim($_POST['admin_email'] ?? '');

    if (empty($fullname) || empty($contact) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required profile fields.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid official email address.']);
        exit();
    }

    $stmt_up = $conn->prepare("UPDATE system_admin SET admin_name = ?, contact_no = ?, email = ? WHERE admin_id = ?");
    $stmt_up->bind_param("sssi", $fullname, $contact, $email, $admin_id);

    if ($stmt_up->execute()) {
        $stmt_up->close();

        // Update active session name
        $_SESSION['system_admin_name'] = $fullname;

        echo json_encode([
            'success'    => true,
            'message'    => 'Root administrator profile updated successfully!',
            'admin_name' => htmlspecialchars($fullname),
            'contact'    => htmlspecialchars($contact),
            'email'      => htmlspecialchars($email)
        ]);
    } else {
        $stmt_up->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save profile changes: ' . $conn->error]);
    }
    exit();
}

// ── FETCH CURRENT ADMIN DATA ──────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM system_admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$system_admin_name = htmlspecialchars($admin_data['admin_name'] ?? $_SESSION['system_admin_name'] ?? 'System Master Admin');
$username          = htmlspecialchars($admin_data['username'] ?? $_SESSION['username'] ?? 'root_admin');
$contact           = htmlspecialchars($admin_data['contact_no'] ?? '1800-233-1004');
$email             = htmlspecialchars($admin_data['email'] ?? 'sysadmin.health@gujarat.gov.in');
$role              = 'Master System Administrator';
$clearance         = 'Root / Level-1 Full Access';

// ── PENDING PHC PROPOSALS BADGE ───────────────────────────────────────────────
$res_prop = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop->fetch_assoc()['cnt'] ?? 0);
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
            <li><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts &amp; Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>System Analytics</span></a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user-cog"></i><span>Admin Profile</span></a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-details">
                    <h4 id="sidebarAdminName"><?php echo $system_admin_name; ?></h4>
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

            <!-- NOTIFICATION BANNERS -->
            <div class="alert-banner success-banner" id="profileSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="profileBannerText">System admin profile information updated successfully!</span>
            </div>
            <div class="alert-banner error-banner" id="profileErrorBanner" style="display: none; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="profileErrorText">An error occurred while updating profile.</span>
            </div>

            <div class="profile-layout-grid">
                
                <!-- LEFT COLUMN: SYSTEM ADMIN IDENTITY CARD -->
                <div class="profile-identity-card">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <h3 id="cardAdminName"><?php echo $system_admin_name; ?></h3>
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
                                <input type="text" id="admin_fullname" name="admin_fullname" value="<?php echo $system_admin_name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_username">Root Username</label>
                                <input type="text" id="admin_username" name="admin_username" value="<?php echo $username; ?>" required readonly style="opacity: 0.7; cursor: not-allowed;" title="Root username cannot be modified">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_contact">Contact Helpline Number</label>
                                <input type="text" id="admin_contact" name="admin_contact" value="<?php echo $contact; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Official IT Cell Email</label>
                                <input type="email" id="admin_email" name="admin_email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>


                        <button type="submit" class="btn-submit-profile" id="saveSystemProfileBtn">Save Master Profile Changes</button>
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
        const profileForm   = document.getElementById("systemProfileForm");
        const successBanner = document.getElementById("profileSuccessBanner");
        const successText   = document.getElementById("profileBannerText");
        const errorBanner   = document.getElementById("profileErrorBanner");
        const errorText     = document.getElementById("profileErrorText");
        const submitBtn     = document.getElementById("saveSystemProfileBtn");

        if (profileForm) {
            profileForm.addEventListener("submit", (e) => {
                e.preventDefault();

                if (successBanner) successBanner.style.display = "none";
                if (errorBanner) errorBanner.style.display = "none";

                const origText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

                const formData = new FormData(profileForm);

                fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (successText) successText.textContent = data.message;
                        if (successBanner) {
                            successBanner.style.display = "flex";
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }

                        // Update dynamic names in UI
                        const cardName = document.getElementById("cardAdminName");
                        const sideName = document.getElementById("sidebarAdminName");
                        if (cardName && data.admin_name) cardName.textContent = data.admin_name;
                        if (sideName && data.admin_name) sideName.textContent = data.admin_name;


                        setTimeout(() => {
                            if (successBanner) successBanner.style.display = "none";
                        }, 4000);
                    } else {
                        if (errorText) errorText.textContent = data.message;
                        if (errorBanner) {
                            errorBanner.style.display = "flex";
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }
                })
                .catch(err => {
                    console.error("Save system profile error:", err);
                    if (errorText) errorText.textContent = "A server error occurred while updating profile.";
                    if (errorBanner) {
                        errorBanner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                });
            });
        }
    });
</script>
</body>
</html>