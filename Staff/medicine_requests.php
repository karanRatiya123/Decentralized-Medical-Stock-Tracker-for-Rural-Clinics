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

$preselected_med_id = (int)($_GET['medicine_id'] ?? 0);

$success_msg = '';
$error_msg   = '';

// =========================================================================
// 1. HANDLE NEW MEDICINE REQUEST FORM SUBMISSION (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $medicine_id        = (int)($_POST['medicine_id'] ?? 0);
    $requested_quantity = (int)($_POST['quantity'] ?? 0);
    $reason             = trim($_POST['reason'] ?? '');

    if ($medicine_id > 0 && $requested_quantity > 0) {
        $stmt_req = $conn->prepare("
            INSERT INTO medicine_requests (phc_id, medicine_id, staff_id, requested_quantity, reason, request_date, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'Pending')
        ");
        $stmt_req->bind_param("iiiis", $phc_id, $medicine_id, $staff_id, $requested_quantity, $reason);

        if ($stmt_req->execute()) {
            $request_id = $stmt_req->insert_id;
            $success_msg = "Stock requisition submitted to District Admin successfully! Requisition ID: #REQ-" . str_pad($request_id, 4, '0', STR_PAD_LEFT);
        } else {
            $error_msg = "Error submitting requisition: " . $conn->error;
        }
        $stmt_req->close();
    } else {
        $error_msg = "Please choose a medicine and enter a valid quantity.";
    }
}

// =========================================================================
// 2. FETCH MASTER MEDICINES FOR MODAL DROPDOWN
// =========================================================================
$medicines_list = $conn->query("SELECT medicine_id, medicine_name, category FROM medicines ORDER BY medicine_name ASC");

// =========================================================================
// 3. FETCH REQUISITION HISTORY LOGS
// =========================================================================
$status_filter = trim($_GET['status'] ?? 'all');
$search        = trim($_GET['search'] ?? '');

$sql = "
    SELECT r.request_id, r.requested_quantity, r.reason, r.request_date, r.status, r.admin_remarks,
           m.medicine_name, m.category,
           st.staff_name
    FROM medicine_requests r
    JOIN medicines m ON r.medicine_id = m.medicine_id
    LEFT JOIN staff st ON r.staff_id = st.staff_id
    WHERE r.phc_id = ?
";

if ($status_filter !== 'all') {
    $sql .= " AND r.status = ? ";
}

if (!empty($search)) {
    $sql .= " AND (m.medicine_name LIKE ? OR r.request_id LIKE ?) ";
}

$sql .= " ORDER BY r.request_date DESC";

$stmt_list = $conn->prepare($sql);

if ($status_filter !== 'all') {
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $stmt_list->bind_param("isss", $phc_id, $status_filter, $search_param, $search);
    } else {
        $stmt_list->bind_param("is", $phc_id, $status_filter);
    }
} else {
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $stmt_list->bind_param("iss", $phc_id, $search_param, $search);
    } else {
        $stmt_list->bind_param("i", $phc_id);
    }
}

