<?php
session_start();

$admin_name = $admin_name ?? '';
$district = $district ?? '';
$pending_requests = $pending_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>District Reports & Analytics - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/district_reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN for Graphical Representation -->
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
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_requests; ?></span>
                </a>
            </li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
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
                <h2>District Analytics & Reports</h2>
                <p>Comprehensive health intelligence and supply chain metrics for <?php echo $district; ?></p>
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
                        <option value="all">All PHCs (District Aggregate)</option>
                        <!-- Dynamic PHC options will load here -->
                        <!--
                        <option value="veraval">PHC Veraval</option>
                        -->
                    </select>
                </div>
                <div class="filter-field">
                    <label>Report Category</label>
                    <select id="reportCategorySelect" class="filter-select">
                        <option value="overview">Comprehensive Overview</option>
                        <option value="inventory">Inventory & Stock Valuation</option>
                        <option value="usage">Medicine Consumption & Usage</option>
                        <option value="visits">Patient Visit Statistics</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Time Period</label>
                    <select id="reportPeriodSelect" class="filter-select">
                        <option value="month">Current Month (August 2026)</option>
                        <option value="quarter">Last Quarter (Q2)</option>
                        <option value="year">Year to Date (2026)</option>
                    </select>
                </div>
                <div class="filter-field" style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn-generate" id="generateReportBtn">
                        <i class="fas fa-sync-alt"></i> Generate Report
                    </button>
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
                    <span style="font-size: 12px; color: var(--text-secondary);">Showing real-time synced records</span>
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
                    <tbody>
                        <!-- Dynamic report rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC Veraval</strong></td>
                            <td>1,420 Patients</td>
                            <td>3,850 Units</td>
                            <td>12 Batches</td>
                            <td>1 Pending</td>
                            <td><span class="badge-score score-high">94% (Optimal)</span></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Analytics & Reporting Module.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script src="../js/district_reports.js"></script>
</body>
</html>