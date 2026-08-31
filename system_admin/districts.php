<?php
session_start();

$system_admin_name = $system_admin_name ?? '';
$total_districts = $total_districts ?? '';
$pending_phc_requests = $pending_phc_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Districts & Admins - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_districts.css">
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
            <li class="active"><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts & Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
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
                <h2>District Administrators Management</h2>
                <p>Create, assign, and manage District Health Officer (DHO) accounts across regions</p>
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

            <!-- SUCCESS NOTIFICATION BANNER -->
            <div class="alert-banner success-banner" id="districtSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="districtBannerText">District Admin account created/updated successfully!</span>
            </div>

            <!-- ACTION BAR: Search & Add Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search by District or Admin Name...">
                </div>
                <button type="button" class="btn-primary-action" id="openDistrictModalBtn">
                    <i class="fas fa-user-plus"></i> Assign New District Admin
                </button>
            </div>

            <!-- DISTRICT ADMINS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Registered District Administrators</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>District ID</th>
                            <th>District Name</th>
                            <th>District Admin (DHO) Name</th>
                            <th>Username / Login</th>
                            <th>Contact & Email</th>
                            <th>Assigned PHCs</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic district admin rows will load here -->
                        <!--
                        <tr>
                            <td><strong>DIS-01</strong></td>
                            <td>Gir Somnath</td>
                            <td>Dr. Rajesh Mehta</td>
                            <td><code>district_admin_gs</code></td>
                            <td>+91 98765 43210<br><small style="color:var(--text-secondary);">dho.girsomnath@gujarat.gov.in</small></td>
                            <td>8 PHCs</td>
                            <td><span class="badge-status status-active">Active</span></td>
                            <td><button type="button" class="btn-action btn-edit">Edit</button></td>
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
     ADD / EDIT DISTRICT ADMIN MODAL
     ============================== -->
<div class="modal-overlay" id="districtModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Assign District Administrator</h3>
            <button type="button" class="modal-close-btn" id="closeDistrictModalBtn">&times;</button>
        </div>
        <form id="districtForm" class="modal-form">
            <div class="form-group">
                <label for="district_name">District Name</label>
                <input type="text" id="district_name" required placeholder="e.g. Porbandar">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="admin_name">Admin / DHO Full Name</label>
                    <input type="text" id="admin_name" required placeholder="e.g. Dr. V. P. Rathod">
                </div>
                <div class="form-group">
                    <label for="username">Login Username</label>
                    <input type="text" id="username" required placeholder="e.g. district_admin_pb">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Set Login Password</label>
                    <input type="password" id="password" required placeholder="Temporary password">
                </div>
                <div class="form-group">
                    <label for="contact_no">Contact Number</label>
                    <input type="text" id="contact_no" required placeholder="10-digit mobile number">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Official Email Address</label>
                <input type="email" id="email" required placeholder="dho.district@gujarat.gov.in">
            </div>

            <div class="form-group">
                <label for="account_status">Account Status</label>
                <select id="account_status" required>
                    <option value="Active">Active (Can Login)</option>
                    <option value="Inactive">Inactive (Suspended)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Save District Admin Account</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script src="../js/system_districts.js"></script>
</body>
</html>