<?php
session_start();
require_once '../config/database.php';

// Session verification (Staff Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$phc_id     = (int)($_SESSION['phc_id'] ?? 0);
$staff_id   = (int)($_SESSION['staff_id'] ?? 0);
$staff_name = htmlspecialchars($_SESSION['staff_name'] ?? 'Staff Member');
$phc_name   = htmlspecialchars($_SESSION['phc_name'] ?? 'PHC Center');
$taluka     = htmlspecialchars($_SESSION['taluka'] ?? '');
$district   = htmlspecialchars($_SESSION['district'] ?? '');

$period = trim($_GET['period'] ?? 'month'); // 'today', 'week', 'month', 'year'

// Date conditions
if ($period === 'today') {
    $date_condition_visits = " AND DATE(pv.visit_timestamp) = CURDATE() ";
    $date_condition_stock  = " AND DATE(s.received_date) = CURDATE() ";
    $period_label = "Today (" . date("d M Y") . ")";
} elseif ($period === 'week') {
    $date_condition_visits = " AND pv.visit_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
    $date_condition_stock  = " AND s.received_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
    $period_label = "Past 7 Days";
} elseif ($period === 'year') {
    $date_condition_visits = " AND YEAR(pv.visit_timestamp) = YEAR(CURDATE()) ";
    $date_condition_stock  = " AND YEAR(s.received_date) = YEAR(CURDATE()) ";
    $period_label = "Current Year (" . date("Y") . ")";
} else {
    $date_condition_visits = " AND MONTH(pv.visit_timestamp) = MONTH(CURDATE()) AND YEAR(pv.visit_timestamp) = YEAR(CURDATE()) ";
    $date_condition_stock  = " AND MONTH(s.received_date) = MONTH(CURDATE()) AND YEAR(s.received_date) = YEAR(CURDATE()) ";
    $period_label = "Current Month (" . date("F Y") . ")";
}

