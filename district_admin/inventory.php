<?php
session_start();
require_once '../config/database.php';

// Session verification (District Admin Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id   = (int)($_SESSION['user_id'] ?? 0);
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'District Admin');
$district   = htmlspecialchars($_SESSION['district'] ?? 'District HQ');

// =========================================================================
// 1. FETCH METRIC COUNTS FOR FILTER CHIPS (District-Wide)
// =========================================================================

// Total Active Inventory Records across the district
$stmt_total = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?)
");
$stmt_total->bind_param("si", $district, $admin_id);
$stmt_total->execute();
$total_items = (int)($stmt_total->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_total->close();

// Low Stock Count (Quantity <= 20 and > 0)
$stmt_low = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND i.quantity <= 20 AND i.quantity > 0
");
$stmt_low->bind_param("si", $district, $admin_id);
$stmt_low->execute();
$low_stock_count = (int)($stmt_low->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_low->close();

// Out of Stock Count (Quantity = 0)
$stmt_out = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND i.quantity = 0
");
$stmt_out->bind_param("si", $district, $admin_id);
$stmt_out->execute();
$out_of_stock_count = (int)($stmt_out->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_out->close();

// =========================================================================
// 2. FETCH INVENTORY TABLE RECORDS (WITH SEARCH & CHIP FILTERS)
// =========================================================================
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT i.inventory_id, i.quantity, i.last_updated,
           m.medicine_id, m.medicine_name, m.category,
           p.phc_name, p.Taluka
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    JOIN phcs p ON i.phc_id = p.phc_id
    WHERE (p.district = ? OR p.district_admin_id = ?)
";

// Apply status filter
if ($filter === 'low') {
    $sql .= " AND i.quantity <= 20 AND i.quantity > 0 ";
} elseif ($filter === 'out') {
    $sql .= " AND i.quantity = 0 ";
}

// Apply text search
if (!empty($search)) {
    $sql .= " AND (m.medicine_name LIKE ? OR m.category LIKE ? OR p.phc_name LIKE ?) ";
}

$sql .= " ORDER BY p.phc_name ASC, m.medicine_name ASC";

$stmt_inv = $conn->prepare($sql);

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_inv->bind_param("sisss", $district, $admin_id, $search_param, $search_param, $search_param);
} else {
    $stmt_inv->bind_param("si", $district, $admin_id);
}

$stmt_inv->execute();
$inventory_result = $stmt_inv->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Monitor - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- DISTRICT ADMIN SIDEBAR -->
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
            <li><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
            <li class="active"><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>District Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-shield"></i><span>Admin Profile</span></a></li>
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

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>District Inventory Monitor</h2>
                <p>Monitor real-time medicine stock levels across all PHCs in <?php echo $district; ?></p>
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

            <!-- ACTION BAR: SEARCH -->
            <div class="page-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
                <form action="inventory.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 550px;">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <div class="search-box" style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Medicine, Category, or PHC Name..." style="width: 100%; padding: 10px 14px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; cursor: pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="inventory.php?filter=<?php echo htmlspecialchars($filter); ?>" style="padding: 10px 14px; background: var(--bg-card, #1e293b); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 6px; text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- FILTER CHIPS -->
            <div class="filter-chips-row" style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
                <a href="inventory.php" class="chip <?php echo ($filter === 'all') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'all') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'all') ? '#fff' : 'var(--text-secondary)'; ?>; border: 1px solid var(--border-color);">
                    All District Stock (<?php echo $total_items; ?>)
                </a>
                <a href="inventory.php?filter=low" class="chip <?php echo ($filter === 'low') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'low') ? '#f59e0b' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'low') ? '#fff' : '#f59e0b'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock (<?php echo $low_stock_count; ?>)
                </a>
                <a href="inventory.php?filter=out" class="chip <?php echo ($filter === 'out') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'out') ? '#ef4444' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'out') ? '#fff' : '#ef4444'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-times-circle"></i> Out of Stock (<?php echo $out_of_stock_count; ?>)
                </a>
            </div>

            <!-- DISTRICT INVENTORY DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">Consolidated District Inventory Records</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC Center</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Units in Stock</th>
                            <th>Status</th>
                            <th>Last Updated</th>
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
                                        $qty_style = 'color: #ef4444;';
                                    } elseif ($qty <= 20) {
                                        $status_label = 'Low Stock';
                                        $status_style = 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                                        $qty_style = 'color: #f59e0b;';
                                    } else {
                                        $status_label = 'In Stock';
                                        $status_style = 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid #10b981;';
                                        $qty_style = 'color: #10b981;';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['phc_name']); ?></strong>
                                        <br>
                                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['Taluka']); ?></small>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'General'); ?></td>
                                    <td>
                                        <span style="font-size: 15px; font-weight: bold; <?php echo $qty_style; ?>">
                                            <?php echo $qty; ?> Units
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status" style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; <?php echo $status_style; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['last_updated'])): ?>
                                            <span style="color: var(--text-secondary); font-size: 13px;">
                                                <?php echo date("d M Y, h:i A", strtotime($row['last_updated'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                    <?php echo !empty($search) ? 'No inventory records found matching "' . htmlspecialchars($search) . '"' : 'No inventory records found for PHCs in this district.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Portal.</p>
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