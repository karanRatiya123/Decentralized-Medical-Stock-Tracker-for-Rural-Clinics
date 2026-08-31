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
    <title>PHC Document Requests - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_phc_requests.css">
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
            <li class="active">
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
                <h2>PHC Document Proposals Review</h2>
                <p>Review attached official documents, extract PHC data, and register new facilities into the database</p>
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
            <div class="alert-banner success-banner" id="sysActionBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="sysActionBannerText">PHC successfully registered into the master database!</span>
            </div>

            <!-- REQUESTS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Incoming District PHC Proposals & Documents</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>District Name</th>
                            <th>Proposal Title</th>
                            <th>Attached Document (Contains Full PHC Data)</th>
                            <th>Submitted Date</th>
                            <th>Status</th>
                            <th>Actions & Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic incoming PHC proposal requests will load here -->
                        <!--
                        <tr>
                            <td><strong>REQ-P01</strong></td>
                            <td>Gir Somnath</td>
                            <td>Proposal for New PHC at Talala Rural</td>
                            <td><a href="#" class="doc-link"><i class="fas fa-file-pdf"></i> Talala_PHC_Proposal.pdf</a></td>
                            <td>26 Aug 2026</td>
                            <td><span class="badge-status status-pending">Pending Review</span></td>
                            <td>
                                <button type="button" class="btn-approve" onclick="openReviewModal('REQ-P01', 'Approve')">Read Doc & Register PHC</button>
                                <button type="button" class="btn-reject" onclick="openReviewModal('REQ-P01', 'Reject')">Reject</button>
                            </td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer" style="display:block; padding: 20px 0; text-align: center; color: var(--text-secondary); font-size: 12px;">
            <p>&copy; 2026 MedStock Tracker. System Administration Portal.</p>
        </footer>

    </main>
</div>

<!-- ==============================
     REVIEW PROPOSAL MODAL (WITH DATABASE ENTRY FIELDS)
     ============================== -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-card" style="max-width: 550px;">
        <div class="modal-header">
            <h3 id="modalTitle">Review Proposal & Enter PHC Data</h3>
            <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="reviewForm" class="modal-form">
            
            <!-- Database fields to be filled from the uploaded document -->
            <div id="phcDatabaseFields">
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_phc_name">PHC Official Name</label>
                        <input type="text" id="db_phc_name" placeholder="e.g. PHC Talala Rural">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_taluka">Taluka</label>
                        <input type="text" id="db_taluka" placeholder="e.g. Talala">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_village">Village / Town</label>
                        <input type="text" id="db_village" placeholder="e.g. Talala City">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_contact">Facility Contact Number</label>
                        <input type="text" id="db_contact" placeholder="e.g. 02878-221099">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label for="db_address">Complete Street Address</label>
                    <input type="text" id="db_address" placeholder="e.g. Near State Highway, Talala">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="sys_remarks">System Admin Remarks / Approval Notes</label>
                <textarea id="sys_remarks" required placeholder="Enter notes verified from the document..."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="modalSubmitBtn">Confirm & Register to Database</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script src="../js/system_phc_requests.js"></script>
</body>
</html>