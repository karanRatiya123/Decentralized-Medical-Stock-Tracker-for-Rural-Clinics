<?php
session_start();

$phc_name = $phc_name ?? '';
$taluka = $taluka ?? '';
$district = $district ?? '';
$staff_name = $staff_name ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; display: inline-block; }
        .status-pending { background-color: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-approved { background-color: rgba(14, 165, 233, 0.15); color: #0ea5e9; border: 1px solid rgba(14, 165, 233, 0.3); }
        .status-rejected { background-color: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        /* Modal & Form styling */   
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: opacity 0.3s; z-index: 1000; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; width: 100%; max-width: 500px; padding: 25px; box-shadow: var(--shadow-main); color: var(--text-primary); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-close-btn { background: none; border: none; font-size: 22px; color: var(--text-secondary); cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 13px; outline: none; box-sizing: border-box; }
        .form-group textarea { resize: vertical; height: 80px; }
        .btn-submit { width: 100%; padding: 12px; background-color: var(--accent-color); color: var(--btn-text-on-accent); border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background-color: var(--accent-hover); }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR NAVIGATION -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-clinic-medical brand-icon"></i>
            <div class="brand-text"><h3>MedStock</h3><p>Tracker</p></div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="patients.php"><i class="fas fa-users"></i><span>Patients</span></a></li>
            <li><a href="medicine_issue.php"><i class="fas fa-pills"></i><span>Medicine Issue</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory</span></a></li>
            <li><a href="stock_in.php"><i class="fas fa-arrow-down"></i><span>Stock Received</span></a></li>
            <li class="active"><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="doctors.php"><i class="fas fa-user-md"></i><span>Doctors</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i><span>Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-details"><h4><?php echo $staff_name; ?></h4><p>Role: PHC Staff</p></div>
            </div>
            <a href="../login.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>Medicine Requests</h2>
                <p><?php echo $phc_name; ?> - Request stock replenishment from District Authority</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display"><i class="far fa-calendar-alt"></i><span><?php echo date("d M Y, h:i A"); ?></span></div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- SUCCESS BANNER -->
            <div class="alert-banner success-banner" id="requestSuccessBanner" style="display: none; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; align-items: center; gap: 10px; font-size: 13px;">
                <i class="fas fa-check-circle"></i><span>Medicine request submitted successfully to District Admin.</span>
            </div>

            <!-- ACTION BAR -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" placeholder="Search previous requests..." style="background-color: var(--bg-input); border: 1px solid var(--border-color); padding: 10px 15px; border-radius: 6px; color: var(--text-primary); width: 300px; font-size: 13px;">
                </div>
                <button class="btn-primary-action" id="openRequestModalBtn" style="background-color: var(--accent-color); color: var(--btn-text-on-accent); border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-plus-circle"></i> Request Medicine
                </button>
            </div>

            <!-- REQUESTS TABLE -->
            <div class="data-panel">
                <div class="panel-header"><h3 class="section-title">Previous Requests History</h3></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Medicine Name</th>
                            <th>Requested Qty</th>
                            <th>Reason</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic medicine requests rows will load here -->
                        <!--
                        <tr>
                            <td><strong>REQ-9012</strong></td>
                            <td>Paracetamol 500mg</td>
                            <td>500 Tablets</td>
                            <td>Stock Depleted / High Demand</td>
                            <td>25 Aug 2026</td>
                            <td><span class="badge-status status-pending">Pending</span></td>
                            <td>-</td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
        </footer>
    </main>
</div>

<!-- REQUEST MEDICINE MODAL -->
<div class="modal-overlay" id="requestModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Create Medicine Request</h3>
            <button class="modal-close-btn" id="closeRequestModalBtn">&times;</button>
        </div>
        <form id="medicineRequestForm">
            <div class="form-group">
                <label for="med_select">Select Medicine</label>
                <select id="med_select" required>
                    <option value="">-- Choose Medicine --</option>
                    <!-- Dynamic medicine options will load here -->
                    <!--
                    <option value="Paracetamol 500mg">Paracetamol 500mg</option>
                    -->
                </select>
            </div>
            <div class="form-group">
                <label for="req_qty">Requested Quantity</label>
                <input type="number" id="req_qty" min="1" required placeholder="e.g. 200">
            </div>
            <div class="form-group">
                <label for="req_reason">Reason</label>
                <select id="req_reason" required>
                    <option value="">-- Select Reason --</option>
                    <option value="Out of Stock">Out of Stock</option>
                    <option value="Insufficient Stock">Insufficient Stock</option>
                    <option value="High Patient Influx / Epidemic">High Patient Influx / Epidemic</option>
                    <option value="Expired Stock Replacement">Expired Stock Replacement</option>
                </select>
            </div>
            <div class="form-group">
                <label for="req_remarks">Optional Remarks</label>
                <textarea id="req_remarks" placeholder="Add any specific notes or urgency details..."></textarea>
            </div>
            <button type="submit" class="btn-submit">Submit Request</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const openModalBtn = document.getElementById("openRequestModalBtn");
        const closeModalBtn = document.getElementById("closeRequestModalBtn");
        const modal = document.getElementById("requestModal");
        const form = document.getElementById("medicineRequestForm");
        const banner = document.getElementById("requestSuccessBanner");

        if (openModalBtn && modal) {
            openModalBtn.addEventListener("click", () => modal.classList.add("active"));
            closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
            window.addEventListener("click", (e) => { if (e.target === modal) modal.classList.remove("active"); });
        }

        if (form) {
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