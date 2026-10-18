<?php
session_start();
require_once '../config/database.php';

// Session verification (District Admin Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);

// Verify account is still active
$stmt_act = $conn->prepare("SELECT status FROM district_admin WHERE district_admin_id = ?");
$stmt_act->bind_param("i", $admin_id);
$stmt_act->execute();
$act_row = $stmt_act->get_result()->fetch_assoc();
$stmt_act->close();
if ($act_row && strcasecmp($act_row['status'], 'Inactive') === 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=deactivated");
    exit();
}

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'District Admin');
$district   = htmlspecialchars($_SESSION['district'] ?? 'District HQ');

// =========================================================================
// 1. FETCH DISTRICT-WIDE METRICS
// =========================================================================

// Metric 1: Total PHC Centers
$stmt_phc = $conn->prepare("SELECT COUNT(*) AS cnt FROM phcs WHERE district = ? OR district_admin_id = ?");
$stmt_phc->bind_param("si", $district, $admin_id);
$stmt_phc->execute();
$total_phcs = (int)($stmt_phc->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_phc->close();

// Metric 2: Total Staff Accounts
$stmt_staff = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM staff s 
    JOIN phcs p ON s.phc_id = p.phc_id 
    WHERE p.district = ? OR p.district_admin_id = ?
");
$stmt_staff->bind_param("si", $district, $admin_id);
$stmt_staff->execute();
$total_staff = (int)($stmt_staff->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_staff->close();

// Metric 3: Associated Doctors
$stmt_doc = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM doctors d 
    JOIN phcs p ON d.phc_id = p.phc_id 
    WHERE p.district = ? OR p.district_admin_id = ?
");
$stmt_doc->bind_param("si", $district, $admin_id);
$stmt_doc->execute();
$total_doctors = (int)($stmt_doc->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_doc->close();

// Metric 4: Pending Medicine Requests
$stmt_req = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM medicine_requests mr 
    JOIN phcs p ON mr.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND mr.status = 'Pending'
");
$stmt_req->bind_param("si", $district, $admin_id);
$stmt_req->execute();
$pending_requests = (int)($stmt_req->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_req->close();

// Metric 5: Low Stock Items across District (Quantity <= 20)
$stmt_low = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND i.quantity <= 20
");
$stmt_low->bind_param("si", $district, $admin_id);
$stmt_low->execute();
$low_stock_count = (int)($stmt_low->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_low->close();

// =========================================================================
// 2. FETCH PHC CENTERS OVERVIEW (Top 5)
// =========================================================================
$stmt_phc_list = $conn->prepare("
    SELECT p.phc_id, p.phc_name, p.Taluka, p.village, p.status,
           (SELECT COUNT(*) FROM staff WHERE phc_id = p.phc_id) AS staff_count
    FROM phcs p
    WHERE p.district = ? OR p.district_admin_id = ?
    ORDER BY p.phc_name ASC
    LIMIT 6
");
$stmt_phc_list->bind_param("si", $district, $admin_id);
$stmt_phc_list->execute();
$phc_overview = $stmt_phc_list->get_result();

// =========================================================================
// 3. FETCH RECENT MEDICINE REQUESTS STREAM (Top 5)
// =========================================================================
$stmt_recent_req = $conn->prepare("
    SELECT mr.request_id, mr.requested_quantity, mr.request_date, mr.status,
           p.phc_name, m.medicine_name
    FROM medicine_requests mr
    JOIN phcs p ON mr.phc_id = p.phc_id
    JOIN medicines m ON mr.medicine_id = m.medicine_id
    WHERE p.district = ? OR p.district_admin_id = ?
    ORDER BY mr.request_date DESC
    LIMIT 5
");
$stmt_recent_req->bind_param("si", $district, $admin_id);
$stmt_recent_req->execute();
$recent_requests = $stmt_recent_req->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>District Dashboard - Admin Portal - MedStock Tracker</title>
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
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>District Admin</p>
            </div>
        </div>
        
        <ul class="nav-links">
            <li class="active">
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
                    <?php if ($pending_requests > 0): ?>
                        <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_requests; ?></span>
                    <?php endif; ?>
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
            <li>
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

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>District Monitoring Dashboard</h2>
                <p>Overview of Primary Health Centers across <?php echo $district; ?> District</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- SUMMARY METRIC CARDS -->
            <div class="metric-cards-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-bottom:25px;">
                <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border-color); padding:20px; border-radius:10px; display:flex; align-items:center; gap:15px;">
                    <div class="metric-icon" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="metric-info">
                        <h3 style="font-size:24px; margin:0;"><?php echo $total_phcs; ?></h3>
                        <p style="color:var(--text-secondary); margin:3px 0 0 0; font-size:12px;">Total PHC Centers</p>
                    </div>
                </div>

                <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border-color); padding:20px; border-radius:10px; display:flex; align-items:center; gap:15px;">
                    <div class="metric-icon" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-info">
                        <h3 style="font-size:24px; margin:0;"><?php echo $total_staff; ?></h3>
                        <p style="color:var(--text-secondary); margin:3px 0 0 0; font-size:12px;">PHC Staff Accounts</p>
                    </div>
                </div>

                <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border-color); padding:20px; border-radius:10px; display:flex; align-items:center; gap:15px;">
                    <div class="metric-icon" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="metric-info">
                        <h3 style="font-size:24px; margin:0;"><?php echo $total_doctors; ?></h3>
                        <p style="color:var(--text-secondary); margin:3px 0 0 0; font-size:12px;">Associated Doctors</p>
                    </div>
                </div>

                <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border-color); padding:20px; border-radius:10px; display:flex; align-items:center; gap:15px;">
                    <div class="metric-icon" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="metric-info">
                        <h3 style="font-size:24px; margin:0;"><?php echo $pending_requests; ?></h3>
                        <p style="color:var(--text-secondary); margin:3px 0 0 0; font-size:12px;">Pending Requisitions</p>
                    </div>
                </div>

                <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border-color); padding:20px; border-radius:10px; display:flex; align-items:center; gap:15px;">
                    <div class="metric-icon" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-info">
                        <h3 style="font-size:24px; margin:0;"><?php echo $low_stock_count; ?></h3>
                        <p style="color:var(--text-secondary); margin:3px 0 0 0; font-size:12px;">Low Stock Alerts</p>
                    </div>
                </div>
            </div>

            <!-- 2-COLUMN GRID -->
            <div class="dashboard-grid-2col" style="display:grid; grid-template-columns:1.5fr 1fr; gap:25px;">
                <!-- PHC Centers Overview Table -->
                <div class="data-panel">
                    <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 class="section-title">PHC Centers Overview</h3>
                        <a href="phcs.php" style="font-size: 12px; color: var(--accent-color); text-decoration:none; font-weight:bold;">View All &rarr;</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PHC Name</th>
                                <th>Taluka / Village</th>
                                <th>Staff Count</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($phc_overview && $phc_overview->num_rows > 0): ?>
                                <?php while ($phc = $phc_overview->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($phc['phc_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($phc['Taluka'] ?: $phc['village'] ?: '-'); ?></td>
                                        <td><span style="font-weight:600;"><?php echo (int)$phc['staff_count']; ?> Staff</span></td>
                                        <td>
                                            <span class="badge-status" style="padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold; <?php echo ($phc['status'] === 'Active') ? 'background:rgba(16,185,129,0.15); color:#10b981;' : 'background:rgba(239,68,68,0.15); color:#ef4444;'; ?>">
                                                <?php echo htmlspecialchars($phc['status'] ?? 'Active'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; color:var(--text-secondary); padding:20px;">No PHCs registered in <?php echo $district; ?> yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Requisitions Feed -->
                <div class="data-panel">
                    <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 class="section-title">Recent Requisitions</h3>
                        <a href="medicine_requests.php" style="font-size: 12px; color: var(--accent-color); text-decoration:none; font-weight:bold;">Manage &rarr;</a>
                    </div>
                    <div>
                        <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                            <?php while ($req = $recent_requests->fetch_assoc()): ?>
                                <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                        <strong><?php echo htmlspecialchars($req['phc_name']); ?></strong>
                                        <span class="badge-status" style="font-size:10px; padding:2px 6px; border-radius:10px; font-weight:bold; <?php echo ($req['status'] === 'Approved') ? 'background:rgba(16,185,129,0.15); color:#10b981;' : (($req['status'] === 'Rejected') ? 'background:rgba(239,68,68,0.15); color:#ef4444;' : 'background:rgba(245,158,11,0.15); color:#f59e0b;'); ?>">
                                            <?php echo htmlspecialchars($req['status']); ?>
                                        </span>
                                    </div>
                                    <p style="color:var(--text-secondary); margin:0; font-size:12px;">
                                        <?php echo htmlspecialchars($req['medicine_name']); ?> (<?php echo (int)$req['requested_quantity']; ?> Units)
                                    </p>
                                    <small style="color:var(--text-secondary); font-size:11px;"><?php echo date("d M Y, h:i A", strtotime($req['request_date'])); ?></small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color:var(--text-secondary); font-size:13px; text-align:center; padding:25px;">No medicine requests submitted yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. District Administration Portal.</p></footer>
    </main>
</div>

<script src="../js/theme.js"></script>
</body>
</html>
<?php
$stmt_phc_list->close();
$stmt_recent_req->close();
?>