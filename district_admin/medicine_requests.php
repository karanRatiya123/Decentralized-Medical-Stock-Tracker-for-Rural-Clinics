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
    <title>Medicine Requests - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/district_medicine_requests.css">
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
            <li class="active">
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
                <h2>PHC Medicine Requisitions</h2>
                <p>Review and action stock requests submitted by PHCs in <?php echo $district; ?></p>
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

            <!-- ACTION BANNER -->
            <div class="alert-banner success-banner" id="adminActionBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="actionBannerText">Request status updated successfully.</span>
            </div>

            <!-- REQUESTS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Incoming & Past Requisitions</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC Name</th>
                            <th>Medicine & Qty</th>
                            <th>Staff Name & Date</th>
                            <th>Reason & Remarks</th>
                            <th>Status</th>
                            <th>Actions / Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic medicine requisitions rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC Veraval</strong></td>
                            <td>Paracetamol 500mg<br><span class="qty-highlight">Qty: 500 Tabs</span></td>
                            <td>Dr. Anjali Sharma<br><small style="color:var(--text-secondary);">25 Aug 2026</small></td>
                            <td>Stock Depleted<br><small style="color:var(--text-secondary);">Note: Urgent requirement for OPD</small></td>
                            <td><span class="badge-status status-pending">Pending</span></td>
                            <td>
                                <button type="button" class="btn-approve" onclick="openActionModal('REQ-9012', 'Approve')">Approve</button>
                                <button type="button" class="btn-reject" onclick="openActionModal('REQ-9012', 'Reject')">Reject</button>
                            </td>
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

<!-- ==============================
     APPROVE / REJECT ACTION MODAL
     ============================== -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Review Request</h3>
            <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="actionForm" class="modal-form">
            <div class="form-group">
                <label for="admin_remarks">Add Admin Remarks / Instructions</label>
                <textarea id="admin_remarks" required placeholder="Enter notes regarding approval, supply dispatch, or reason for rejection..."></textarea>
            </div>
            <button type="submit" class="btn-submit" id="modalSubmitBtn">Confirm & Save</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script src="../js/district_medicine_requests.js"></script>
</body>
</html>