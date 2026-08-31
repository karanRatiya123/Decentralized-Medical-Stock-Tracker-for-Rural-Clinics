<?php
session_start();

$system_admin_name = $system_admin_name ?? '';
$total_districts = $total_districts ?? '';
$total_phcs = $total_phcs ?? '';
$total_district_admins = $total_district_admins ?? '';
$pending_phc_requests = $pending_phc_requests ?? '';
$total_staff_accounts = $total_staff_accounts ?? '';
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
                    <span>Districts & Admins</span>
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
                        <h3><?php echo $total_districts; ?></h3>
                        <p>Managed Districts</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_phcs; ?></h3>
                        <p>Total Registered PHCs</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $pending_phc_requests; ?></h3>
                        <p>Pending PHC Requests</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_district_admins; ?></h3>
                        <p>District Administrators</p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="metric-info">
                        <h3><?php echo $total_staff_accounts; ?></h3>
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
                            <!-- Dynamic district infrastructure rows will load here -->
                            <!--
                            <tr>
                                <td><strong>Gir Somnath</strong></td>
                                <td>Dr. Rajesh Mehta</td>
                                <td>8 PHCs</td>
                                <td><span class="badge-status status-active">Active</span></td>
                            </tr>
                            -->
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
                        <!-- Dynamic incoming PHC proposals will load here -->
                        <!--
                        <div style="padding: 12px 0; border-bottom: 1px dashed var(--border-color);">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <strong>Gir Somnath District</strong>
                                <span class="badge-status status-pending">Pending Review</span>
                            </div>
                            <p style="color:var(--text-secondary); margin:0 0 6px 0; font-size:12px;">Proposal for New PHC at Talala Rural</p>
                            <a href="#" style="color: var(--accent-color); font-size: 11px; text-decoration: underline;"><i class="fas fa-file-pdf"></i> Talala_PHC_Proposal.pdf</a>
                        </div>
                        -->
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