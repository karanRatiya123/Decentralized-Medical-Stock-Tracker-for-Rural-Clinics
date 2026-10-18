<?php
session_start();
require_once '../config/database.php';

// ── ROUTE GUARD ───────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'district_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = (int)$_SESSION['user_id'];
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'District Admin');
$district   = htmlspecialchars($_SESSION['district']   ?? 'District HQ');

// ── PENDING BADGE ─────────────────────────────────────────────────────────────
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

// ── PHC DROPDOWN LIST ─────────────────────────────────────────────────────────
$stmt_phcs = $conn->prepare("
    SELECT phc_id, phc_name
    FROM phcs
    WHERE district_admin_id = ? OR district = ?
    ORDER BY phc_name ASC
");
$stmt_phcs->bind_param("is", $admin_id, $district);
$stmt_phcs->execute();
$phc_list = $stmt_phcs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_phcs->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>District Reports &amp; Analytics - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/district_reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="phcs.php"><i class="fas fa-hospital"></i><span>PHC Centers</span></a></li>
            <li><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li>
                <a href="medicine_requests.php">
                    <i class="fas fa-file-invoice"></i><span>Medicine Requests</span>
                    <span class="badge" style="background:#f59e0b;color:#fff;padding:2px 6px;border-radius:10px;font-size:10px;float:right;"><?php echo $pending_requests; ?></span>
                </a>
            </li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts &amp; Expiry</span></a></li>
            <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i><span>District Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-shield"></i><span>Admin Profile</span></a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <div class="user-details">
                    <h4><?php echo $admin_name; ?></h4>
                    <p>District: <?php echo $district; ?></p>
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
                <h2>District Analytics &amp; Reports</h2>
                <p id="reportSubtitle">Comprehensive health intelligence and supply chain metrics for <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <button type="button" class="btn-pdf-export" id="exportPdfBtn">
                    <i class="fas fa-file-pdf"></i> Download PDF Report
                </button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="dashboard-container" id="printableArea">

            <!-- REPORT FILTER CONTROLS -->
            <div class="report-filter-bar">
                <div class="filter-field">
                    <label>Select PHC Center</label>
                    <select id="reportPhcSelect" class="filter-select">
                        <option value="0">All PHCs (District Aggregate)</option>
                        <?php foreach ($phc_list as $phc): ?>
                            <option value="<?php echo (int)$phc['phc_id']; ?>">
                                <?php echo htmlspecialchars($phc['phc_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Report Category</label>
                    <select id="reportCategorySelect" class="filter-select">
                        <option value="overview">Comprehensive Overview</option>
                        <option value="inventory">Inventory &amp; Stock Valuation</option>
                        <option value="usage">Medicine Consumption &amp; Usage</option>
                        <option value="visits">Patient Visit Statistics</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Time Period</label>
                    <select id="reportPeriodSelect" class="filter-select">
                        <option value="month">Current Month (<?php echo date('F Y'); ?>)</option>
                        <option value="quarter">Last 3 Months</option>
                        <option value="year">Year to Date (<?php echo date('Y'); ?>)</option>
                    </select>
                </div>
                <div class="filter-field" style="display:flex;align-items:flex-end;">
                    <button type="button" class="btn-generate" id="generateReportBtn">
                        <i class="fas fa-sync-alt"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- KPI SUMMARY METRICS -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
                <div class="data-panel" style="padding:16px; border-left:4px solid #0ea5e9;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-users" style="color:#0ea5e9; margin-right:6px;"></i>Patient Visits</span>
                    <h3 id="kpiVisits" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #10b981;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-pills" style="color:#10b981; margin-right:6px;"></i>Units Dispensed</span>
                    <h3 id="kpiDispensed" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #8b5cf6;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-boxes" style="color:#8b5cf6; margin-right:6px;"></i>Stock In Batches</span>
                    <h3 id="kpiBatches" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #f59e0b;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-clock" style="color:#f59e0b; margin-right:6px;"></i>Pending Requests</span>
                    <h3 id="kpiRequests" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
            </div>

            <!-- GRAPHICAL CHARTS SECTION -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="panel-header">
                        <h3 class="section-title">Monthly Medicine Consumption Trends</h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="consumptionChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="panel-header">
                        <h3 class="section-title">PHC-wise Stock Level Comparison</h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="phcComparisonChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- DETAILED TABULAR BREAKDOWN -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title" id="tableReportTitle">District Performance Summary Table</h3>
                    <span id="liveStatusBadge" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#10b981;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 6px #10b981;display:inline-block;"></span>
                        Live Synced <small id="lastUpdatedTime" style="color:var(--text-secondary);margin-left:4px;">(connecting...)</small>
                    </span>
                </div>
                <table class="data-table" id="reportDataTable">
                    <thead>
                        <tr>
                            <th>PHC Name</th>
                            <th>Total Patient Visits</th>
                            <th>Medicines Dispensed</th>
                            <th>Stock Received (Batches)</th>
                            <th>Active Requisitions</th>
                            <th>Efficiency Score</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <tr>
                            <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-secondary);">
                                <i class="fas fa-sync-alt fa-spin"></i> Loading report data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Analytics &amp; Reporting Module.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script src="../js/district_reports.js"></script>
</body>
</html>