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
    <title>PHC Centers & Requests - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/phcs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- FIX FOR MODAL POPUP DISPLAY -->
    <style>
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex; justify-content: center; align-items: center;
            opacity: 0; visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            z-index: 1000;
        }
        .modal-overlay.active {
            opacity: 1 !important;
            visibility: visible !important;
        }
        .modal-card {
            background-color: var(--bg-card, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 10px;
            width: 100%; max-width: 480px;
            padding: 25px;
            color: var(--text-primary, #f8fafc);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
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
            <li class="active"><a href="phcs.php"><i class="fas fa-hospital"></i><span>PHC Centers</span></a></li>
            <li><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
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
                <h2>PHC Centers & New Requests</h2>
                <p>Monitor existing PHCs or submit official document proposals for new PHC creation to System Admin</p>
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
            <div class="alert-banner success-banner" id="phcRequestSuccessBanner" style="display: none; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; align-items: center; gap: 10px; background: rgba(14,165,233,0.15); border:1px solid #0ea5e9; color:#0ea5e9;">
                <i class="fas fa-check-circle"></i>
                <span>PHC proposal document successfully sent to System Admin for review!</span>
            </div>

            <!-- ACTION BAR: Search & Request New PHC Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search PHC by Name or Taluka...">
                </div>
                <button type="button" class="btn-primary-action" id="openPhcRequestModalBtn" style="background-color: var(--accent-color); color: var(--btn-text-on-accent); border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-upload"></i> Request New PHC (Upload Document)
                </button>
            </div>

            <!-- EXISTING PHC CENTERS TABLE PANEL -->
            <div class="data-panel" style="margin-bottom: 25px;">
                <div class="panel-header">
                    <h3 class="section-title">Active PHC Centers (<?php echo $district; ?>)</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC ID</th>
                            <th>PHC Name</th>
                            <th>Village / Taluka</th>
                            <th>Contact Number</th>
                            <th>Assigned Staff</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic active PHC rows will load here -->
                        <!--
                        <tr>
                            <td><strong>PHC-101</strong></td>
                            <td>PHC Veraval</td>
                            <td>Veraval (Taluka HQ)</td>
                            <td>02876-221045</td>
                            <td>3 Active Staff</td>
                            <td><span class="badge-status status-active">Operational</span></td>
                            <td>
                                <button class="btn-action btn-view" onclick="openPhcDetails('PHC Veraval', 'Veraval', '3 Staff', 'Operational')">Monitor</button>
                            </td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

            <!-- PENDING PHC CREATION REQUESTS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Pending PHC Requests Submitted to System Admin</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Proposal / Title</th>
                            <th>Attached Document</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>System Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic pending PHC request rows will load here -->
                        <!--
                        <tr>
                            <td><strong>REQ-P01</strong></td>
                            <td>Proposal for New Rural Clinic at Talala</td>
                            <td><a href="#" style="color: var(--accent-color); text-decoration: underline;"><i class="fas fa-file-pdf"></i> Talala_PHC_Proposal.pdf</a></td>
                            <td>26 Aug 2026</td>
                            <td><span class="badge-status" style="background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3);">Pending Review</span></td>
                            <td>Awaiting System Admin verification.</td>
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
     REQUEST NEW PHC MODAL (DOCUMENT UPLOAD)
     ============================== -->
<div class="modal-overlay" id="phcRequestModal">
    <div class="modal-card">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin:0; font-size:16px;">Request New PHC Creation</h3>
            <button type="button" class="modal-close-btn" id="closePhcRequestModalBtn" style="background:none; border:none; font-size:22px; color:var(--text-secondary); cursor:pointer;">&times;</button>
        </div>
        <form id="phcRequestForm">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px;">Proposal Title / Description</label>
                <input type="text" id="prop_title" required placeholder="e.g. Proposal for New PHC at Talala Rural" style="width: 100%; padding: 10px; background-color: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-primary, #f8fafc); font-size: 13px; outline: none; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px;">Upload Official Proposal / Sanction Document (PDF / Scan)</label>
                <input type="file" id="prop_doc" accept=".pdf,.doc,.docx,.jpg,.png" required style="width: 100%; padding: 8px; background-color: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-primary, #f8fafc); font-size: 13px; box-sizing: border-box;">
                <small style="color: var(--text-secondary); font-size: 11px; margin-top: 4px; display: block;">System Admin will review this document to manually add the PHC data.</small>
            </div>

            <button type="submit" class="btn-submit" style="width: 100%; padding: 12px; background-color: var(--accent-color, #0ea5e9); color: var(--btn-text-on-accent, #ffffff); border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Send Document to System Admin</button>
        </form>
    </div>
</div>

<!-- MONITOR PHC DETAILS MODAL -->
<div class="modal-overlay" id="phcModal">
    <div class="modal-card">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 id="modalPhcName" style="margin:0; font-size:16px;">PHC Monitoring Summary</h3>
            <button class="modal-close-btn" onclick="closePhcModal()" style="background:none; border:none; font-size:22px; color:var(--text-secondary); cursor:pointer;">&times;</button>
        </div>
        <div class="phc-modal-body" style="font-size: 13px; line-height: 1.6;">
            <p><strong>Taluka:</strong> <span id="modalTaluka">-</span></p>
            <p><strong>Operational Status:</strong> <span id="modalStatus" style="color: #10b981; font-weight: bold;">-</span></p>
            <p><strong>Assigned Staff Members:</strong> <span id="modalStaffCount">-</span></p>
        </div>
        <button type="button" class="btn-submit" style="margin-top: 20px; width: 100%; padding: 10px; background-color: var(--accent-color, #0ea5e9); color: var(--btn-text-on-accent, #ffffff); border: none; border-radius: 6px; font-weight: bold; cursor: pointer;" onclick="closePhcModal()">Close Overview</button>
    </div>
</div>

<script src="../js/theme.js"></script>
<script>
    // Existing PHC Modal script
    function openPhcDetails(name, taluka, staff, status) {
        document.getElementById('modalPhcName').textContent = name + " - Monitoring Details";
        document.getElementById('modalTaluka').textContent = taluka;
        document.getElementById('modalStaffCount').textContent = staff;
        document.getElementById('modalStatus').textContent = status;
        document.getElementById('phcModal').classList.add('active');
    }
    function closePhcModal() {
        document.getElementById('phcModal').classList.remove('active');
    }

    // New Request PHC Document Modal script
    document.addEventListener("DOMContentLoaded", () => {
        const openModalBtn = document.getElementById("openPhcRequestModalBtn");
        const closeModalBtn = document.getElementById("closePhcRequestModalBtn");
        const modal = document.getElementById("phcRequestModal");
        const form = document.getElementById("phcRequestForm");
        const banner = document.getElementById("phcRequestSuccessBanner");

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