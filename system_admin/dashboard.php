<?php
session_start();
require_once '../config/database.php';

// ── ROUTE GUARD (System Admin Only) ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$system_admin_name = htmlspecialchars($_SESSION['system_admin_name'] ?? $_SESSION['username'] ?? 'System Master Admin');

// ── METRIC 1: MANAGED DISTRICTS ───────────────────────────────────────────────
$res_dist = $conn->query("SELECT COUNT(DISTINCT district) AS cnt FROM district_admin WHERE district IS NOT NULL AND district != ''");
$total_districts = (int)($res_dist->fetch_assoc()['cnt'] ?? 0);

// ── METRIC 2: TOTAL PHCS ──────────────────────────────────────────────────────
$res_phcs = $conn->query("SELECT COUNT(*) AS cnt FROM phcs");
$total_phcs = (int)($res_phcs->fetch_assoc()['cnt'] ?? 0);

// ── METRIC 3: PENDING PHC PROPOSALS ───────────────────────────────────────────
$res_prop = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop->fetch_assoc()['cnt'] ?? 0);

// ── METRIC 4: DISTRICT ADMINISTRATORS ─────────────────────────────────────────
$res_da = $conn->query("SELECT COUNT(*) AS cnt FROM district_admin");
$total_district_admins = (int)($res_da->fetch_assoc()['cnt'] ?? 0);

// ── METRIC 5: TOTAL STAFF ACCOUNTS ────────────────────────────────────────────
$res_staff = $conn->query("SELECT COUNT(*) AS cnt FROM staff");
$total_staff_accounts = (int)($res_staff->fetch_assoc()['cnt'] ?? 0);

// ── DISTRICT INFRASTRUCTURE STATUS ────────────────────────────────────────────
$districts_query = "
    SELECT da.district_admin_id, da.district, da.admin_name, da.status,
           COUNT(p.phc_id) AS phc_count
    FROM district_admin da
    LEFT JOIN phcs p ON p.district_admin_id = da.district_admin_id OR p.district = da.district
    GROUP BY da.district_admin_id, da.district, da.admin_name, da.status
    ORDER BY da.district ASC
";
$districts_res = $conn->query($districts_query);
$districts_data = $districts_res ? $districts_res->fetch_all(MYSQLI_ASSOC) : [];

// ── RECENT INCOMING PHC PROPOSALS ─────────────────────────────────────────────
$recent_props_query = "
    SELECT r.*, da.admin_name
    FROM phc_creation_requests r
    LEFT JOIN district_admin da ON r.district_admin_id = da.district_admin_id
    ORDER BY r.submitted_date DESC
    LIMIT 5
";
$recent_props_res = $conn->query($recent_props_query);
$recent_props = $recent_props_res ? $recent_props_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Dashboard - Master Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
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
            <li class="active">
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i>
                    <span>System Dashboard</span>
                </a>
            </li>
            <li>
                <a href="districts.php">
                    <i class="fas fa-map-marked-alt"></i>
                    <span>Districts &amp; Admins</span>
                </a>
            </li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i>
                    <span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li>
                <a href="phc_directory.php">
                    <i class="fas fa-hospital"></i>
                    <span>Master PHC Directory</span>
                </a>
            </li>
            <li>
                <a href="medicines_master.php">
                    <i class="fas fa-pills"></i>
                    <span>Master Medicines</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-line"></i>
                    <span>System Analytics</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-user-cog"></i>
                    <span>Admin Profile</span>
                </a>
            </li>
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
                <h2>System Master Dashboard</h2>
                <p>Central oversight of all districts, health center proposals, and platform infrastructure</p>
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

        <!-- DASHBOARD CONTAINER -->
        <div class="dashboard-container">

            <!-- SUMMARY METRIC CARDS -->
            <div class="metric-cards-grid">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">
                        <i class="fas fa-map"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo number_format($total_districts); ?></h3>
                        <p>Managed Districts</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo number_format($total_phcs); ?></h3>
                        <p>Total Registered PHCs</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo number_format($pending_phc_requests); ?></h3>
                        <p>Pending PHC Requests</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo number_format($total_district_admins); ?></h3>
                        <p>District Administrators</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo number_format($total_staff_accounts); ?></h3>
                        <p>Total PHC Staff Accounts</p>
                    </div>
                </div>
            </div>

            <!-- 2-COLUMN SECTION: DISTRICTS OVERVIEW & RECENT DOCUMENT REQUESTS -->
            <div class="dashboard-grid-2col">
                
                <!-- Districts Overview Table -->
                <div class="data-panel" style="margin-bottom:0;">
                    <div class="panel-header">
                        <h3 class="section-title">District Infrastructure Status</h3>
                        <a href="districts.php" style="font-size: 12px; color: var(--accent-color);">Manage &rarr;</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>District Name</th>
                                <th>Assigned DHO / Admin</th>
                                <th>PHCs</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($districts_data)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 20px;">
                                        No district administrators configured yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($districts_data as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['district']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
                                        <td><?php echo number_format((int)$row['phc_count']); ?> PHCs</td>
                                        <td>
                                            <?php if ($row['status'] === 'Active'): ?>
                                                <span class="badge-status status-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-status status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent PHC Document Requests Preview -->
                <div class="data-panel" style="margin-bottom:0;">
                    <div class="panel-header">
                        <h3 class="section-title">Incoming PHC Proposals</h3>
                        <a href="phc_requests.php" style="font-size: 12px; color: var(--accent-color);">Review All &rarr;</a>
                    </div>
                    <div style="font-size: 13px;">
                        <?php if (empty($recent_props)): ?>
                            <p style="color: var(--text-secondary); padding: 15px 0; text-align: center;">
                                No PHC creation proposals received yet.
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_props as $prop): 
                                $status_class = match($prop['status']) {
                                    'Approved' => 'status-active',
                                    'Rejected' => 'status-inactive',
                                    default    => 'status-pending'
                                };
                            ?>
                                <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                        <strong><?php echo htmlspecialchars($prop['district']); ?> District</strong>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($prop['status']); ?>
                                        </span>
                                    </div>
                                    <p style="color:var(--text-secondary); margin:0 0 6px 0; font-size:12px;">
                                        <?php echo htmlspecialchars($prop['description']); ?>
                                    </p>
                                    <?php if (!empty($prop['document_path'])): ?>
                                        <a href="../<?php echo htmlspecialchars($prop['document_path']); ?>" target="_blank" style="color: var(--accent-color); font-size: 11px; text-decoration: underline;">
                                            <i class="fas fa-file-pdf"></i> View Attached Proposal
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. System Administration Master Portal.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
</body>
</html>