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

// ── PENDING PROPOSALS BADGE ───────────────────────────────────────────────────
$res_prop = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop ? $res_prop->fetch_assoc()['cnt'] : 0);

// ── DISTRICTS DROPDOWN LIST ───────────────────────────────────────────────────
$res_dist = $conn->query("SELECT DISTINCT district FROM district_admin WHERE district IS NOT NULL AND district != '' ORDER BY district ASC");
$districts = [];
if ($res_dist) {
    while ($row = $res_dist->fetch_assoc()) {
        $districts[] = $row['district'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statewide Analytics &amp; Reports - Master Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/district_reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li>
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
            <li class="active">
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
                <h2>Statewide Intelligence &amp; Reports</h2>
                <p id="reportSubtitle">Real-time health supply chain analytics and district performance overview</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme">
                    <i class="fas fa-sun"></i>
                </button>
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
                    <label>Select District Scope</label>
                    <select id="reportDistrictSelect" class="filter-select">
                        <option value="all">All Districts (Statewide)</option>
                        <?php foreach ($districts as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>">
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Report Category</label>
                    <select id="reportCategorySelect" class="filter-select">
                        <option value="overview">Executive Overview</option>
                        <option value="inventory">Inventory &amp; Stock Valuation</option>
                        <option value="usage">Medicine Consumption Trends</option>
                        <option value="visits">Patient Footfall Analytics</option>
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
                    <button type="button" class="btn-generate" id="generateReportBtn" style="background:#8b5cf6;">
                        <i class="fas fa-sync-alt"></i> Generate Report
                    </button>
                </div>
            </div>

            <!-- KPI SUMMARY METRICS -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
                <div class="data-panel" style="padding:16px; border-left:4px solid #8b5cf6;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-map" style="color:#8b5cf6; margin-right:6px;"></i>Districts Active</span>
                    <h3 id="kpiDistricts" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #0ea5e9;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-hospital" style="color:#0ea5e9; margin-right:6px;"></i>Registered PHCs</span>
                    <h3 id="kpiPhcs" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #10b981;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-users" style="color:#10b981; margin-right:6px;"></i>Patient Visits</span>
                    <h3 id="kpiVisits" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
                <div class="data-panel" style="padding:16px; border-left:4px solid #f59e0b;">
                    <span style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; font-weight:600;"><i class="fas fa-pills" style="color:#f59e0b; margin-right:6px;"></i>Units Dispensed</span>
                    <h3 id="kpiDispensed" style="font-size:24px; margin:8px 0 0 0; color:var(--text-primary);">--</h3>
                </div>
            </div>

            <!-- GRAPHICAL CHARTS SECTION -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="panel-header">
                        <h3 class="section-title" id="chart1Title">Medicine Consumption &amp; Demand Trends</h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="consumptionChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="panel-header">
                        <h3 class="section-title" id="chart2Title">District-wise Stock Distribution</h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="stockDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- DETAILED TABULAR BREAKDOWN -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title" id="tableReportTitle">District Performance &amp; Operational Overview</h3>
                    <span id="liveStatusBadge" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#10b981;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 6px #10b981;display:inline-block;"></span>
                        Live Synced <small id="lastUpdatedTime" style="color:var(--text-secondary);margin-left:4px;">(connecting...)</small>
                    </span>
                </div>
                <table class="data-table" id="reportDataTable">
                    <thead>
                        <tr>
                            <th>District Name</th>
                            <th>District Administrator</th>
                            <th>Registered PHCs</th>
                            <th>Patient Visits</th>
                            <th>Medicines Dispensed</th>
                            <th>Pending PHC Proposals</th>
                            <th>Infrastructure Status</th>
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-secondary);">
                                <i class="fas fa-sync-alt fa-spin"></i> Loading analytics data...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. Central Executive Analytics &amp; Statewide Reporting Module.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script src="../js/system_reports.js"></script>
</body>
</html>
