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
    <title>Inventory Monitor - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/inventory_monitor.css">
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
            <li class="active">
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
                <h2>District Inventory Monitor</h2>
                <p>Real-time stock levels across all PHC Centers in <?php echo $district; ?></p>
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

            <!-- ACTION BAR: Search & PHC Filter -->
            <div class="page-action-bar">
                <div class="search-box">
                    <i class="fas fa-search" style="position: absolute; margin-left: 12px; margin-top: 12px; color: var(--text-secondary);"></i>
                    <input type="text" id="inventorySearch" placeholder="Search medicine name..." style="padding-left: 35px;">
                </div>
                <div class="filter-group">
                    <select id="phcFilter" class="filter-select">
                        <option value="">All PHC Centers</option>
                        <!-- Dynamic PHC options will load here -->
                        <!--
                        <option value="PHC Veraval">PHC Veraval</option>
                        -->
                    </select>
                </div>
            </div>

            <!-- INVENTORY MONITORING TABLE -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">PHC Stock Overview (Read-Only Monitor)</h3>
                </div>
                <table class="data-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>PHC Center</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Available Qty</th>
                            <th>Stock Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic district inventory rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC Veraval</strong></td>
                            <td>Paracetamol 500mg</td>
                            <td>Analgesic</td>
                            <td><span class="stock-val low">8 Tablets</span></td>
                            <td><span class="badge-status status-low">Low Stock</span></td>
                            <td>25 Aug 2026</td>
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
<script src="../js/district_inventory.js"></script>
</body>
</html>