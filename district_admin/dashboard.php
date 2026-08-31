<?php
session_start();

$admin_name = $admin_name ?? '';
$district = $district ?? '';
$total_phcs = $total_phcs ?? '';
$total_staff = $total_staff ?? '';
$total_doctors = $total_doctors ?? '';
$total_medicines = $total_medicines ?? '';
$pending_requests = $pending_requests ?? '';
$low_stock_count = $low_stock_count ?? '';
$near_expiry_count = $near_expiry_count ?? '';
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
                <h2>District Monitoring Dashboard</h2>
                <p>Overview of Primary Health Centers across <?php echo $district; ?> District</p>
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
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_phcs; ?></h3>
                        <p>Total PHC Centers</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_staff; ?></h3>
                        <p>Total PHC Staff Accounts</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_doctors; ?></h3>
                        <p>Associated Doctors</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $pending_requests; ?></h3>
                        <p>Pending Medicine Requests</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $low_stock_count; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
            </div>

            <!-- 2-COLUMN SECTION: PHC OVERVIEW & RECENT REQUESTS -->
            <div class="dashboard-grid-2col">
                
                <!-- PHC Centers Overview Table -->
                <div class="data-panel" style="margin-bottom:0;">
                    <div class="panel-header">
                        <h3 class="section-title">PHC Centers Overview</h3>
                        <a href="phcs.php" style="font-size: 12px; color: var(--accent-color);">View All &rarr;</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PHC Name</th>
                                <th>Location</th>
                                <th>Staff Count</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic PHC center overview rows will load here -->
                            <!--
                            <tr>
                                <td><strong>PHC Veraval</strong></td>
                                <td>Veraval (Taluka HQ)</td>
                                <td>3 Staff</td>
                                <td><span class="badge-status status-active">Operational</span></td>
                            </tr>
                            -->
                        </tbody>
                    </table>
                </div>

                <!-- Recent Medicine Requests Preview -->
                <div class="data-panel" style="margin-bottom:0;">
                    <div class="panel-header">
                        <h3 class="section-title">Recent Requests</h3>
                        <a href="medicine_requests.php" style="font-size: 12px; color: var(--accent-color);">Manage &rarr;</a>
                    </div>
                    <div style="font-size: 13px;">
                        <!-- Dynamic recent requests preview will load here -->
                        <!--
                        <div style="padding: 10px 0; border-bottom: 1px dashed var(--border-color);">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong>PHC Veraval</strong>
                                <span class="badge-status status-pending">Pending</span>
                            </div>
                            <p style="color:var(--text-secondary); margin:0; font-size:12px;">Paracetamol 500mg (500 Tabs)</p>
                            <small style="color:var(--text-secondary);">25 Aug 2026</small>
                        </div>
                        -->
                    </div>
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
</body>
</html>