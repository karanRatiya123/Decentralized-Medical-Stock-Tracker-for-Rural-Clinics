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

// =========================================================================
// 1. FETCH METRIC COUNTS FOR FILTER CHIPS
// =========================================================================
// Total Active Inventory Items
$stmt_total = $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE phc_id = ?");
$stmt_total->bind_param("i", $phc_id);
$stmt_total->execute();
$total_items = (int)($stmt_total->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_total->close();
//low stock items (qantity < 20)
$stmt_low = $conn->prepare("SELECT COUNT(*) AS cnt FROM inventory WHERE phc_id = ? AND quantity <= 20");
$stmt_low->bind_param("i", $phc_id);
$stmt_low->execute();
$low_stock_count = (int)($stmt_low->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_low->close();
// Near Expiry Batches Count (Expiring in the next 60 days)
$stmt_exp = $conn->prepare("
    SELECT COUNT(DISTINCT medicine_id) AS cnt 
    FROM stock_in 
    WHERE phc_id = ? AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
");
$stmt_exp->bind_param("i", $phc_id);
$stmt_exp->execute();
$near_expiry_count = (int)($stmt_exp->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_exp->close();
// =========================================================================
// 2. FETCH INVENTORY TABLE RECORDS (WITH SEARCH & CHIP FILTERS)
// =========================================================================
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT i.inventory_id, i.quantity, i.last_updated,
           m.medicine_id, m.medicine_name, m.category, m.pack_quantity, m.pack_price, m.tablet_price,
           (SELECT MIN(s.expiry_date) FROM stock_in s WHERE s.phc_id = i.phc_id AND s.medicine_id = i.medicine_id AND s.expiry_date >= CURDATE()) AS nearest_expiry
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    WHERE i.phc_id = ?
";
if ($filter === 'low') {
    $sql .= " AND i.quantity <= 20 ";
} elseif ($filter === 'expiry') {
    $sql .= " AND i.medicine_id IN (SELECT s.medicine_id FROM stock_in s WHERE s.phc_id = ? AND s.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)) ";
}
if (!empty($search)) {
    $sql .= " AND (m.medicine_name LIKE ? OR m.category LIKE ?) ";
}
$sql .= " ORDER BY i.quantity ASC, m.medicine_name ASC";
$stmt_inv = $conn->prepare($sql);
if ($filter === 'expiry') {
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $stmt_inv->bind_param("iiss", $phc_id, $phc_id, $search_param, $search_param);
    } else {
        $stmt_inv->bind_param("ii", $phc_id, $phc_id);
    }
} else {
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $stmt_inv->bind_param("iss", $phc_id, $search_param, $search_param);
    } else {
        $stmt_inv->bind_param("i", $phc_id);
    }
}

$stmt_inv->execute();
$inventory_result = $stmt_inv->get_result();


?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Catalog - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/inventory.css">
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
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="patients.php"><i class="fas fa-users"></i><span>Patients</span></a></li>
            <li><a href="medicine_issue.php"><i class="fas fa-pills"></i><span>Medicine Issue</span></a></li>
            <li class="active"><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory</span></a></li>
            <li><a href="stock_in.php"><i class="fas fa-arrow-down"></i><span>Stock Received</span></a></li>
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="doctors.php"><i class="fas fa-user-md"></i><span>Doctors</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i><span>Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-details">
                    <h4><?php echo $staff_name; ?></h4>
                    <p>Role: PHC Staff</p>
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
                <h2>Medicine Inventory Catalog</h2>
                <p><?php echo $phc_name; ?> - Real-time Stock Levels & Batch Expiry Tracking</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>
        <!-- DASHBOARD CONTAINER -->
        <div class="dashboard-container">
            <!-- ACTION BAR: SEARCH & REQUISITION BUTTON -->
            <div class="page-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
                <form action="inventory.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 450px;">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <div class="search-box" style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Medicine Name or Category..." style="width: 100%; padding: 10px 14px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; cursor: pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="inventory.php?filter=<?php echo htmlspecialchars($filter); ?>" style="padding: 10px 14px; background: var(--bg-card, #1e293b); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 6px; text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>
                <div style="display: flex; gap: 10px;">
                     
                    <a href="medicine_requests.php" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-invoice"></i> Request Stock
                    </a>
                </div>
            </div>
            <!-- FILTER CHIPS -->
            <div class="filter-chips-row" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="inventory.php" class="chip <?php echo ($filter === 'all') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'all') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'all') ? '#fff' : 'var(--text-secondary)'; ?>; border: 1px solid var(--border-color);">
                    All Stock (<?php echo $total_items; ?>)
                </a>
                <a href="inventory.php?filter=low" class="chip <?php echo ($filter === 'low') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'low') ? '#ef4444' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'low') ? '#fff' : '#ef4444'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock (<?php echo $low_stock_count; ?>)
                </a>
                <a href="inventory.php?filter=expiry" class="chip <?php echo ($filter === 'expiry') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'expiry') ? '#f59e0b' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'expiry') ? '#fff' : '#f59e0b'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-clock"></i> Near Expiry (<?php echo $near_expiry_count; ?>)
                </a>
            </div>
            <!-- INVENTORY DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">PHC Live Inventory Records</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Med ID</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Units in Stock</th>
                            <th>Stock Status</th>
                            <th>Earliest Expiry</th>
                            <th>Unit / Pack Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                            <?php while ($row = $inventory_result->fetch_assoc()): ?>
                                <?php
                                    $qty = (int)$row['quantity'];
                                    if ($qty == 0) {
                                        $status_label = 'Out of Stock';
                                        $status_style = 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444;';
                                    } elseif ($qty <= 20) {
                                        $status_label = 'Low Stock';
                                        $status_style = 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                                    } else {
                                        $status_label = 'In Stock';
                                        $status_style = 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid #10b981;';
                                    }
                                ?>
                                <tr>
                                    <td><strong>MED-<?php echo str_pad($row['medicine_id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'General'); ?></td>
                                    <td>
                                        <span style="font-size: 15px; font-weight: bold; <?php echo ($qty <= 20) ? 'color: #ef4444;' : ''; ?>">
                                            <?php echo $qty; ?> Units
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status" style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; <?php echo $status_style; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['nearest_expiry'])): ?>
                                            <span style="<?php echo (strtotime($row['nearest_expiry']) <= strtotime('+60 days')) ? 'color: #f59e0b; font-weight: bold;' : ''; ?>">
                                                <?php echo date("d M Y", strtotime($row['nearest_expiry'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>₹<?php echo number_format($row['tablet_price'], 2); ?> / ₹<?php echo number_format($row['pack_price'], 2); ?></td>
                                    <td>
                                        <a href="medicine_requests.php?medicine_id=<?php echo $row['medicine_id']; ?>" class="btn-action" style="padding: 5px 12px; background: rgba(14,165,233,0.15); color: #0ea5e9; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold;">
                                            <i class="fas fa-plus"></i> Requisition
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                    <?php echo !empty($search) ? 'No medicines found matching "' . htmlspecialchars($search) . '"' : 'No inventory records found for this PHC.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
        </footer>
    </main>
</div>
<!-- JavaScript for Theme Toggle -->
<script src="../js/theme.js"></script>
</body>
</html>
<?php
$stmt_inv->close();
?>