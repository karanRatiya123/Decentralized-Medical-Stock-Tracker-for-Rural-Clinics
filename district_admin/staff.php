<?php
session_start();
require_once '../config/database.php';
// Session verification (District Admin Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    header("Location: ../login.php");
    exit();
}
$admin_id   = (int)($_SESSION['user_id'] ?? 0);
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'District Admin');
$district   = htmlspecialchars($_SESSION['district'] ?? 'District HQ');
$success_msg = '';
$error_msg   = '';
// =========================================================================
// 1. HANDLE CREATE NEW STAFF ACCOUNT (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $staff_name = trim($_POST['staff_name'] ?? '');
    $phc_id     = (int)($_POST['phc_id'] ?? 0);
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $shift      = trim($_POST['shift'] ?? 'Morning');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $status     = trim($_POST['status'] ?? 'Active');
    if (!empty($staff_name) && $phc_id > 0 && !empty($username) && !empty($password)) {
        // Check if username is already taken
        $stmt_check = $conn->prepare("SELECT staff_id FROM staff WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error_msg = "Username '{$username}' is already in use. Please choose another username.";
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_add = $conn->prepare("
                INSERT INTO staff (phc_id, staff_name, username, password, shift, contact_no, joining_date, status)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ");
            $stmt_add->bind_param("issssss", $phc_id, $staff_name, $username, $hashed_password, $shift, $contact_no, $status);
            if ($stmt_add->execute()) {
                $new_id = $stmt_add->insert_id;
                $success_msg = "New PHC staff account (#S" . str_pad($new_id, 3, '0', STR_PAD_LEFT) . ") created successfully for {$staff_name}!";
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
            $stmt_add->close();
        }
        $stmt_check->close();
    } else {
        $error_msg = "Please fill in all required fields (Staff Name, Assigned PHC, Username, and Password).";
    }
}

// =========================================================================
// 2. FETCH PHC CENTERS IN THIS DISTRICT (FOR MODAL DROPDOWN)
// =========================================================================
$stmt_phcs = $conn->prepare("
    SELECT phc_id, phc_name, Taluka 
    FROM phcs 
    WHERE district = ? OR district_admin_id = ?
    ORDER BY phc_name ASC
");
$stmt_phcs->bind_param("si", $district, $admin_id);
$stmt_phcs->execute();
$phcs_list = $stmt_phcs->get_result();
// =========================================================================
// 3. FETCH STAFF DIRECTORY (WITH SEARCH FILTER)
// =========================================================================
$search = trim($_GET['search'] ?? '');
$sql_staff = "
    SELECT s.*, p.phc_name, p.Taluka
    FROM staff s
    JOIN phcs p ON s.phc_id = p.phc_id
    WHERE (p.district = ? OR p.district_admin_id = ?)
";
if (!empty($search)) {
    $sql_staff .= " AND (s.staff_name LIKE ? OR s.username LIKE ? OR p.phc_name LIKE ?) ";
}
$sql_staff .= " ORDER BY s.staff_id DESC";
$stmt_staff = $conn->prepare($sql_staff);
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_staff->bind_param("sisss", $district, $admin_id, $search_param, $search_param, $search_param);
} else {
    $stmt_staff->bind_param("si", $district, $admin_id);
}
$stmt_staff->execute();
$staff_result = $stmt_staff->get_result();
$total_staff_count = $staff_result->num_rows;

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-layout">
    <!-- DISTRICT ADMIN SIDEBAR -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-shield-alt brand-icon"></i>
            <div class="brand-text"><h3>MedStock</h3><p>District Admin</p></div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="phcs.php"><i class="fas fa-hospital"></i><span>PHC Centers</span></a></li>
            <li class="active"><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>District Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-shield"></i><span>Admin Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <div class="user-details"><h4><?php echo $admin_name; ?></h4><p>District: <?php echo $district; ?></p></div>
            </div>
            <a href="../login.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </nav>
    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>PHC Staff Accounts Management</h2>
                <p>Create and manage login accounts for PHC Staff across <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display"><i class="far fa-calendar-alt"></i><span><?php echo date("d M Y, h:i A"); ?></span></div>
            </div>
        </header>
        <div class="dashboard-container">
            <!-- Alert Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display:flex; background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#10b981; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display:flex; background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#ef4444; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle"></i><span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>
            <!-- ACTION BAR -->
            <div class="page-action-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:15px; flex-wrap:wrap;">
                <form action="staff.php" method="GET" style="display:flex; gap:10px; flex:1; max-width:450px;">
                    <div class="search-box" style="flex:1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Staff Name, Username or PHC..." style="width:100%; padding:10px 14px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; font-size:13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding:10px 18px; border:none; background:var(--accent-color, #0ea5e9); color:#fff; border-radius:6px; cursor:pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="staff.php" style="padding:10px 14px; background:var(--bg-card, #1e293b); border:1px solid var(--border-color); color:var(--text-secondary); border-radius:6px; text-decoration:none; font-size:13px;">Clear</a>
                    <?php endif; ?>
                </form>
                <!-- Unbreakable Modal Open Button -->
                <button type="button" class="btn-primary-action" 
                    onclick="document.getElementById('staffModal').style.cssText = 'display:flex !important; opacity:1 !important; visibility:visible !important; position:fixed !important; top:0 !important; left:0 !important; width:100vw !important; height:100vh !important; background:rgba(0,0,0,0.8) !important; z-index:999999 !important; justify-content:center !important; align-items:center !important;'"
                    style="padding:10px 18px; border:none; background:var(--accent-color, #0ea5e9); color:#fff; border-radius:6px; font-weight:bold; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-user-plus"></i> Add New PHC Staff
                </button>
            </div>
            <!-- STAFF DIRECTORY TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom:15px;">
                    <h3 class="section-title">Registered PHC Staff Directory (<?php echo $total_staff_count; ?> Staff Members)</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Staff Name</th>
                            <th>Assigned PHC Center</th>
                            <th>Username / Login ID</th>
                            <th>Contact Phone</th>
                            <th>Duty Shift</th>
                            <th>Joining Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($staff_result && $staff_result->num_rows > 0): ?>
                            <?php while ($s = $staff_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#S<?php echo str_pad($s['staff_id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($s['staff_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($s['phc_name']); ?> <small style="color:var(--text-secondary);">(<?php echo htmlspecialchars($s['Taluka']); ?>)</small></td>
                                    <td><code><?php echo htmlspecialchars($s['username']); ?></code></td>
                                    <td><?php echo htmlspecialchars($s['contact_no'] ?? '-'); ?></td>
                                    <td><span style="font-size:12px; padding:3px 8px; border-radius:4px; background:rgba(14,165,233,0.15); color:#0ea5e9; font-weight:bold;"><?php echo htmlspecialchars($s['shift']); ?> Shift</span></td>
                                    <td><?php echo date("d M Y", strtotime($s['joining_date'] ?? 'now')); ?></td>
                                    <td>
                                        <span class="badge-status" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; <?php echo ($s['status'] === 'Active') ? 'background:rgba(16,185,129,0.15); color:#10b981; border:1px solid #10b981;' : 'background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid #ef4444;'; ?>">
                                            <?php echo htmlspecialchars($s['status'] ?? 'Active'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:25px;">No staff accounts found. Click "Add New PHC Staff" to register one.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. District Administration Portal.</p></footer>
    </main>
</div>
<!-- ==============================
     ADD NEW PHC STAFF MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="staffModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:999999;">
    <div class="modal-card" style="background:var(--bg-card, #1e293b); border:1px solid var(--border-color, #334155); border-radius:10px; width:100%; max-width:500px; padding:25px; color:var(--text-primary, #fff); box-shadow:0 10px 30px rgba(0,0,0,0.6);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Create PHC Staff Account</h3>
            <button type="button" class="modal-close-btn" 
                onclick="document.getElementById('staffModal').style.cssText = 'display:none !important; opacity:0 !important; visibility:hidden !important;'"
                style="background:none; border:none; font-size:26px; color:var(--text-secondary); cursor:pointer;">&times;</button>
        </div>
        <form action="staff.php" method="POST" class="modal-form">
            <input type="hidden" name="action" value="add_staff">
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Staff Full Name *</label>
                <input type="text" name="staff_name" required placeholder="e.g. Dr. Anjali Sharma" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Assign to PHC Center *</label>
                <select name="phc_id" required style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                    <option value="">-- Choose PHC Center --</option>
                    <?php if ($phcs_list && $phcs_list->num_rows > 0): ?>
                        <?php while ($phc = $phcs_list->fetch_assoc()): ?>
                            <option value="<?php echo $phc['phc_id']; ?>">
                                <?php echo htmlspecialchars($phc['phc_name']); ?> (<?php echo htmlspecialchars($phc['Taluka']); ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Login Username *</label>
                    <input type="text" name="username" required placeholder="e.g. staff_veraval" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Account Password *</label>
                    <input type="password" name="password" required placeholder="Enter password" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Duty Shift *</label>
                    <select name="shift" required style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                        <option value="Morning">Morning Shift</option>
                        <option value="Evening">Evening Shift</option>
                        <option value="Night">Night Shift</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Contact Mobile</label>
                    <input type="tel" name="contact_no" placeholder="e.g. 9876543210" pattern="[0-9]{10}" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Account Status</label>
                <select name="status" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" style="width:100%; padding:12px; background:var(--accent-color, #0ea5e9); color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-user-check"></i> Create Staff Account
            </button>
        </form>
    </div>
</div>
<script src="../js/theme.js"></script>
</body>
</html>
<?php
$stmt_phcs->close();
$stmt_staff->close();
?>