// 1. Total Patient Visits in Period
$stmt_p = $conn->prepare("SELECT COUNT(*) AS cnt FROM patient_visits pv WHERE pv.phc_id = ? $date_condition_visits");
$stmt_p->bind_param("i", $phc_id);
$stmt_p->execute();
$total_visits = (int)($stmt_p->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_p->close();

// 2. Total Medicine Units Dispensed
$stmt_m = $conn->prepare("
    SELECT COALESCE(SUM(vm.quantity_issued), 0) AS cnt 
    FROM visit_medicines vm 
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id 
    WHERE pv.phc_id = ? $date_condition_visits
");
$stmt_m->bind_param("i", $phc_id);
$stmt_m->execute();
$total_dispensed = (int)($stmt_m->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_m->close();

// 3. Total Shipments Received
$stmt_s = $conn->prepare("SELECT COALESCE(SUM(s.quantity_received), 0) AS cnt FROM stock_in s WHERE s.phc_id = ? $date_condition_stock");
$stmt_s->bind_param("i", $phc_id);
$stmt_s->execute();
$total_received = (int)($stmt_s->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_s->close();

// 4. Most Prescribed Medicines in Period
$stmt_top = $conn->prepare("
    SELECT m.medicine_name, m.category, SUM(vm.quantity_issued) AS total_qty
    FROM visit_medicines vm
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id
    JOIN medicines m ON vm.medicine_id = m.medicine_id
    WHERE pv.phc_id = ? $date_condition_visits
    GROUP BY vm.medicine_id
    ORDER BY total_qty DESC
    LIMIT 10
");
$stmt_top->bind_param("i", $phc_id);
$stmt_top->execute();
$top_medicines = $stmt_top->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .sidebar, .top-header, .filter-chips-row, .btn-print, .main-footer { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body>

<div class="app-layout">
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
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="doctors.php"><i class="fas fa-user-md"></i><span>Doctors</span></a></li>
            <li class="active"><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
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

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>Reports & Analytics</h2>
                <p><?php echo $phc_name; ?> - Period: <?php echo $period_label; ?></p>
            </div>
            <div class="header-right">
                <button type="button" class="btn-print" onclick="window.print()" style="padding:10px 18px; border:none; background:#8b5cf6; color:#fff; border-radius:6px; font-weight:bold; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-print"></i> Print Report / Save PDF
                </button>
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- Period Filter Chips -->
            <div class="filter-chips-row" style="display:flex; gap:10px; margin-bottom:20px;">
                <a href="reports.php?period=today" class="chip <?php echo ($period === 'today') ? 'active' : ''; ?>" style="text-decoration:none; padding:8px 16px; border-radius:20px; font-size:13px; font-weight:bold; background:<?php echo ($period === 'today') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color:<?php echo ($period === 'today') ? '#fff' : 'var(--text-secondary)'; ?>; border:1px solid var(--border-color);">Today</a>
                <a href="reports.php?period=week" class="chip <?php echo ($period === 'week') ? 'active' : ''; ?>" style="text-decoration:none; padding:8px 16px; border-radius:20px; font-size:13px; font-weight:bold; background:<?php echo ($period === 'week') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color:<?php echo ($period === 'week') ? '#fff' : 'var(--text-secondary)'; ?>; border:1px solid var(--border-color);">This Week</a>
                <a href="reports.php?period=month" class="chip <?php echo ($period === 'month') ? 'active' : ''; ?>" style="text-decoration:none; padding:8px 16px; border-radius:20px; font-size:13px; font-weight:bold; background:<?php echo ($period === 'month') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color:<?php echo ($period === 'month') ? '#fff' : 'var(--text-secondary)'; ?>; border:1px solid var(--border-color);">This Month</a>
                <a href="reports.php?period=year" class="chip <?php echo ($period === 'year') ? 'active' : ''; ?>" style="text-decoration:none; padding:8px 16px; border-radius:20px; font-size:13px; font-weight:bold; background:<?php echo ($period === 'year') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color:<?php echo ($period === 'year') ? '#fff' : 'var(--text-secondary)'; ?>; border:1px solid var(--border-color);">This Year</a>
            </div>

            <!-- Metric Summary Cards -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:25px;">
                <div class="data-panel" style="padding:20px; text-align:center;">
                    <i class="fas fa-users" style="font-size:28px; color:#0ea5e9; margin-bottom:10px;"></i>
                    <h3 style="font-size:26px; margin:0;"><?php echo number_format($total_visits); ?></h3>
                    <p style="color:var(--text-secondary); margin:5px 0 0 0; font-size:13px;">Patient Visits</p>
                </div>
                <div class="data-panel" style="padding:20px; text-align:center;">
                    <i class="fas fa-pills" style="font-size:28px; color:#10b981; margin-bottom:10px;"></i>
                    <h3 style="font-size:26px; margin:0;"><?php echo number_format($total_dispensed); ?></h3>
                    <p style="color:var(--text-secondary); margin:5px 0 0 0; font-size:13px;">Medicine Units Dispensed</p>
                </div>
                <div class="data-panel" style="padding:20px; text-align:center;">
                    <i class="fas fa-box-open" style="font-size:28px; color:#f59e0b; margin-bottom:10px;"></i>
                    <h3 style="font-size:26px; margin:0;"><?php echo number_format($total_received); ?></h3>
                    <p style="color:var(--text-secondary); margin:5px 0 0 0; font-size:13px;">Units Received in Shipments</p>
                </div>
            </div>

            <!-- Top Dispensed Medicines Table -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom:15px;"><h3 class="section-title">Most Dispensed Medications (<?php echo $period_label; ?>)</h3></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Total Units Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_medicines && $top_medicines->num_rows > 0): ?>
                            <?php $rank = 1; while ($med = $top_medicines->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $rank++; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($med['medicine_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($med['category'] ?? 'General'); ?></td>
                                    <td><span style="font-size:15px; font-weight:bold; color:#10b981;"><?php echo number_format($med['total_qty']); ?> Units</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--text-secondary); padding:30px;">No dispensing activity recorded for <?php echo $period_label; ?>.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p></footer>
    </main>
</div>

<script src="../js/theme.js"></script>
</body>
</html>
<?php $stmt_top->close(); ?>