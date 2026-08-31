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
    <title>Staff Management - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- MINIMAL EMBEDDED STYLES FOR BUTTON & MODAL ONLY -->
    <style>
        .page-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn-primary-action {
            background-color: var(--accent-color, #0ea5e9);
            color: var(--btn-text-on-accent, #ffffff);
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary-action:hover { background-color: var(--accent-hover, #0284c7); }
        
        /* Modal Fixes */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex; justify-content: center; align-items: center;
            opacity: 0; visibility: hidden; transition: opacity 0.3s ease; z-index: 1000;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card {
            background-color: var(--bg-card, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 10px; width: 100%; max-width: 500px; padding: 25px;
            color: var(--text-primary, #f8fafc); box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close-btn { background: none; border: none; font-size: 22px; color: var(--text-secondary); cursor: pointer; }
        .modal-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-form .form-group { margin-bottom: 15px; }
        .modal-form label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; }
        .modal-form input, .modal-form select {
            width: 100%; padding: 10px; background-color: var(--bg-input, #334155);
            border: 1px solid var(--border-color, #475569); border-radius: 6px;
            color: var(--text-primary, #f8fafc); font-size: 13px; outline: none; box-sizing: border-box;
        }
        .btn-submit {
            width: 100%; padding: 12px; background-color: var(--accent-color, #0ea5e9);
            color: var(--btn-text-on-accent, #ffffff); border: none; border-radius: 6px;
            font-weight: bold; cursor: pointer; margin-top: 10px;
        }
    </style>
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
            <li class="active"><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li>
                <a href="medicine_requests.php">
                    <i class="fas fa-file-invoice"></i><span>Medicine Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_requests; ?></span>
                </a>
            </li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>District Reports</span></a></li>
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
                <h2>PHC Staff Accounts Management</h2>
                <p>Create and manage login credentials for PHC Staff across <?php echo $district; ?></p>
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

            <!-- SUCCESS NOTIFICATION BANNER -->
            <div class="alert-banner success-banner" id="staffSuccessBanner" style="display: none; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; align-items: center; gap: 10px; background: rgba(14,165,233,0.15); border:1px solid #0ea5e9; color:#0ea5e9;">
                <i class="fas fa-check-circle"></i>
                <span id="bannerMsg">New PHC staff account created successfully!</span>
            </div>

            <!-- ACTION BAR: Search & Add Staff Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search by Staff Name, Username or PHC..." style="background:var(--bg-input); border:1px solid var(--border-color); padding:10px 15px; border-radius:6px; color:var(--text-primary); width:320px; font-size:13px;">
                </div>
                <button type="button" class="btn-primary-action" id="openStaffModalBtn">
                    <i class="fas fa-user-plus"></i> Add New PHC Staff
                </button>
            </div>

            <!-- STAFF ACCOUNTS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Registered PHC Staff Directory</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Staff Name & Designation</th>
                            <th>Assigned PHC</th>
                            <th>Username / Login</th>
                            <th>Contact Number</th>
                            <th>Shift / Joining</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic staff accounts rows will load here -->
                        <!--
                        <tr>
                            <td><strong>S102</strong></td>
                            <td>Dr. Anjali Sharma<br><small style="color:var(--text-secondary);">Dispensing Officer</small></td>
                            <td>PHC Veraval</td>
                            <td><code>staff_veraval</code></td>
                            <td>9876543210</td>
                            <td>Morning (08:00 - 14:00)<br><small style="color:var(--text-secondary);">Joined: 12 Jan 2024</small></td>
                            <td><span class="badge-status status-active" style="padding:4px 10px; border-radius:20px; font-size:11px; font-weight:bold; background:rgba(16,185,129,0.15); color:#10b981;">Active</span></td>
                            <td><button type="button" class="btn-action btn-edit" style="padding:6px 14px; border-radius:4px; font-size:11px; font-weight:bold; cursor:pointer;">Edit</button></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Portal.</p>
        </footer>

    </main>
</div>

<!-- ==============================
     ADD / EDIT PHC STAFF MODAL
     ============================== -->
<div class="modal-overlay" id="staffModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Create PHC Staff Account</h3>
            <button type="button" class="modal-close-btn" id="closeStaffModalBtn">&times;</button>
        </div>
        <form id="staffForm" class="modal-form">
            <div class="form-group">
                <label for="staff_name">Staff Full Name</label>
                <input type="text" id="staff_name" required placeholder="e.g. Dr. Suresh Kumar">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="assigned_phc">Assign PHC Center</label>
                    <select id="assigned_phc" required>
                        <option value="">Select PHC</option>
                        <!-- Dynamic PHC options will load here -->
                        <!--
                        <option value="PHC Veraval">PHC Veraval</option>
                        -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="designation">Designation</label>
                    <input type="text" id="designation" required placeholder="e.g. Dispensing Officer">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Login Username</label>
                    <input type="text" id="username" required placeholder="e.g. staff_veraval2">
                </div>
                <div class="form-group">
                    <label for="password">Set Login Password</label>
                    <input type="password" id="password" required placeholder="Temporary password">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact_no">Contact Number</label>
                    <input type="text" id="contact_no" required placeholder="10-digit mobile number">
                </div>
                <div class="form-group">
                    <label for="shift">Working Shift</label>
                    <select id="shift" required>
                        <option value="Morning">Morning (08:00 - 14:00)</option>
                        <option value="Evening">Evening (14:00 - 20:00)</option>
                        <option value="Night">Night (20:00 - 08:00)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="account_status">Account Status</label>
                <select id="account_status" required>
                    <option value="Active">Active (Can Login)</option>
                    <option value="Inactive">Inactive (Suspended)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Save Staff Account & Generate Credentials</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const openModalBtn = document.getElementById("openStaffModalBtn");
        const closeModalBtn = document.getElementById("closeStaffModalBtn");
        const modal = document.getElementById("staffModal");
        const form = document.getElementById("staffForm");
        const banner = document.getElementById("staffSuccessBanner");

        if (openModalBtn && modal) {
            openModalBtn.addEventListener("click", () => {
                modal.classList.add("active");
            });
        }
        if (closeModalBtn && modal) {
            closeModalBtn.addEventListener("click", () => {
                modal.classList.remove("active");
            });
        }
        window.addEventListener("click", (e) => {
            if (modal && e.target === modal) {
                modal.classList.remove("active");
            }
        });

        if (form && modal) {
            form.addEventListener("submit", (e) => {
                e.preventDefault();
                modal.classList.remove("active");
                form.reset();
                if (banner) {
                    banner.style.display = "flex";
                    setTimeout(() => { banner.style.display = "none"; }, 4000);
                }
            });
        }
    });
</script>
</body>
</html>