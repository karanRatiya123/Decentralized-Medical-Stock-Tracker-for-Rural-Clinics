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

// Check if district_inventory table exists
$has_di_table = false;
$chk_table = $conn->query("SHOW TABLES LIKE 'district_inventory'");
if ($chk_table && $chk_table->num_rows > 0) {
    $has_di_table = true;
}

// =========================================================================
// 1. HANDLE APPROVE / REJECT REQUEST (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_request') {
    $request_id    = (int)($_POST['request_id'] ?? 0);
    $status        = trim($_POST['status'] ?? '');
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    $supply_qty    = (int)($_POST['supply_quantity'] ?? 0);

    if ($request_id > 0 && in_array($status, ['Approved', 'Rejected'])) {
        if ($status === 'Rejected') {
            // Rejection flow
            $stmt_update = $conn->prepare("UPDATE medicine_requests SET status = 'Rejected', admin_remarks = ? WHERE request_id = ?");
            $stmt_update->bind_param("si", $admin_remarks, $request_id);
            if ($stmt_update->execute()) {
                $success_msg = "Requisition #REQ-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " has been officially Rejected.";
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
            $stmt_update->close();
        } elseif ($status === 'Approved') {
            // Auto-supply workflow (Atomic supply chain transaction)
            $conn->begin_transaction();
            try {
                // 1. Fetch requisition row details
                $stmt_req_info = $conn->prepare("SELECT phc_id, medicine_id, staff_id, requested_quantity FROM medicine_requests WHERE request_id = ? FOR UPDATE");
                $stmt_req_info->bind_param("i", $request_id);
                $stmt_req_info->execute();
                $req_data = $stmt_req_info->get_result()->fetch_assoc();
                $stmt_req_info->close();

                if (!$req_data) {
                    throw new Exception("Requisition #REQ-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " not found.");
                }

                $phc_id      = (int)$req_data['phc_id'];
                $med_id      = (int)$req_data['medicine_id'];
                $staff_id    = (int)($req_data['staff_id'] ?? 1);
                $req_qty     = (int)$req_data['requested_quantity'];
                $dispatch_qty = ($supply_qty > 0) ? $supply_qty : $req_qty;

                // 2. Verify District Central Warehouse available stock
                $stmt_dist_chk = $conn->prepare("SELECT quantity FROM district_inventory WHERE district_admin_id = ? AND medicine_id = ? FOR UPDATE");
                $stmt_dist_chk->bind_param("ii", $admin_id, $med_id);
                $stmt_dist_chk->execute();
                $dist_stock_row = $stmt_dist_chk->get_result()->fetch_assoc();
                $stmt_dist_chk->close();

                $avail_dist_stock = (int)($dist_stock_row['quantity'] ?? 0);

                if ($avail_dist_stock < $dispatch_qty) {
                    throw new Exception("Insufficient District Warehouse stock! Available: " . number_format($avail_dist_stock) . " units, Dispatch requested: " . number_format($dispatch_qty) . " units.");
                }

                // 3. Deduct from District Central Warehouse
                $stmt_deduct = $conn->prepare("UPDATE district_inventory SET quantity = quantity - ?, last_updated = NOW() WHERE district_admin_id = ? AND medicine_id = ?");
                $stmt_deduct->bind_param("iii", $dispatch_qty, $admin_id, $med_id);
                $stmt_deduct->execute();
                $stmt_deduct->close();

                // 4. Increment PHC Clinic Inventory
                $stmt_phc_inv_chk = $conn->prepare("SELECT quantity FROM inventory WHERE phc_id = ? AND medicine_id = ?");
                $stmt_phc_inv_chk->bind_param("ii", $phc_id, $med_id);
                $stmt_phc_inv_chk->execute();
                $phc_has_inv = $stmt_phc_inv_chk->get_result()->fetch_assoc();
                $stmt_phc_inv_chk->close();

                if ($phc_has_inv) {
                    $stmt_up_phc = $conn->prepare("UPDATE inventory SET quantity = quantity + ?, last_updated = NOW() WHERE phc_id = ? AND medicine_id = ?");
                    $stmt_up_phc->bind_param("iii", $dispatch_qty, $phc_id, $med_id);
                    $stmt_up_phc->execute();
                    $stmt_up_phc->close();
                } else {
                    $stmt_in_phc = $conn->prepare("INSERT INTO inventory (phc_id, medicine_id, quantity, last_updated) VALUES (?, ?, ?, NOW())");
                    $stmt_in_phc->bind_param("iii", $phc_id, $med_id, $dispatch_qty);
                    $stmt_in_phc->execute();
                    $stmt_in_phc->close();
                }

                // 5. Query batch and expiry info from district_stock_in for logging
                $batch_no = 'DIST-DISPATCH';
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

                // 6. Log entry into PHC stock_in audit trail
                $stmt_si = $conn->prepare("
                    INSERT INTO stock_in (medicine_id, phc_id, quantity_received, expiry_date, staff_id, received_date)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_si->bind_param("iiisi", $med_id, $phc_id, $dispatch_qty, $exp_date, $staff_id);
                $stmt_si->execute();
                $stmt_si->close();

                // 7. Update Requisition status to Approved
                $final_remarks = !empty($admin_remarks) ? $admin_remarks : "Supplied " . number_format($dispatch_qty) . " units from District Central Warehouse.";
                $stmt_up = $conn->prepare("UPDATE medicine_requests SET status = 'Approved', admin_remarks = ? WHERE request_id = ?");
                $stmt_up->bind_param("si", $final_remarks, $request_id);
                $stmt_up->execute();
                $stmt_up->close();

                $conn->commit();
                $success_msg = "Requisition #REQ-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " Approved & Dispatched! " . number_format($dispatch_qty) . " units deducted from District Warehouse and credited to PHC clinic inventory.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = $e->getMessage();
            }
        }
    } else {
        $error_msg = "Invalid request or status provided.";
    }
}

// =========================================================================
// 2. FETCH METRIC COUNTS FOR FILTER CHIPS
// =========================================================================
function getStatusCount($conn, $status, $district, $admin_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM medicine_requests mr 
        JOIN phcs p ON mr.phc_id = p.phc_id 
        WHERE (p.district = ? OR p.district_admin_id = ?) AND mr.status = ?
    ");
    $stmt->bind_param("sis", $district, $admin_id, $status);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $count;
}

$pending_count  = getStatusCount($conn, 'Pending', $district, $admin_id);
$approved_count = getStatusCount($conn, 'Approved', $district, $admin_id);
$rejected_count = getStatusCount($conn, 'Rejected', $district, $admin_id);

$stmt_total = $conn->prepare("
    SELECT COUNT(*) AS cnt 
    FROM medicine_requests mr 
    JOIN phcs p ON mr.phc_id = p.phc_id 
    WHERE (p.district = ? OR p.district_admin_id = ?)
");
$stmt_total->bind_param("si", $district, $admin_id);
$stmt_total->execute();
$total_requests = (int)($stmt_total->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt_total->close();

// =========================================================================
// 3. FETCH REQUISITIONS LIST (WITH DISTRICT CENTRAL STOCK CHECK)
// =========================================================================
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');

if ($has_di_table) {
    $sql = "
        SELECT mr.request_id, mr.phc_id, mr.medicine_id, mr.requested_quantity, mr.reason, mr.request_date, mr.status, mr.admin_remarks,
               p.phc_name, p.Taluka,
               m.medicine_name, m.category,
               s.staff_name,
               COALESCE(di.quantity, 0) AS district_available_stock
        FROM medicine_requests mr
        JOIN phcs p ON mr.phc_id = p.phc_id
        JOIN medicines m ON mr.medicine_id = m.medicine_id
        LEFT JOIN staff s ON mr.staff_id = s.staff_id
        LEFT JOIN district_inventory di ON (mr.medicine_id = di.medicine_id AND di.district_admin_id = ?)
        WHERE (p.district = ? OR p.district_admin_id = ?)
    ";
} else {
    $sql = "
        SELECT mr.request_id, mr.phc_id, mr.medicine_id, mr.requested_quantity, mr.reason, mr.request_date, mr.status, mr.admin_remarks,
               p.phc_name, p.Taluka,
               m.medicine_name, m.category,
               s.staff_name,
               0 AS district_available_stock
        FROM medicine_requests mr
        JOIN phcs p ON mr.phc_id = p.phc_id
        JOIN medicines m ON mr.medicine_id = m.medicine_id
        LEFT JOIN staff s ON mr.staff_id = s.staff_id
        WHERE (p.district = ? OR p.district_admin_id = ?)
    ";
}

if (in_array($filter, ['Pending', 'Approved', 'Rejected'])) {
    $sql .= " AND mr.status = ? ";
}

if (!empty($search)) {
    $sql .= " AND (p.phc_name LIKE ? OR m.medicine_name LIKE ? OR mr.request_id LIKE ?) ";
}

$sql .= " ORDER BY mr.request_date DESC";

$stmt_req = $conn->prepare($sql);

if ($has_di_table) {
    if (in_array($filter, ['Pending', 'Approved', 'Rejected'])) {
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $stmt_req->bind_param("isiss", $admin_id, $district, $admin_id, $filter, $search_param, $search_param, $search);
        } else {
            $stmt_req->bind_param("isis", $admin_id, $district, $admin_id, $filter);
        }
    } else {
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $stmt_req->bind_param("isiss", $admin_id, $district, $admin_id, $search_param, $search_param, $search);
        } else {
            $stmt_req->bind_param("isi", $admin_id, $district, $admin_id);
        }
    }
} else {
    if (in_array($filter, ['Pending', 'Approved', 'Rejected'])) {
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $stmt_req->bind_param("sisss", $district, $admin_id, $filter, $search_param, $search_param, $search);
        } else {
            $stmt_req->bind_param("sis", $district, $admin_id, $filter);
        }
    } else {
        if (!empty($search)) {
            $search_param = "%" . $search . "%";
            $stmt_req->bind_param("siss", $district, $admin_id, $search_param, $search_param, $search);
        } else {
            $stmt_req->bind_param("si", $district, $admin_id);
        }
    }
}

$stmt_req->execute();
$requests_result = $stmt_req->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests & Auto-Supply - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_medicine_requests.css">
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
            <li class="active"><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
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
        
        <header class="top-header">
            <div class="header-left">
                <h2>Medicine Requisitions & Auto-Supply</h2>
                <p>Review PHC requests, check District Warehouse reserves, and dispatch medicine stock with automatic sync across <?php echo $district; ?></p>
            </div>
            <div class="header-right">
                <a href="inventory.php" class="btn-form-cancel" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-warehouse" style="color: var(--accent-color);"></i>
                    <span>Central Warehouse Stock</span>
                </a>
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-container">

            <!-- NOTIFICATION BANNERS -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display: flex; background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                    <span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display: flex; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                    <span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <!-- ACTION BAR: SEARCH -->
            <div class="page-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
                <form action="medicine_requests.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 550px;">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PHC Name, Medicine, or Req ID..." class="form-control" style="width: 100%;">
                    </div>
                    <button type="submit" class="btn-process-req">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="medicine_requests.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn-form-cancel" style="text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- FILTER CHIPS -->
            <div class="filter-chips-row" style="display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap;">
                <a href="medicine_requests.php" class="chip-item <?php echo ($filter === 'all') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: <?php echo ($filter === 'all') ? 'var(--accent-color)' : 'var(--bg-card)'; ?>; color: <?php echo ($filter === 'all') ? '#fff' : 'var(--text-secondary)'; ?>;">
                    All Requests (<?php echo $total_requests; ?>)
                </a>
                <a href="medicine_requests.php?filter=Pending" class="chip-item <?php echo ($filter === 'Pending') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: <?php echo ($filter === 'Pending') ? '#f59e0b' : 'var(--bg-card)'; ?>; color: <?php echo ($filter === 'Pending') ? '#fff' : '#f59e0b'; ?>;">
                    <i class="fas fa-clock"></i> Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="medicine_requests.php?filter=Approved" class="chip-item <?php echo ($filter === 'Approved') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: <?php echo ($filter === 'Approved') ? '#10b981' : 'var(--bg-card)'; ?>; color: <?php echo ($filter === 'Approved') ? '#fff' : '#10b981'; ?>;">
                    <i class="fas fa-check-circle"></i> Approved & Supplied (<?php echo $approved_count; ?>)
                </a>
                <a href="medicine_requests.php?filter=Rejected" class="chip-item <?php echo ($filter === 'Rejected') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid var(--border-color); background: <?php echo ($filter === 'Rejected') ? '#ef4444' : 'var(--bg-card)'; ?>; color: <?php echo ($filter === 'Rejected') ? '#fff' : '#ef4444'; ?>;">
                    <i class="fas fa-times-circle"></i> Rejected (<?php echo $rejected_count; ?>)
                </a>
            </div>

            <!-- REQUISITIONS DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 class="section-title">PHC Requisitions & Supply Actions</h3>
                    <span style="font-size: 12px; color: var(--text-secondary);">
                        <i class="fas fa-bolt" style="color: var(--accent-color);"></i> Auto-deducts district stock & auto-credits PHC clinic
                    </span>
                </div>
                <table class="data-table" id="requestsTable">
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>PHC Center</th>
                            <th>Requested Medicine</th>
                            <th>Requested Qty</th>
                            <th>District Central Stock</th>
                            <th>Date / Staff</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                            <?php while ($row = $requests_result->fetch_assoc()): ?>
                                <?php
                                    $st = $row['status'];
                                    if ($st === 'Approved') {
                                        $badge_class = 'badge-approved';
                                    } elseif ($st === 'Rejected') {
                                        $badge_class = 'badge-rejected';
                                    } else {
                                        $badge_class = 'badge-pending';
                                    }

                                    $dist_stock = (int)($row['district_available_stock'] ?? 0);
                                    $req_qty    = (int)$row['requested_quantity'];
                                    $has_enough = ($dist_stock >= $req_qty);
                                ?>
                                <tr>
                                    <td><strong>#REQ-<?php echo str_pad($row['request_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['phc_name']); ?></strong><br>
                                        <small style="color:var(--text-secondary);"><?php echo htmlspecialchars($row['Taluka']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong><br>
                                        <small style="color:var(--text-secondary);">Reason: <?php echo htmlspecialchars($row['reason'] ?: '-'); ?></small>
                                    </td>
                                    <td>
                                        <span style="font-weight: 800; font-size: 15px; color: var(--text-primary);">
                                            <?php echo number_format($req_qty); ?> Units
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($has_enough): ?>
                                            <span class="stock-indicator-badge stock-sufficient">
                                                <i class="fas fa-check"></i> <?php echo number_format($dist_stock); ?> Units Available
                                            </span>
                                        <?php else: ?>
                                            <span class="stock-indicator-badge stock-shortage">
                                                <i class="fas fa-triangle-exclamation"></i> Only <?php echo number_format($dist_stock); ?> in Warehouse
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($row['request_date'])); ?><br>
                                        <small style="color:var(--text-secondary);">By: <?php echo htmlspecialchars($row['staff_name'] ?? 'Staff'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($st === 'Pending'): ?>
                                            <button type="button" class="btn-process-req"
                                                onclick="openProcessModal(
                                                    '<?php echo $row['request_id']; ?>',
                                                    '<?php echo addslashes($row['phc_name']); ?>',
                                                    '<?php echo addslashes($row['medicine_name']); ?>',
                                                    <?php echo $req_qty; ?>,
                                                    <?php echo $dist_stock; ?>,
                                                    this.closest('tr')
                                                )">
                                                <i class="fas fa-truck-ramp-box"></i>
                                                <span>Supply & Process</span>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">
                                                <i class="fas fa-check-double" style="color: #10b981;"></i> Fulfilled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                    <?php echo !empty($search) ? 'No requisitions found matching your criteria.' : 'No medicine requisitions found.'; ?>
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

<!-- =========================================================
     PROCESS & AUTO-SUPPLY REQUISITION MODAL
     ========================================================= -->
<div class="modal-overlay" id="processModal">
    <div class="modal-card">
        <div class="modal-header-row">
            <div>
                <h3>Process Requisition <span id="modalReqDisplay" style="color: var(--accent-color);"></span></h3>
                <small id="modalReqSubtext">Review stock and execute automatic supply</small>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeProcessModal()">&times;</button>
        </div>

        <!-- REQUISITION & WAREHOUSE LIVE STATUS CARD -->
        <div class="modal-summary-box">
            <div class="modal-summary-row">
                <span class="modal-summary-label">Requesting Facility:</span>
                <strong id="modalPhcName" class="modal-summary-value">-</strong>
            </div>
            <div class="modal-summary-row">
                <span class="modal-summary-label">Requested Medicine:</span>
                <strong id="modalMedName" class="modal-summary-value">-</strong>
            </div>
            <div class="modal-summary-row">
                <span class="modal-summary-label">Quantity Demanded:</span>
                <strong id="modalReqQty" class="modal-summary-value" style="color: var(--accent-color);">-</strong>
            </div>
            <div class="modal-summary-row">
                <span class="modal-summary-label">District Warehouse Balance:</span>
                <strong id="modalDistStock" class="modal-summary-value">-</strong>
            </div>
        </div>

        <form action="medicine_requests.php" method="POST">
            <input type="hidden" name="action" value="process_request">
            <input type="hidden" name="request_id" id="modalReqId" value="">

            <div class="form-group-block">
                <label class="form-label">Action Decision *</label>
                <select name="status" id="decisionStatus" required onchange="toggleSupplyOptions()" class="form-control">
                    <option value="Approved">🚚 Approve & Auto-Supply from District Stock</option>
                    <option value="Rejected">❌ Reject Requisition</option>
                </select>
            </div>

            <!-- Auto-Supply Config Box -->
            <div id="supplyConfigBox" class="auto-supply-box">
                <div class="form-group-block">
                    <label class="form-label">Quantity to Fulfill & Dispatch *</label>
                    <input type="number" name="supply_quantity" id="supplyQtyInput" min="1" required class="form-control" style="font-size: 14px; font-weight: 700;">
                    <small style="color: var(--text-secondary);">Defaulted to full requested quantity. Can be edited for partial supply.</small>
                </div>

                <!-- Auto update note -->
                <div class="sync-info-card">
                    <i class="fas fa-sync-alt"></i>
                    <span><strong>Auto-Inventory Sync:</strong> Units will be deducted from District Central Warehouse and immediately credited to PHC clinic inventory upon submission.</span>
                </div>
            </div>

            <div class="form-group-block">
                <label class="form-label">Dispatch / Admin Remarks</label>
                <textarea name="admin_remarks" id="modalRemarks" rows="2" placeholder="e.g. Dispatched via District Medical Supply Van #2..." class="form-control"></textarea>
            </div>

            <div class="form-footer-actions">
                <button type="button" class="btn-form-cancel" onclick="closeProcessModal()">Cancel</button>
                <button type="submit" id="btnSubmitDecision" class="btn-form-submit">
                    <i class="fas fa-check"></i>
                    <span>Confirm & Execute Dispatch</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript references -->
<script src="../js/theme.js"></script>
<script src="../js/district_medicine_requests.js"></script>

</body>
</html>
<?php
$stmt_req->close();
?>
