<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}



// Fetch staff details from session
$phc_id     = $_SESSION['phc_id'];
$staff_id   = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_name'];
$phc_id     = $_SESSION['phc_id'];
$phc_name   = $_SESSION['phc_name'] ?? '';
$taluka     = $_SESSION['taluka'] ?? '';
$district   = $_SESSION['district'] ?? '';


// Fetch dashboard metrics
// 1. Patients Today
$stmt_patients=$conn->prepare("
    SELECT COUNT(DISTINCT patient_id) AS cnt 
    FROM patient_visits 
    WHERE phc_id = ? AND DATE(visit_timestamp) = CURDATE()
");
$stmt_patients->bind_param("i", $phc_id);
$stmt_patients->execute();
$result_patients = $stmt_patients->get_result();
$patients_today = $result_patients->fetch_assoc()['cnt'] ?? 0;

// 2.Toal medicines issued today0
$stmt_meds_issued=$conn->prepare("
    SELECT COALESCE(SUM(vm.quantity_issued), 0) AS cnt 
    FROM visit_medicines vm 
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id 
    WHERE pv.phc_id = ? AND DATE(pv.visit_timestamp) = CURDATE()
");
$stmt_meds_issued->bind_param("i", $phc_id);
$stmt_meds_issued->execute();
$result_meds_issued = $stmt_meds_issued->get_result();
$meds_issued_today = $result_meds_issued->fetch_assoc()['cnt'] ?? 0;
 // 3. Total medicines in inventory
 $stmt_toatl=$conn->prepare("SELECT COUNT(*) AS cnt  FROM inventory  WHERE phc_id = ? AND quantity > 0");
$stmt_toatl->bind_param("i", $phc_id);
$stmt_toatl->execute();
$result_toatl = $stmt_toatl->get_result();
$total_meds = $result_toatl->fetch_assoc()['cnt'] ?? 0;



?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. FIXED SIDEBAR NAVIGATION
         ============================== -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-clinic-medical brand-icon"></i>
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>Tracker</p>
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
        <a href="patients.php">
            <i class="fas fa-users"></i>
            <span>Patients</span>
        </a>
    </li>
    <li>
        <a href="medicine_issue.php">
            <i class="fas fa-pills"></i>
            <span>Medicine Issue</span>
        </a>
    </li>
    <li>
        <a href="inventory.php">
            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>
        </a>
    </li>
    <li>
        <a href="stock_in.php">
            <i class="fas fa-arrow-down"></i>
            <span>Stock Received</span>
        </a>
    </li>
    <li>
        <a href="medicine_requests.php">
            <i class="fas fa-file-invoice"></i>
            <span>Medicine Requests</span>
        </a>
    </li>
    <li>
        <a href="doctors.php">
            <i class="fas fa-user-md"></i>
            <span>Doctors</span>
        </a>
    </li>
    <li>
        <a href="reports.php">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
    </li>
    <li>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </li>
</ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $staff_name; ?></h4>
                    <p>Role: PHC Staff</p>
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
                <h2>Dashboard</h2>
                <p><?php echo $phc_name; ?> <?php echo (!empty($taluka) || !empty($district)) ? "- " . $taluka . ", " . $district : ''; ?></p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Patient by Name or ID...">
                </div>
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme">
                    <i class="fas fa-sun"></i>
                </button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- DASHBOARD GRID CONTENT -->
        <div class="dashboard-container">

            <!-- Row 1: SUMMARY CARDS -->
            <section class="metric-cards-row">
                <div class="metric-card">
                    <div class="card-icon-wrapper patients">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="card-content">
                        <h4>Patients Today</h4>
                        <p class="metric-value"><?php echo $patients_today; ?></p>
                        <a href="patients.php" class="card-link">View patients →</a>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="card-icon-wrapper prescriptions">
                        <i class="fas fa-file-prescription"></i>
                    </div>
                    <div class="card-content">
                        <h4>Meds Issued Today</h4>
                        <p class="metric-value"><?php echo $meds_issued_today; ?></p>
                        <a href="medicine_issue.php" class="card-link">View issues →</a>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="card-icon-wrapper inventory">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="card-content">
                        <h4>Total Medicines</h4>
                        <p class="metric-value"><?php echo $total_medicines; ?></p>
                        <a href="inventory.php" class="card-link">View inventory →</a>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="card-icon-wrapper alert-low">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="card-content">
                        <h4>Low Stock Alerts</h4>
                        <p class="metric-value"><?php echo $low_stock_count; ?></p>
                        <a href="inventory.php?filter=low" class="card-link">View low stock →</a>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="card-icon-wrapper alert-expiry">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="card-content">
                        <h4>Near Expiry</h4>
                        <p class="metric-value"><?php echo $near_expiry_count; ?></p>
                        <a href="inventory.php?filter=expiry" class="card-link">View expiry →</a>
                    </div>
                </div>
            </section>

            <!-- Row 2: QUICK ACTIONS -->
            <section class="quick-actions-section">
                <h3 class="section-title">Quick Actions</h3>
                <div class="quick-actions-grid">
                    <a href="patients.php?action=register" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Register Patient</span>
                    </a>
                    <a href="medicine_issue.php" class="action-btn">
                        <i class="fas fa-prescription-bottle-alt"></i>
                        <span>Issue Medicines</span>
                    </a>
                    <a href="stock_in.php" class="action-btn">
                        <i class="fas fa-box-open"></i>
                        <span>Receive Stock</span>
                    </a>
                    <a href="inventory.php" class="action-btn">
                        <i class="fas fa-search-plus"></i>
                        <span>View Inventory</span>
                    </a>
                    <a href="doctors.php" class="action-btn">
                        <i class="fas fa-user-md"></i>
                        <span>Manage Doctors</span>
                    </a>
                    <a href="reports.php" class="action-btn">
                        <i class="fas fa-file-alt"></i>
                        <span>Generate Reports</span>
                    </a>
                </div>
            </section>

            <!-- Row 3: ALERTS & TABLE PREVIEWS -->
            <section class="dashboard-data-grid">
                <!-- ALERTS PANEL -->
                <div class="alerts-panel">
                    <div class="panel-header">
                        <h3 class="section-title">Active Alerts</h3>
                        <a href="inventory.php?filter=alerts" class="view-all">View All</a>
                    </div>
                    
                    <!-- Dynamic Alerts will populate here -->
                    <!--
                    <div class="alert-item low-stock">
                        <i class="fas fa-exclamation-circle alert-icon"></i>
                        <div class="alert-details">
                            <h4>Paracetamol 500mg</h4>
                            <p>Only 8 Tablets Left (Min: 30)</p>
                        </div>
                        <button class="btn-action btn-restock">Restock</button>
                    </div>
                    -->
                </div>

                <!-- RECENT PATIENTS TABLE -->
                <div class="data-panel">
                    <div class="panel-header">
                        <h3 class="section-title">Recent Patient Visits</h3>
                        <a href="patients.php" class="view-all">View All</a>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient Name</th>
                                <th>Age/Sex</th>
                                <th>Mobile</th>
                                <th>Last Visit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic patient visit rows will load here -->
                            <!--
                            <tr>
                                <td>P1258</td>
                                <td>Ramesh Bhai</td>
                                <td>45 / M</td>
                                <td>9876543210</td>
                                <td>10:30 AM</td>
                            </tr>
                            -->
                        </tbody>
                    </table>
                </div>
            </section>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
            <p>Developed for Rural PHCs</p>
        </footer>

    </main>
</div>

<!-- JavaScript for Theme Switching -->
<script src="../js/theme.js"></script>

</body>
</html>