$stmt_list->execute();
$requests_result = $stmt_list->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/medicine_requests.css">
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
        
        <header class="top-header">
            <div class="header-left">
                <h2>Medicine Requisitions</h2>
                <p><?php echo $phc_name; ?> - Request Stock Restocking from District Health Office</p>
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

            <!-- NOTIFICATION BANNERS -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display: flex; background: rgba(16,185,129,0.15); border: 1px solid #10b981; color: #10b981; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display: flex; background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <!-- ACTION BAR -->
            <div class="page-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
                <form action="medicine_requests.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 450px;">
                    <div class="search-box" style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Medicine Name or ID..." style="width: 100%; padding: 10px 14px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; cursor: pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="medicine_requests.php" style="padding: 10px 14px; background: var(--bg-card, #1e293b); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 6px; text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>

                <!-- Unbreakable Modal Trigger Button -->
                <button type="button" class="btn-primary-action" id="openRequestModalBtn"
                    onclick="document.getElementById('requestModal').style.cssText = 'display: flex !important; opacity: 1 !important; visibility: visible !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0,0,0,0.8) !important; z-index: 999999 !important; justify-content: center !important; align-items: center !important;'"
                    style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-paper-plane"></i> Request New Stock
                </button>
            </div>

            <!-- REQUISITIONS HISTORY TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">Submitted Requisitions History</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Quantity Requested</th>
                            <th>Reason / Urgency</th>
                            <th>Date Requested</th>
                            <th>Status</th>
                            <th>Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                            <?php while ($row = $requests_result->fetch_assoc()): ?>
                                <?php
                                    $st = $row['status'];
                                    if ($st === 'Approved') {
                                        $badge_style = 'background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981;';
                                    } elseif ($st === 'Rejected') {
                                        $badge_style = 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444;';
                                    } else {
                                        $badge_style = 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                                    }
                                ?>
                                <tr>
                                    <td><strong>#REQ-<?php echo str_pad($row['request_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'General'); ?></td>
                                    <td><span style="font-weight: bold; font-size: 14px;"><?php echo (int)$row['requested_quantity']; ?> Units</span></td>
                                    <td><?php echo htmlspecialchars($row['reason'] ?? '-'); ?></td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($row['request_date'])); ?></td>
                                    <td>
                                        <span class="badge-status" style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; <?php echo $badge_style; ?>">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['admin_remarks'])): ?>
                                            <span style="color: var(--text-primary); font-size: 13px;"><?php echo htmlspecialchars($row['admin_remarks']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">Awaiting Review</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                    <?php echo !empty($search) ? 'No requisitions found matching "' . htmlspecialchars($search) . '"' : 'No medicine requisitions submitted yet. Click "Request New Stock" to create one.'; ?>
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

<!-- ==============================
     CREATE REQUISITION MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="requestModal" style="display: <?php echo ($preselected_med_id > 0) ? 'flex' : 'none'; ?>; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 999999;">
    <div class="modal-card" style="background: var(--bg-card, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 10px; width: 100%; max-width: 500px; padding: 25px; color: var(--text-primary, #fff); box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Submit Stock Requisition</h3>
            <!-- Close Button with direct inline dismiss handler -->
            <button type="button" class="modal-close-btn" id="closeRequestModalBtn"
                onclick="document.getElementById('requestModal').style.cssText = 'display: none !important; opacity: 0 !important; visibility: hidden !important;'"
                style="background: none; border: none; font-size: 26px; color: var(--text-secondary, #94a3b8); cursor: pointer; padding: 5px 10px;">&times;</button>
        </div>

        <form action="medicine_requests.php" method="POST" id="requestForm" class="modal-form">
            <input type="hidden" name="action" value="create_request">

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="medicine_id" style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Select Medicine *</label>
                <select id="medicine_id" name="medicine_id" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #475569); border-radius: 6px; box-sizing: border-box;">
                    <option value="">-- Choose Medicine --</option>
                    <?php if ($medicines_list && $medicines_list->num_rows > 0): ?>
                        <?php while ($med = $medicines_list->fetch_assoc()): ?>
                            <option value="<?php echo $med['medicine_id']; ?>" <?php echo ($preselected_med_id === (int)$med['medicine_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($med['medicine_name']); ?> (<?php echo htmlspecialchars($med['category'] ?? 'General'); ?>)
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="quantity" style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Required Quantity (Units) *</label>
                <input type="number" id="quantity" name="quantity" min="1" required placeholder="e.g. 500" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="reason" style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Reason / Urgency Remarks</label>
                <textarea id="reason" name="reason" rows="3" placeholder="e.g. Current stock depleted due to high seasonal OPD rush." style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Requesting Staff</label>
                    <input type="text" value="<?php echo $staff_name; ?>" disabled style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-secondary); opacity: 0.8; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Target Authority</label>
                    <input type="text" value="District Health Officer" disabled style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-secondary); opacity: 0.8; box-sizing: border-box;">
                </div>
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: var(--accent-color, #0ea5e9); color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer;">
                <i class="fas fa-paper-plane"></i> Send Requisition to District Admin
            </button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>

</body>
</html>
<?php
$stmt_list->close();
?>