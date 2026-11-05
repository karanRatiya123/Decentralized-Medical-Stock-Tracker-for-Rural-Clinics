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

$success_msg = '';
$error_msg   = '';
$active_tab  = $_GET['tab'] ?? 'warehouse';

// =========================================================================
// 0. HANDLE POST ACTIONS (INWARD STOCK & DIRECT DISPATCH)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ---------------------------------------------------------------------
    // A. INWARD STOCK CONSIGNMENT HANDLER
    // ---------------------------------------------------------------------
    if ($_POST['action'] === 'receive_inward') {
        $med_id   = (int)($_POST['medicine_id'] ?? 0);
        $qty      = (int)($_POST['quantity'] ?? 0);
        $batch_no = trim($_POST['batch_no'] ?? '');
        $expiry   = trim($_POST['expiry_date'] ?? '');
        $source   = trim($_POST['supplier'] ?? 'Gujarat Medical Services Corp (GMSCL)');

        if ($med_id > 0 && $qty > 0 && !empty($batch_no) && !empty($expiry)) {
            $conn->begin_transaction();
            try {
                // 1. Check if source column exists in district_stock_in
                $has_src = false;
                $chk_col = $conn->query("SHOW COLUMNS FROM district_stock_in LIKE 'source'");
                if ($chk_col && $chk_col->num_rows > 0) {
                    $has_src = true;
                }

                if ($has_src) {
                    $stmt_inw = $conn->prepare("
                        INSERT INTO district_stock_in 
                            (district_admin_id, medicine_id, quantity_received, batch_no, expiry_date, source, received_date)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt_inw->bind_param("iiisss", $admin_id, $med_id, $qty, $batch_no, $expiry, $source);
                } else {
                    $stmt_inw = $conn->prepare("
                        INSERT INTO district_stock_in 
                            (district_admin_id, medicine_id, quantity_received, batch_no, expiry_date, received_date)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt_inw->bind_param("iiiss", $admin_id, $med_id, $qty, $batch_no, $expiry);
                }
                
                if (!$stmt_inw->execute()) {
                    throw new Exception("Error recording stock-in receipt: " . $stmt_inw->error);
                }
                $stmt_inw->close();

                // 2. Increment District Central Warehouse inventory (district_inventory)
                $stmt_chk_di = $conn->prepare("SELECT quantity FROM district_inventory WHERE district_admin_id = ? AND medicine_id = ?");
                $stmt_chk_di->bind_param("ii", $admin_id, $med_id);
                $stmt_chk_di->execute();
                $di_exists = $stmt_chk_di->get_result()->fetch_assoc();
                $stmt_chk_di->close();

                if ($di_exists) {
                    $stmt_up_di = $conn->prepare("UPDATE district_inventory SET quantity = quantity + ?, last_updated = NOW() WHERE district_admin_id = ? AND medicine_id = ?");
                    $stmt_up_di->bind_param("iii", $qty, $admin_id, $med_id);
                    $stmt_up_di->execute();
                    $stmt_up_di->close();
                } else {
                    $stmt_in_di = $conn->prepare("INSERT INTO district_inventory (district_admin_id, medicine_id, quantity, last_updated) VALUES (?, ?, ?, NOW())");
                    $stmt_in_di->bind_param("iii", $admin_id, $med_id, $qty);
                    $stmt_in_di->execute();
                    $stmt_in_di->close();
                }

                $conn->commit();
                $success_msg = "Inward Consignment Received! + " . number_format($qty) . " units (Batch: " . htmlspecialchars($batch_no) . ") recorded into District Warehouse.";
                $active_tab  = 'inward_logs';
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Failed to record inward consignment: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please select a medicine, enter a valid quantity, batch number, and expiry date.";
        }
    }

    // ---------------------------------------------------------------------
    // B. DIRECT DISPATCH / TRANSFER TO PHC HANDLER
    // ---------------------------------------------------------------------
    if ($_POST['action'] === 'direct_dispatch') {
        $target_phc_id = (int)($_POST['target_phc_id'] ?? 0);
        $med_id        = (int)($_POST['medicine_id'] ?? 0);
        $qty           = (int)($_POST['quantity'] ?? 0);

        if ($target_phc_id > 0 && $med_id > 0 && $qty > 0) {
            $conn->begin_transaction();
            try {
                // 1. Check District Warehouse stock balance with row lock
                $stmt_chk = $conn->prepare("
                    SELECT quantity 
                    FROM district_inventory 
                    WHERE district_admin_id = ? AND medicine_id = ? 
                    FOR UPDATE
                ");
                $stmt_chk->bind_param("ii", $admin_id, $med_id);
                $stmt_chk->execute();
                $dist_row = $stmt_chk->get_result()->fetch_assoc();
                $stmt_chk->close();

                $avail_stock = (int)($dist_row['quantity'] ?? 0);

                if ($avail_stock < $qty) {
                    throw new Exception("Insufficient District Warehouse stock! Available: " . number_format($avail_stock) . " units, Dispatch requested: " . number_format($qty) . " units.");
                }

                // 2. Deduct from District Central Warehouse
                $stmt_deduct = $conn->prepare("
                    UPDATE district_inventory 
                    SET quantity = quantity - ?, last_updated = NOW() 
                    WHERE district_admin_id = ? AND medicine_id = ?
                ");
                $stmt_deduct->bind_param("iii", $qty, $admin_id, $med_id);
                $stmt_deduct->execute();
                $stmt_deduct->close();

                // 3. Increment target PHC's inventory
                $stmt_chk_phc = $conn->prepare("SELECT quantity FROM inventory WHERE phc_id = ? AND medicine_id = ?");
                $stmt_chk_phc->bind_param("ii", $target_phc_id, $med_id);
                $stmt_chk_phc->execute();
                $phc_inv_row = $stmt_chk_phc->get_result()->fetch_assoc();
                $stmt_chk_phc->close();

                if ($phc_inv_row) {
                    $stmt_up_phc = $conn->prepare("UPDATE inventory SET quantity = quantity + ?, last_updated = NOW() WHERE phc_id = ? AND medicine_id = ?");
                    $stmt_up_phc->bind_param("iii", $qty, $target_phc_id, $med_id);
                    $stmt_up_phc->execute();
                    $stmt_up_phc->close();
                } else {
                    $stmt_in_phc = $conn->prepare("INSERT INTO inventory (phc_id, medicine_id, quantity, last_updated) VALUES (?, ?, ?, NOW())");
                    $stmt_in_phc->bind_param("iii", $target_phc_id, $med_id, $qty);
                    $stmt_in_phc->execute();
                    $stmt_in_phc->close();
                }

                // 4. Find staff member ID for target PHC to assign stock_in audit entry
                $staff_id = 0;
                $stmt_st = $conn->prepare("SELECT staff_id FROM staff WHERE phc_id = ? LIMIT 1");
                $stmt_st->bind_param("i", $target_phc_id);
                $stmt_st->execute();
                $st_row = $stmt_st->get_result()->fetch_assoc();
                if ($st_row) {
                    $staff_id = (int)$st_row['staff_id'];
                }
                $stmt_st->close();

                if ($staff_id === 0) {
                    $res_any_st = $conn->query("SELECT staff_id FROM staff LIMIT 1");
                    if ($res_any_st && $r_st = $res_any_st->fetch_assoc()) {
                        $staff_id = (int)$r_st['staff_id'];
                    } else {
                        $staff_id = 1;
                    }
                }

                // 5. Query batch and expiry info from district_stock_in for the PHC receipt
                $batch_no = 'DIST-TRANSFER';
                $exp_date = date('Y-m-d', strtotime('+1 year'));
                $stmt_b = $conn->prepare("SELECT batch_no, expiry_date FROM district_stock_in WHERE district_admin_id = ? AND medicine_id = ? ORDER BY expiry_date ASC LIMIT 1");
                if ($stmt_b) {
                    $stmt_b->bind_param("ii", $admin_id, $med_id);
                    $stmt_b->execute();
                    $b_row = $stmt_b->get_result()->fetch_assoc();
                    if ($b_row) {
                        if (!empty($b_row['batch_no'])) $batch_no = $b_row['batch_no'];
                        if (!empty($b_row['expiry_date'])) $exp_date = $b_row['expiry_date'];
                    }
                    $stmt_b->close();
                }

                // 6. Record into PHC stock_in audit register
                $stmt_si = $conn->prepare("
                    INSERT INTO stock_in (medicine_id, phc_id, quantity_received, expiry_date, staff_id, received_date)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_si->bind_param("iiisi", $med_id, $target_phc_id, $qty, $exp_date, $staff_id);
                $stmt_si->execute();
                $stmt_si->close();

                $conn->commit();
                $success_msg = "Direct Dispatch Successful! " . number_format($qty) . " units transferred from District Warehouse to PHC clinic. Both inventories synchronized.";
                $active_tab  = 'clinics';
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Direct dispatch failed: " . $e->getMessage();
            }
        } else {
            $error_msg = "Please select a valid PHC clinic, medicine, and quantity greater than 0.";
        }
    }
}

// =========================================================================
// 1. FETCH PHC CLINICS & MEDICINES LIST FOR DROPDOWNS & MODALS
// =========================================================================
$phcs_list = [];
$stmt_phc_list = $conn->prepare("SELECT phc_id, phc_name, Taluka FROM phcs WHERE (district = ? OR district_admin_id = ?) ORDER BY phc_name ASC");
if ($stmt_phc_list) {
    $stmt_phc_list->bind_param("si", $district, $admin_id);
    $stmt_phc_list->execute();
    $res_phcs = $stmt_phc_list->get_result();
    while ($p = $res_phcs->fetch_assoc()) {
        $phcs_list[] = $p;
    }
    $stmt_phc_list->close();
}

$medicines_list = [];
$res_meds = $conn->query("SELECT medicine_id, medicine_name, category FROM medicines ORDER BY medicine_name ASC");
if ($res_meds) {
    while ($m = $res_meds->fetch_assoc()) {
        $medicines_list[] = $m;
    }
}

// =========================================================================
// 2. FETCH DISTRICT CENTRAL WAREHOUSE DATA (REAL DATABASE)
// =========================================================================
$district_inventory = [];
$total_warehouse_units = 0;
$total_medicines_stocked = 0;
$low_warehouse_count = 0;
$out_warehouse_count = 0;

$chk_table = $conn->query("SHOW TABLES LIKE 'district_inventory'");
if ($chk_table && $chk_table->num_rows > 0) {
    $sql_di = "
        SELECT di.*, m.medicine_name, m.category, m.tablet_price, m.pack_price,
               (SELECT dsi.batch_no FROM district_stock_in dsi WHERE dsi.district_admin_id = di.district_admin_id AND dsi.medicine_id = di.medicine_id ORDER BY dsi.expiry_date ASC LIMIT 1) AS batch_no,
               (SELECT dsi.expiry_date FROM district_stock_in dsi WHERE dsi.district_admin_id = di.district_admin_id AND dsi.medicine_id = di.medicine_id ORDER BY dsi.expiry_date ASC LIMIT 1) AS expiry_date
        FROM district_inventory di
        JOIN medicines m ON di.medicine_id = m.medicine_id
        WHERE di.district_admin_id = ?
        ORDER BY di.quantity ASC, m.medicine_name ASC
    ";
    $stmt_di = $conn->prepare($sql_di);
    if ($stmt_di) {
        $stmt_di->bind_param("i", $admin_id);
        $stmt_di->execute();
        $res_di = $stmt_di->get_result();
        while ($row = $res_di->fetch_assoc()) {
            $district_inventory[] = $row;
            $qty = (int)$row['quantity'];
            $total_warehouse_units += $qty;
            if ($qty > 0) $total_medicines_stocked++;
            if ($qty == 0) $out_warehouse_count++;
            elseif ($qty < 100) $low_warehouse_count++;
        }
        $stmt_di->close();
    }
}

// =========================================================================
// 3. FETCH METRIC COUNTS FOR RURAL PHC CLINICS MONITOR
// =========================================================================
$stmt_phc_total = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?)
");
$stmt_phc_total->bind_param("si", $district, $admin_id);
$stmt_phc_total->execute();
$total_phc_items = (int)($stmt_phc_total->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_phc_total->close();

$stmt_phc_low = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND i.quantity <= 20 AND i.quantity > 0
");
$stmt_phc_low->bind_param("si", $district, $admin_id);
$stmt_phc_low->execute();
$phc_low_count = (int)($stmt_phc_low->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_phc_low->close();

$stmt_phc_out = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM inventory i 
    JOIN phcs p ON i.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?) AND i.quantity = 0
");
$stmt_phc_out->bind_param("si", $district, $admin_id);
$stmt_phc_out->execute();
$phc_out_count = (int)($stmt_phc_out->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_phc_out->close();

// Query PHC inventory table
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$sql_phc_inv = "
    SELECT i.inventory_id, i.quantity, i.last_updated,
           m.medicine_id, m.medicine_name, m.category,
           p.phc_id, p.phc_name, p.Taluka
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    JOIN phcs p ON i.phc_id = p.phc_id
    WHERE (p.district = ? OR p.district_admin_id = ?)
";

if ($filter === 'low') {
    $sql_phc_inv .= " AND i.quantity <= 20 AND i.quantity > 0 ";
} elseif ($filter === 'out') {
    $sql_phc_inv .= " AND i.quantity = 0 ";
}

if (!empty($search)) {
    $sql_phc_inv .= " AND (m.medicine_name LIKE ? OR m.category LIKE ? OR p.phc_name LIKE ?) ";
}

$sql_phc_inv .= " ORDER BY p.phc_name ASC, m.medicine_name ASC";

$stmt_phc_inv = $conn->prepare($sql_phc_inv);

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_phc_inv->bind_param("sisss", $district, $admin_id, $search_param, $search_param, $search_param);
} else {
    $stmt_phc_inv->bind_param("si", $district, $admin_id);
}

$stmt_phc_inv->execute();
$phc_inventory_result = $stmt_phc_inv->get_result();

// =========================================================================
// 4. FETCH INWARD CONSIGNMENT HISTORY
// =========================================================================
$inward_logs = [];
$chk_inward_table = $conn->query("SHOW TABLES LIKE 'district_stock_in'");
if ($chk_inward_table && $chk_inward_table->num_rows > 0) {
    $sql_inwards = "
        SELECT dsi.*, m.medicine_name, m.category
        FROM district_stock_in dsi
        JOIN medicines m ON dsi.medicine_id = m.medicine_id
        WHERE dsi.district_admin_id = ?
        ORDER BY dsi.received_date DESC
    ";
    $stmt_inw = $conn->prepare($sql_inwards);
    if ($stmt_inw) {
        $stmt_inw->bind_param("i", $admin_id);
        $stmt_inw->execute();
        $res_inw = $stmt_inw->get_result();
        while ($row = $res_inw->fetch_assoc()) {
            $inward_logs[] = $row;
        }
        $stmt_inw->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>District Inventory & Central Warehouse - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_inventory.css">
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
                <h2>District Inventory & Central Warehouse</h2>
                <p>Manage District Central Stock reserves, receive consignments, and monitor rural PHC stock levels across <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <button type="button" class="btn-action-primary" onclick="openInwardModal()">
                    <i class="fas fa-plus-circle"></i>
                    <span>Receive Inward Stock</span>
                </button>
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- DASHBOARD CONTAINER -->
        <div class="dashboard-container">

            <!-- NOTIFICATION BANNERS -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display: flex; align-items: center; background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-check-circle" style="margin-right: 10px; font-size: 18px;"></i>
                    <span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display: flex; align-items: center; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 10px; font-size: 18px;"></i>
                    <span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <!-- TAB NAVIGATION BAR -->
            <div class="tab-nav-bar">
                <button type="button" class="tab-btn <?php echo ($active_tab === 'warehouse') ? 'active' : ''; ?>" id="btnTabWarehouse" onclick="switchTab('warehouse')">
                    <i class="fas fa-warehouse"></i>
                    <span>District Central Warehouse</span>
                    <span class="tab-badge"><?php echo count($district_inventory); ?> items</span>
                </button>
                <button type="button" class="tab-btn <?php echo ($active_tab === 'clinics') ? 'active' : ''; ?>" id="btnTabClinics" onclick="switchTab('clinics')">
                    <i class="fas fa-hospital"></i>
                    <span>Rural PHC Clinics Stock</span>
                    <span class="tab-badge"><?php echo $total_phc_items; ?> entries</span>
                </button>
                <button type="button" class="tab-btn <?php echo ($active_tab === 'inward_logs') ? 'active' : ''; ?>" id="btnTabInwardLogs" onclick="switchTab('inward_logs')">
                    <i class="fas fa-truck-loading"></i>
                    <span>Inward Consignment History</span>
                    <span class="tab-badge"><?php echo count($inward_logs); ?> batches</span>
                </button>
            </div>

            <!-- =========================================================
                 TAB 1: DISTRICT CENTRAL WAREHOUSE STOCK (DEFAULT)
                 ========================================================= -->
            <div id="pane-warehouse" class="tab-pane <?php echo ($active_tab === 'warehouse') ? 'active' : ''; ?>">

                <!-- KPI METRIC CARDS -->
                <div class="kpi-cards-grid">
                    <div class="kpi-card good">
                        <div class="kpi-icon-box"><i class="fas fa-boxes-stacked"></i></div>
                        <div class="kpi-details">
                            <h4>Central Stock Reserve</h4>
                            <div class="kpi-number" id="kpiTotalReserve"><?php echo number_format($total_warehouse_units); ?></div>
                            <small>Total units in district warehouse</small>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon-box"><i class="fas fa-pills"></i></div>
                        <div class="kpi-details">
                            <h4>Cataloged Medicines</h4>
                            <div class="kpi-number"><?php echo $total_medicines_stocked; ?></div>
                            <small>Varieties held in warehouse</small>
                        </div>
                    </div>
                    <div class="kpi-card warn">
                        <div class="kpi-icon-box"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="kpi-details">
                            <h4>Low Buffer Alerts</h4>
                            <div class="kpi-number"><?php echo $low_warehouse_count; ?></div>
                            <small>Medicines below 100 units</small>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon-box"><i class="fas fa-truck-fast"></i></div>
                        <div class="kpi-details">
                            <h4>Auto-Supply Ready</h4>
                            <div class="kpi-number"><?php echo count($phcs_list); ?> PHCs</div>
                            <small>Eligible for instant replenishment</small>
                        </div>
                    </div>
                </div>

                <!-- ACTION & SEARCH BAR -->
                <div class="page-action-bar">
                    <div class="search-form-group">
                        <input type="text" id="warehouseSearchInput" class="search-input-field" placeholder="Search Central Warehouse by Medicine, Category, Batch...">
                        <select id="warehouseCategoryFilter" class="category-select-field">
                            <option value="">All Categories</option>
                            <option value="Tablet">Tablets</option>
                            <option value="Capsule">Capsules</option>
                            <option value="Syrup">Syrups</option>
                            <option value="Injection">Injections</option>
                            <option value="Powder">Powder</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn-action-secondary" onclick="openDispatchModal()">
                            <i class="fas fa-paper-plane" style="color: var(--accent-color);"></i>
                            <span>Quick Dispatch to PHC</span>
                        </button>
                    </div>
                </div>

                <!-- CENTRAL WAREHOUSE DATA TABLE -->
                <div class="data-panel">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 class="section-title">District Central Warehouse Inventory</h3>
                        <span style="font-size: 12px; color: var(--text-secondary);">
                            <i class="fas fa-check-double" style="color: #10b981; margin-right: 4px;"></i> Live stock available for PHC replenishment
                        </span>
                    </div>
                    <table class="data-table" id="warehouseTable">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Category</th>
                                <th>Central Warehouse Balance</th>
                                <th>Batch / Lot No</th>
                                <th>Expiry Date</th>
                                <th>Buffer Status</th>
                                <th>Quick Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($district_inventory)): ?>
                                <?php foreach ($district_inventory as $item): ?>
                                    <?php
                                        $qty = (int)$item['quantity'];
                                        if ($qty == 0) {
                                            $status_text  = 'Out of Stock';
                                            $badge_class  = 'badge-crit';
                                            $qty_style    = 'color: #ef4444;';
                                        } elseif ($qty < 100) {
                                            $status_text  = 'Low Buffer';
                                            $badge_class  = 'badge-warn';
                                            $qty_style    = 'color: #f59e0b;';
                                        } else {
                                            $status_text  = 'Adequate';
                                            $badge_class  = 'badge-good';
                                            $qty_style    = 'color: #10b981;';
                                        }

                                        $exp = !empty($item['expiry_date']) ? date("d M Y", strtotime($item['expiry_date'])) : 'N/A';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 700; font-size: 14px; color: var(--text-primary);">
                                                <?php echo htmlspecialchars($item['medicine_name']); ?>
                                            </div>
                                            <small style="color: var(--text-secondary);">Med ID: #MED-<?php echo str_pad($item['medicine_id'], 3, '0', STR_PAD_LEFT); ?></small>
                                        </td>
                                        <td>
                                            <span class="category-tag">
                                                <?php echo htmlspecialchars($item['category'] ?? 'General'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 16px; font-weight: 800; <?php echo $qty_style; ?>">
                                                <?php echo number_format($qty); ?> Units
                                            </span>
                                        </td>
                                        <td>
                                            <code style="color: var(--text-secondary);"><?php echo htmlspecialchars($item['batch_no'] ?? '-'); ?></code>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px;">
                                                <i class="far fa-calendar-alt" style="color: var(--text-secondary); margin-right: 4px;"></i>
                                                <?php echo $exp; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $badge_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="btn-table-action btn-table-inward"
                                                    onclick="prefillInward('<?php echo $item['medicine_id']; ?>', '<?php echo addslashes($item['medicine_name']); ?>')"
                                                    title="Receive New Inward Batch">
                                                    <i class="fas fa-plus"></i> Inward
                                                </button>
                                                <button type="button" class="btn-table-action btn-table-supply"
                                                    onclick="prefillDispatch('<?php echo $item['medicine_id']; ?>', '<?php echo addslashes($item['medicine_name']); ?>', <?php echo $qty; ?>)"
                                                    title="Direct Supply to PHC">
                                                    <i class="fas fa-share"></i> Supply
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                                        <i class="fas fa-boxes-stacked" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        <strong style="font-size: 14px; color: var(--text-primary);">No Central Warehouse Stock Recorded</strong><br>
                                        <span style="font-size: 12px;">The District Central Warehouse currently has no active stock entries. Click <strong>"Receive Inward Stock"</strong> above to record your first consignment.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- =========================================================
                 TAB 2: RURAL PHC CLINICS STOCK MONITOR
                 ========================================================= -->
            <div id="pane-clinics" class="tab-pane <?php echo ($active_tab === 'clinics') ? 'active' : ''; ?>">

                <!-- ACTION BAR: SEARCH & CHIP FILTERS -->
                <div class="page-action-bar">
                    <form action="inventory.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 550px;">
                        <input type="hidden" name="tab" value="clinics">
                        <?php if ($filter !== 'all'): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <?php endif; ?>
                        <div style="flex: 1;">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Medicine, Category, or PHC Name..." class="search-input-field" style="width: 100%;">
                        </div>
                        <button type="submit" class="btn-action-primary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="inventory.php?tab=clinics&filter=<?php echo htmlspecialchars($filter); ?>" class="btn-action-clear">Clear</a>
                        <?php endif; ?>
                    </form>
                    <div>
                        <a href="medicine_requests.php" class="btn-action-primary" style="text-decoration: none;">
                            <i class="fas fa-file-invoice"></i> View Pending Requisitions
                        </a>
                    </div>
                </div>

                <!-- FILTER CHIPS -->
                <div class="filter-chips-row">
                    <a href="inventory.php?tab=clinics" class="chip-item <?php echo ($filter === 'all') ? 'active' : ''; ?>">
                        All PHC Stock (<?php echo $total_phc_items; ?>)
                    </a>
                    <a href="inventory.php?tab=clinics&filter=low" class="chip-item chip-low <?php echo ($filter === 'low') ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock in PHCs (<?php echo $phc_low_count; ?>)
                    </a>
                    <a href="inventory.php?tab=clinics&filter=out" class="chip-item chip-out <?php echo ($filter === 'out') ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i> Out of Stock in PHCs (<?php echo $phc_out_count; ?>)
                    </a>
                </div>

                <!-- PHC INVENTORY TABLE -->
                <div class="data-panel">
                    <div class="panel-header" style="margin-bottom: 15px;">
                        <h3 class="section-title">Consolidated Rural PHC Inventory Records</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PHC Center</th>
                                <th>Medicine Name</th>
                                <th>Category</th>
                                <th>Clinic Units</th>
                                <th>Clinic Status</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($phc_inventory_result && $phc_inventory_result->num_rows > 0): ?>
                                <?php while ($row = $phc_inventory_result->fetch_assoc()): ?>
                                    <?php
                                        $qty = (int)$row['quantity'];
                                        if ($qty == 0) {
                                            $status_label = 'Out of Stock';
                                            $status_class = 'badge-crit';
                                            $qty_style    = 'color: #ef4444;';
                                        } elseif ($qty <= 20) {
                                            $status_label = 'Low Stock';
                                            $status_class = 'badge-warn';
                                            $qty_style    = 'color: #f59e0b;';
                                        } else {
                                            $status_label = 'In Stock';
                                            $status_class = 'badge-good';
                                            $qty_style    = 'color: #10b981;';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['phc_name']); ?></strong>
                                            <br>
                                            <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['Taluka']); ?></small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                        <td><span class="category-tag"><?php echo htmlspecialchars($row['category'] ?? 'General'); ?></span></td>
                                        <td>
                                            <span style="font-size: 15px; font-weight: bold; <?php echo $qty_style; ?>">
                                                <?php echo $qty; ?> Units
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $status_class; ?>">
                                                <?php echo $status_label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['last_updated'])): ?>
                                                <span style="color: var(--text-secondary); font-size: 12px;">
                                                    <?php echo date("d M Y, h:i A", strtotime($row['last_updated'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--text-secondary); font-size: 12px;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-table-action btn-table-dispatch"
                                                onclick="prefillDispatchForPhc('<?php echo $row['phc_id']; ?>', '<?php echo $row['medicine_id']; ?>', '<?php echo addslashes($row['medicine_name']); ?>')">
                                                <i class="fas fa-truck"></i> Dispatch
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                        <?php echo !empty($search) ? 'No inventory records found matching "' . htmlspecialchars($search) . '"' : 'No inventory records found for PHCs in this district.'; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- =========================================================
                 TAB 3: INWARD CONSIGNMENT HISTORY
                 ========================================================= -->
            <div id="pane-inward_logs" class="tab-pane <?php echo ($active_tab === 'inward_logs') ? 'active' : ''; ?>">
                <div class="data-panel">
                    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 class="section-title">Consignment Inward Register (District Receipts)</h3>
                        <button type="button" class="btn-action-primary" onclick="openInwardModal()">
                            <i class="fas fa-plus"></i> New Inward Entry
                        </button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Receipt ID</th>
                                <th>Medicine Name</th>
                                <th>Quantity Received</th>
                                <th>Batch No</th>
                                <th>Expiry Date</th>
                                <th>Source / Depot</th>
                                <th>Inward Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($inward_logs)): ?>
                                <?php foreach ($inward_logs as $log): ?>
                                    <tr>
                                        <td><strong style="color: var(--accent-color);">#INW-<?php echo str_pad($log['stockin_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($log['medicine_name']); ?></strong></td>
                                        <td>
                                            <span style="font-weight: 700; color: #10b981; font-size: 14px;">
                                                +<?php echo number_format($log['quantity_received']); ?> Units
                                            </span>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($log['batch_no'] ?? '-'); ?></code></td>
                                        <td><span style="font-size: 12px; color: var(--text-secondary);"><i class="far fa-calendar-alt"></i> <?php echo date("d M Y", strtotime($log['expiry_date'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($log['source'] ?? 'State Medical Depot'); ?></td>
                                        <td><small style="color: var(--text-secondary);"><?php echo date("d M Y, h:i A", strtotime($log['received_date'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 40px 20px;">
                                        <i class="fas fa-truck-ramp-box" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                                        <strong style="font-size: 14px; color: var(--text-primary);">No Consignment Inward Records Found</strong><br>
                                        <span style="font-size: 12px;">Inward stock receipts logged for <?php echo $district; ?> District will appear in this audit register.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. District Administration Portal.</p>
        </footer>

    </main>
</div>

<!-- =========================================================
     MODAL 1: RECEIVE INWARD STOCK (DISTRICT WAREHOUSE)
     ========================================================= -->
<div class="modal-overlay" id="inwardModal">
    <div class="modal-card">
        <div class="modal-header-row">
            <div class="modal-header-title">
                <div class="modal-icon-badge modal-icon-inward">
                    <i class="fas fa-truck-ramp-box"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Receive Inward Consignment</h3>
                    <small style="color: var(--text-secondary);">Add medical supplies into District Central Warehouse</small>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeInwardModal()">&times;</button>
        </div>

        <form id="inwardForm" action="inventory.php" method="POST">
            <input type="hidden" name="action" value="receive_inward">
            <div class="form-group-block">
                <label class="form-label">Select Medicine *</label>
                <select name="medicine_id" id="inwardMedicineSelect" class="form-control" required>
                    <option value="">-- Choose Medicine from Master Catalog --</option>
                    <?php foreach ($medicines_list as $m): ?>
                        <option value="<?php echo $m['medicine_id']; ?>">
                            <?php echo htmlspecialchars($m['medicine_name']); ?> (<?php echo htmlspecialchars($m['category']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row form-group-block">
                <div>
                    <label class="form-label">Quantity Received (Units) *</label>
                    <input type="number" name="quantity" id="inwardQty" class="form-control" placeholder="e.g. 5000" min="1" required>
                </div>
                <div>
                    <label class="form-label">Batch / Lot Number *</label>
                    <input type="text" name="batch_no" id="inwardBatch" class="form-control" placeholder="e.g. BATCH-2026-X99" required>
                </div>
            </div>

            <div class="form-row form-group-block">
                <div>
                    <label class="form-label">Expiry Date *</label>
                    <input type="date" name="expiry_date" id="inwardExpiry" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="form-label">Warehouse Bay / Rack</label>
                    <input type="text" name="storage_location" class="form-control" placeholder="e.g. Rack A-4 / Cold Store">
                </div>
            </div>

            <div class="form-group-block">
                <label class="form-label">Consignment Source / Supplier *</label>
                <input type="text" name="supplier" class="form-control" placeholder="e.g. Gujarat Medical Services Corporation Ltd (GMSCL)" required>
            </div>

            <div class="form-group-block">
                <label class="form-label">Invoice / Challan Reference</label>
                <input type="text" name="invoice_ref" class="form-control" placeholder="e.g. CHALLAN-GMSCL-84920">
            </div>

            <div class="form-footer-actions">
                <button type="button" class="btn-form-cancel" onclick="closeInwardModal()">Cancel</button>
                <button type="submit" class="btn-form-submit">
                    <i class="fas fa-check"></i>
                    <span>Save & Update Warehouse Stock</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================
     MODAL 2: QUICK DISPATCH / SUPPLY TO PHC CLINIC
     ========================================================= -->
<div class="modal-overlay" id="dispatchModal">
    <div class="modal-card">
        <div class="modal-header-row">
            <div class="modal-header-title">
                <div class="modal-icon-badge modal-icon-dispatch">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Direct Dispatch to PHC Clinic</h3>
                    <small style="color: var(--text-secondary);">Transfer stock from District Warehouse to rural clinic</small>
                </div>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeDispatchModal()">&times;</button>
        </div>

        <form id="dispatchForm" action="inventory.php" method="POST">
            <input type="hidden" name="action" value="direct_dispatch">
            <div class="form-group-block">
                <label class="form-label">Destination PHC Clinic *</label>
                <select name="target_phc_id" id="dispatchPhcSelect" class="form-control" required>
                    <option value="">-- Select Receiving Primary Health Center --</option>
                    <?php foreach ($phcs_list as $p): ?>
                        <option value="<?php echo $p['phc_id']; ?>">
                            <?php echo htmlspecialchars($p['phc_name']); ?> (Taluka: <?php echo htmlspecialchars($p['Taluka']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-block">
                <label class="form-label">Medicine to Transfer *</label>
                <select name="medicine_id" id="dispatchMedicineSelect" class="form-control" onchange="updateAvailableIndicator()" required>
                    <option value="">-- Choose Medicine --</option>
                    <?php if (!empty($district_inventory)): ?>
                        <?php foreach ($district_inventory as $di): ?>
                            <?php if ((int)$di['quantity'] > 0): ?>
                                <option value="<?php echo $di['medicine_id']; ?>" data-avail="<?php echo $di['quantity']; ?>">
                                    <?php echo htmlspecialchars($di['medicine_name']); ?> (Available in District: <?php echo number_format($di['quantity']); ?> Units)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>-- No Central Warehouse Stock Available to Dispatch --</option>
                    <?php endif; ?>
                </select>
                <div id="availStockNotice" class="avail-stock-notice">
                    <i class="fas fa-info-circle"></i> Currently Available in District: <span id="availStockNum">0</span> Units
                </div>
            </div>

            <div class="form-row form-group-block">
                <div>
                    <label class="form-label">Dispatch Quantity (Units) *</label>
                    <input type="number" name="quantity" id="dispatchQty" class="form-control" placeholder="e.g. 200" min="1" required>
                </div>
                <div>
                    <label class="form-label">Dispatch / Vehicle Reference</label>
                    <input type="text" name="vehicle_ref" class="form-control" placeholder="e.g. Route-North-Van #4">
                </div>
            </div>

            <div class="form-group-block">
                <label class="form-label">Dispatch Remarks / Instructions</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Regular monthly replenishment allocation..."></textarea>
            </div>

            <!-- Auto update info banner -->
            <div class="sync-notice-banner">
                <i class="fas fa-bolt" style="color: #0ea5e9; font-size: 16px;"></i>
                <span><strong>Automated Stock Sync:</strong> Confirming this will automatically deduct units from District Central Warehouse and immediately increment the target PHC's inventory.</span>
            </div>

            <div class="form-footer-actions">
                <button type="button" class="btn-form-cancel" onclick="closeDispatchModal()">Cancel</button>
                <button type="submit" class="btn-form-submit btn-green">
                    <i class="fas fa-paper-plane"></i>
                    <span>Execute Transfer & Sync</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript references -->
<script src="../js/theme.js"></script>
<script src="../js/district_inventory.js"></script>

</body>
</html>
<?php
$stmt_phc_inv->close();
?>
