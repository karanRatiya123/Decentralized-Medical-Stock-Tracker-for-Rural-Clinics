<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

// Verify account is still active
$staff_id = (int)($_SESSION['staff_id'] ?? $_SESSION['user_id'] ?? 0);
$stmt_act = $conn->prepare("SELECT status FROM staff WHERE staff_id = ?");
$stmt_act->bind_param("i", $staff_id);
$stmt_act->execute();
$act_row = $stmt_act->get_result()->fetch_assoc();
$stmt_act->close();
if ($act_row && strcasecmp($act_row['status'], 'Inactive') === 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=deactivated");
    exit();
}

// Extract session variables
$phc_id     = (int)($_SESSION['phc_id'] ?? 0);
$staff_name = htmlspecialchars($_SESSION['staff_name'] ?? 'Staff Member');
$phc_name   = htmlspecialchars($_SESSION['phc_name'] ?? 'PHC Center');
$taluka     = htmlspecialchars($_SESSION['taluka'] ?? '');
$district   = htmlspecialchars($_SESSION['district'] ?? '');

// =========================================================================
// 1. QUERY DASHBOARD METRICS
// =========================================================================

// Metric 1: Patients visited today
$stmt_patients = $conn->prepare("
    SELECT COUNT(DISTINCT patient_id) AS cnt 
    FROM patient_visits 
    WHERE phc_id = ? AND DATE(visit_timestamp) = CURDATE()
");
$stmt_patients->bind_param("i", $phc_id);
$stmt_patients->execute();
$patients_today = $stmt_patients->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt_patients->close();

// Metric 2: Total medicine units issued today
$stmt_meds = $conn->prepare("
    SELECT COALESCE(SUM(vm.quantity_issued), 0) AS cnt 
    FROM visit_medicines vm 
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id 
    WHERE pv.phc_id = ? AND DATE(pv.visit_timestamp) = CURDATE()
");
$stmt_meds->bind_param("i", $phc_id);
$stmt_meds->execute();
$meds_issued_today = $stmt_meds->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt_meds->close();

// Metric 3: Total active medicines in PHC inventory
$stmt_total = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory 
    WHERE phc_id = ? AND quantity > 0
");
$stmt_total->bind_param("i", $phc_id);
$stmt_total->execute();
$total_medicines = $stmt_total->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt_total->close();

// Metric 4: Low stock count (Quantity <= 20)
$stmt_low = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory 
    WHERE phc_id = ? AND quantity <= 20
");
$stmt_low->bind_param("i", $phc_id);
$stmt_low->execute();
$low_stock_count = $stmt_low->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt_low->close();

// Metric 5: Near expiry count (Expiring in the next 60 days)
$stmt_exp = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM stock_in 
    WHERE phc_id = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
");
$stmt_exp->bind_param("i", $phc_id);
$stmt_exp->execute();
$near_expiry_count = $stmt_exp->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt_exp->close();

// =========================================================================
// 2. QUERY ACTIVE LOW-STOCK ALERTS (Top 5)
// =========================================================================
$stmt_alerts = $conn->prepare("
    SELECT m.medicine_name, i.quantity 
    FROM inventory i 
    JOIN medicines m ON i.medicine_id = m.medicine_id 
    WHERE i.phc_id = ? AND i.quantity <= 20 
    ORDER BY i.quantity ASC 
    LIMIT 5
");
$stmt_alerts->bind_param("i", $phc_id);
$stmt_alerts->execute();
$alerts_result = $stmt_alerts->get_result();

// =========================================================================
// 3. QUERY RECENT PATIENT VISITS (Top 5)
// =========================================================================
$stmt_visits = $conn->prepare("
    SELECT pv.visit_id, pv.visit_timestamp, pt.patient_id, pt.patient_name, pt.age, pt.gender, pt.mobile_no 
    FROM patient_visits pv 
    JOIN patients pt ON pv.patient_id = pt.patient_id 
    WHERE pv.phc_id = ? 
    ORDER BY pv.visit_timestamp DESC 
    LIMIT 5
");
$stmt_visits->bind_param("i", $phc_id);
$stmt_visits->execute();
$recent_visits = $stmt_visits->get_result();
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
            <li class="active">
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
                    
                    <?php if ($alerts_result && $alerts_result->num_rows > 0): ?>
                        <?php while ($alert = $alerts_result->fetch_assoc()): ?>
                            <div class="alert-item low-stock">
                                <i class="fas fa-exclamation-circle alert-icon"></i>
                                <div class="alert-details">
                                    <h4><?php echo htmlspecialchars($alert['medicine_name']); ?></h4>
                                    <p>Only <?php echo (int)$alert['quantity']; ?> Units Left (Threshold: 20)</p>
                                </div>
                                <a href="medicine_requests.php?action=create" class="btn-action btn-restock" style="text-decoration:none; text-align:center;">Restock</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 13px; padding: 15px;">No low stock alerts for this PHC.</p>
                    <?php endif; ?>
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
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Age/Sex</th>
                                <th>Mobile</th>
                                <th>Visit Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_visits && $recent_visits->num_rows > 0): ?>
                                <?php while ($visit = $recent_visits->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>P-<?php echo str_pad($visit['patient_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($visit['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($visit['age'] ?? '-'); ?> / <?php echo htmlspecialchars(substr($visit['gender'] ?? '-', 0, 1)); ?></td>
                                        <td><?php echo htmlspecialchars($visit['mobile_no'] ?? '-'); ?></td>
                                        <td><?php echo date("d M, h:i A", strtotime($visit['visit_timestamp'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-secondary);">No recent patient visits recorded.</td>
                                </tr>
                            <?php endif; ?>
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
<?php
$stmt_alerts->close();
$stmt_visits->close();
?>