<?php
session_start();

$system_admin_name = $system_admin_name ?? '';
$pending_phc_requests = $pending_phc_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master PHC Directory - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_phc_directory.css">
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
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>System Dashboard</span></a></li>
            <li><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts & Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li class="active"><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i><span>Admin Profile</span></a></li>
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
                <h2>Master PHC Directory</h2>
                <p>State-wide database of all operational Primary Health Centers across districts</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="dashboard-container">

            <!-- ACTION BAR: Search & District Filter -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="phcSearchInput" placeholder="Search PHC Name or Village...">
                </div>
                <div class="filter-group">
                    <select id="districtFilterSelect" class="filter-select">
                        <option value="">All Districts</option>
                        <!-- Dynamic district options will load here -->
                        <!--
                        <option value="Gir Somnath">Gir Somnath</option>
                        -->
                    </select>
                </div>
            </div>

            <!-- MASTER PHC DIRECTORY TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Registered Health Centers State-wide</h3>
                </div>
                <table class="data-table" id="masterPhcTable">
                    <thead>
                        <tr>
                            <th>PHC ID</th>
                            <th>PHC Name</th>
                            <th>District</th>
                            <th>Taluka / Village</th>
                            <th>Contact Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic master PHC rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC-101</strong></td>
                            <td>PHC Veraval</td>
                            <td>Gir Somnath</td>
                            <td>Veraval (HQ)</td>
                            <td>02876-221045</td>
                            <td><span class="badge-status status-active">Operational</span></td>
                            <td><button type="button" class="btn-action btn-view" onclick="openPhcView('PHC Veraval', 'Gir Somnath', 'Veraval (HQ)', '02876-221045')">View Details</button></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <footer class="main-footer" style="display:block; padding: 20px 0; text-align: center; color: var(--text-secondary); font-size: 12px;">
            <p>&copy; 2026 MedStock Tracker. System Administration Portal.</p>
        </footer>

    </main>
</div>

<!-- ==============================
     VIEW PHC DETAILS MODAL
     ============================== -->
<div class="modal-overlay" id="phcDetailsModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalPhcTitle">PHC Facility Details</h3>
            <button type="button" class="modal-close-btn" onclick="closePhcView()">&times;</button>
        </div>
        <div class="modal-body" style="font-size: 13px; line-height: 1.8;">
            <p><strong>Facility Name:</strong> <span id="dtlName">-</span></p>
            <p><strong>District Jurisdiction:</strong> <span id="dtlDistrict">-</span></p>
            <p><strong>Location / Taluka:</strong> <span id="dtlLocation">-</span></p>
            <p><strong>Official Contact:</strong> <span id="dtlContact">-</span></p>
            <p><strong>Database Registration Status:</strong> <span style="color: #10b981; font-weight: bold;">Active & Synced</span></p>
        </div>
        <button type="button" class="btn-submit" style="margin-top: 20px;" onclick="closePhcView()">Close</button>
    </div>
</div>

<script src="../js/theme.js"></script>
<script src="../js/system_phc_directory.js"></script>
</body>
</html>