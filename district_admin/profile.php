<?php
session_start();
require_once '../config/database.php';

// ── ROUTE GUARD (District Admin Only) ─────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];

// ── HANDLE PROFILE UPDATE (AJAX / POST) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $fullname     = trim($_POST['admin_fullname'] ?? '');
    $contact      = trim($_POST['admin_contact'] ?? '');
    $email        = trim($_POST['admin_email'] ?? '');

    if (empty($fullname) || empty($contact) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit();
    }

    $stmt_up = $conn->prepare("UPDATE district_admin SET admin_name = ?, contact_no = ?, email = ? WHERE district_admin_id = ?");
    $stmt_up->bind_param("sssi", $fullname, $contact, $email, $admin_id);

    if ($stmt_up->execute()) {
        $stmt_up->close();

        // Update active session variables
        $_SESSION['admin_name'] = $fullname;
        $_SESSION['contact_no'] = $contact;
        $_SESSION['email']      = $email;

        echo json_encode([
            'success'    => true,
            'message'    => 'Profile details updated successfully in database!',
            'admin_name' => htmlspecialchars($fullname),
            'contact'    => htmlspecialchars($contact),
            'email'      => htmlspecialchars($email)
        ]);
    } else {
        $stmt_up->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save changes to database. Please try again.']);
    }
    exit();
}

// ── FETCH CURRENT ADMIN DATA FROM DATABASE ────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM district_admin WHERE district_admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = htmlspecialchars($admin_data['admin_name'] ?? $_SESSION['admin_name'] ?? 'District Admin');
$username   = htmlspecialchars($admin_data['username'] ?? $_SESSION['username'] ?? '');
$district   = htmlspecialchars($admin_data['district'] ?? $_SESSION['district'] ?? 'District HQ');
$contact    = htmlspecialchars($admin_data['contact_no'] ?? $_SESSION['contact_no'] ?? '');
$email      = htmlspecialchars($admin_data['email'] ?? $_SESSION['email'] ?? '');
$role       = 'District Administrator';

// ── PENDING REQUESTS BADGE ────────────────────────────────────────────────────
$stmt_pend = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM medicine_requests mr
    JOIN phcs p ON mr.phc_id = p.phc_id
    WHERE (p.district = ? OR p.district_admin_id = ?)
      AND mr.status = 'Pending'
");
$stmt_pend->bind_param("si", $district, $admin_id);
$stmt_pend->execute();
$pending_requests = (int)($stmt_pend->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_pend->close();
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
                    <span>Alerts &amp; Expiry</span>
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
                    <h4 id="sidebarAdminName"><?php echo $admin_name; ?></h4>
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

            <!-- NOTIFICATION BANNERS -->
            <div class="alert-banner success-banner" id="profileSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="profileBannerText">Profile information updated successfully!</span>
            </div>

            <div class="alert-banner error-banner" id="profileErrorBanner" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="profileErrorText">An error occurred while updating profile.</span>
            </div>

            <div class="profile-layout-grid">
                
                <!-- LEFT COLUMN: ADMIN IDENTITY CARD -->
                <div class="profile-identity-card">
                    <div class="profile-avatar-large">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 id="cardAdminName"><?php echo $admin_name; ?></h3>
                    <p class="role-badge"><?php echo $role; ?></p>
                    <p class="district-info"><i class="fas fa-map-marker-alt"></i> District Jurisdiction: <strong><?php echo $district; ?></strong></p>
                    
                    <div class="security-note">
                        <i class="fas fa-lock"></i>
                        <span>District-level access granted for medical inventory and PHC clinic governance.</span>
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
                                <input type="text" id="admin_fullname" name="admin_fullname" value="<?php echo $admin_name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_username">Login Username</label>
                                <input type="text" id="admin_username" name="admin_username" value="<?php echo $username; ?>" required readonly style="opacity: 0.7; cursor: not-allowed;" title="Username cannot be modified">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="admin_contact">Contact Number</label>
                                <input type="text" id="admin_contact" name="admin_contact" value="<?php echo $contact; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Official Email Address</label>
                                <input type="email" id="admin_email" name="admin_email" value="<?php echo $email; ?>" required>
                            </div>
                        </div>


                        <button type="submit" class="btn-submit-profile" id="saveProfileBtn">Save Profile Changes</button>
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