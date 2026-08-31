<?php
session_start();

$admin_name = $admin_name ?? '';
$district = $district ?? '';
$pending_requests = $pending_requests ?? '';
$low_stock_count = $low_stock_count ?? '';
$near_expiry_count = $near_expiry_count ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts & Expiry Monitor - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/district_alerts.css">
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
            <li class="active">
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

    <!-- ==============================
         2. MAIN CONTENT AREA
         ============================== -->
    <main class="main-content">
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>District Alerts & Expiry Monitor</h2>
                <p>Dynamic low-stock warnings and near-expiry medications across <?php echo $district; ?></p>
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

            <!-- TAB SWITCHER -->
            <div class="filter-chips-row" style="margin-bottom: 20px;">
                <button type="button" class="chip active" onclick="switchTab('lowstock', this)">Low Stock Alerts (<?php echo $low_stock_count; ?>)</button>
                <button type="button" class="chip" onclick="switchTab('expiry', this)">Near Expiry Alerts (<?php echo $near_expiry_count; ?>)</button>
            </div>

            <!-- TAB 1: LOW STOCK ALERTS PANEL -->
            <div class="data-panel alert-section" id="lowstockSection">
                <div class="panel-header">
                    <h3 class="section-title"><i class="fas fa-exclamation-triangle" style="color: #ef4444; margin-right: 8px;"></i>PHC Low-Stock Inventory</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC Center</th>
                            <th>Medicine Name</th>
                            <th>Current Quantity</th>
                            <th>Threshold Limit</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic low stock rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC Veraval</strong></td>
                            <td>Paracetamol 500mg</td>
                            <td><span class="qty-badge low">8 Tablets</span></td>
                            <td>20 Tablets</td>
                            <td><span class="badge-alert status-danger">Critical Low</span></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: NEAR EXPIRY ALERTS PANEL -->
            <div class="data-panel alert-section" id="expirySection" style="display: none;">
                <div class="panel-header">
                    <h3 class="section-title"><i class="fas fa-clock" style="color: #f59e0b; margin-right: 8px;"></i>Medicines Near Expiry</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC Center</th>
                            <th>Medicine Name</th>
                            <th>Batch No.</th>
                            <th>Stock Quantity</th>
                            <th>Expiry Date</th>
                            <th>Urgency Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic near-expiry rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC Kodinar</strong></td>
                            <td>Salbutamol Syrup 100ml</td>
                            <td>BT-4419</td>
                            <td>40 Bottles</td>
                            <td><span style="color:#ef4444; font-weight:bold;">15 Sep 2026</span></td>
                            <td><span class="badge-alert status-danger">Expiring Soon (20 Days)</span></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Portal.</p>
        </footer>

    </main>
</div>

<script src="../js/theme.js"></script>
<script src="../js/district_alerts.js"></script>
</body>
</html>