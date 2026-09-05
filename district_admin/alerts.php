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
// 1. FETCH ALERT METRICS
// =========================================================================

// Critical Alerts (Quantity = 0)
$sql_crit = "SELECT COUNT(*) AS cnt FROM inventory i JOIN phcs p ON i.phc_id = p.phc_id WHERE p.district = ? AND i.quantity = 0";
$stmt_crit = $conn->prepare($sql_crit);
if ($stmt_crit) {
    $stmt_crit->bind_param("s", $district);
    $stmt_crit->execute();
    $critical_alerts = (int)($stmt_crit->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt_crit->close();
} else {
    $critical_alerts = 0;
}

// Warning Alerts (Quantity between 1 and 20)
$sql_warn = "SELECT COUNT(*) AS cnt FROM inventory i JOIN phcs p ON i.phc_id = p.phc_id WHERE p.district = ? AND i.quantity > 0 AND i.quantity <= 20";
$stmt_warn = $conn->prepare($sql_warn);
if ($stmt_warn) {
    $stmt_warn->bind_param("s", $district);
    $stmt_warn->execute();
    $warning_alerts = (int)($stmt_warn->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt_warn->close();
} else {
    $warning_alerts = 0;
}

$total_alerts = $critical_alerts + $warning_alerts;

// =========================================================================
// 2. FETCH ALERTS DATA TABLE
// =========================================================================
// Safely selecting only core columns
$sql_alerts = "
    SELECT i.inventory_id, i.quantity, i.last_updated,
           m.medicine_name, m.category,
           p.phc_name
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    JOIN phcs p ON i.phc_id = p.phc_id
    WHERE p.district = ? AND i.quantity <= 20
    ORDER BY i.quantity ASC, p.phc_name ASC
";

$stmt_alerts = $conn->prepare($sql_alerts);

// Error Handling to prevent crashing if query fails
if (!$stmt_alerts) {
    die("<div style='color:red; padding:20px; background:#ffebeb; border:1px solid red; border-radius:5px;'>
         <strong>Database Query Error:</strong> " . $conn->error . "<br>
         Please check your database table structure.
         </div>");
}

$stmt_alerts->bind_param("s", $district);
$stmt_alerts->execute();
$alerts_result = $stmt_alerts->get_result();

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Alerts - District Admin - MedStock Tracker</title>
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
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li class="active"><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
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
        
        <header class="top-header">
            <div class="header-left">
                <h2>District Stock Alerts</h2>
                <p>Monitor critical shortages and low stock warnings across all PHCs in <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-container">

            <!-- ALERTS SUMMARY CARDS -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                
                <!-- Critical Alerts Card -->
                <div class="stat-card" style="background: var(--bg-card); border: 1px solid rgba(239, 68, 68, 0.3); border-left: 4px solid #ef4444;">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $critical_alerts; ?></h3>
                        <p style="color: #ef4444; font-weight: bold;">Critical (Out of Stock)</p>
                    </div>
                </div>

                <!-- Warning Alerts Card -->
                <div class="stat-card" style="background: var(--bg-card); border: 1px solid rgba(245, 158, 11, 0.3); border-left: 4px solid #f59e0b;">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $warning_alerts; ?></h3>
                        <p style="color: #f59e0b; font-weight: bold;">Warnings (Low Stock &le; 20)</p>
                    </div>
                </div>

                <!-- Total Alerts Card -->
                <div class="stat-card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="stat-icon" style="background: rgba(148, 163, 184, 0.15); color: var(--text-secondary);">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_alerts; ?></h3>
                        <p>Total Active Alerts</p>
                    </div>
                </div>
            </div>

            <!-- ALERTS DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">Requires Immediate Attention</h3>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Alert Level</th>
                            <th>Medicine Name</th>
                            <th>PHC Center</th>
                            <th>Current Stock</th>
                            <th>Category</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($alerts_result && $alerts_result->num_rows > 0): ?>
                            <?php while ($row = $alerts_result->fetch_assoc()): ?>
                                <?php
                                    $qty = (int)$row['quantity'];
                                    if ($qty == 0) {
                                        $alert_level = 'CRITICAL';
                                        $alert_icon = 'fa-times-circle';
                                        $row_style = 'background: rgba(239, 68, 68, 0.05);';
                                        $badge_style = 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444;';
                                    } else {
                                        $alert_level = 'WARNING';
                                        $alert_icon = 'fa-exclamation-circle';
                                        $row_style = '';
                                        $badge_style = 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                                    }
                                ?>
                                <tr style="<?php echo $row_style; ?>">
                                    <td>
                                        <span class="badge-status" style="padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; <?php echo $badge_style; ?>">
                                            <i class="fas <?php echo $alert_icon; ?>" style="margin-right: 4px;"></i> <?php echo $alert_level; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['phc_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="font-size: 16px; font-weight: bold; <?php echo ($qty == 0) ? 'color: #ef4444;' : 'color: #f59e0b;'; ?>">
                                            <?php echo $qty; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                    <td>
                                        <a href="medicine_requests.php?search=<?php echo urlencode($row['phc_name']); ?>" 
                                           style="display: inline-block; padding: 6px 12px; background: rgba(14,165,233,0.15); color: #0ea5e9; border: 1px solid #0ea5e9; border-radius: 4px; font-size: 11px; font-weight: bold; text-decoration: none;">
                                           Check Requests
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 40px;">
                                    <div style="font-size: 40px; color: #10b981; margin-bottom: 10px;">
                                        <i class="fas fa-shield-check"></i>
                                    </div>
                                    <h3>All Clear!</h3>
                                    <p>No critical shortages or low stock alerts across the district.</p>
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

<script src="../js/theme.js"></script>
</body>
</html>
<?php
if ($stmt_alerts) {
    $stmt_alerts->close();
}
?>