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

// =========================================================================
// 1. HANDLE APPROVE / REJECT REQUEST (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_request') {
    $request_id    = (int)($_POST['request_id'] ?? 0);
    $status        = trim($_POST['status'] ?? '');
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');

    if ($request_id > 0 && in_array($status, ['Approved', 'Rejected'])) {
        $stmt_update = $conn->prepare("
            UPDATE medicine_requests 
            SET status = ?, admin_remarks = ? 
            WHERE request_id = ?
        ");
        $stmt_update->bind_param("ssi", $status, $admin_remarks, $request_id);

        if ($stmt_update->execute()) {
            $success_msg = "Requisition #REQ-" . str_pad($request_id, 4, '0', STR_PAD_LEFT) . " has been officially {$status}.";
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
        $stmt_update->close();
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
// 3. FETCH REQUISITIONS LIST
// =========================================================================
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT mr.request_id, mr.requested_quantity, mr.reason, mr.request_date, mr.status, mr.admin_remarks,
           p.phc_name, p.Taluka,
           m.medicine_name, m.category,
           s.staff_name
    FROM medicine_requests mr
    JOIN phcs p ON mr.phc_id = p.phc_id
    JOIN medicines m ON mr.medicine_id = m.medicine_id
    LEFT JOIN staff s ON mr.staff_id = s.staff_id
    WHERE (p.district = ? OR p.district_admin_id = ?)
";

if (in_array($filter, ['Pending', 'Approved', 'Rejected'])) {
    $sql .= " AND mr.status = ? ";
}

if (!empty($search)) {
    $sql .= " AND (p.phc_name LIKE ? OR m.medicine_name LIKE ? OR mr.request_id LIKE ?) ";
}

$sql .= " ORDER BY mr.request_date DESC";

$stmt_req = $conn->prepare($sql);

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

$stmt_req->execute();
$requests_result = $stmt_req->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Requests - District Admin - MedStock Tracker</title>
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
                <h2>Medicine Requisitions Management</h2>
                <p>Review, approve, or reject stock requests from PHC facilities in <?php echo $district; ?></p>
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
                    <div class="search-box" style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PHC Name, Medicine, or Req ID..." style="width: 100%; padding: 10px 14px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; cursor: pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="medicine_requests.php?filter=<?php echo htmlspecialchars($filter); ?>" style="padding: 10px 14px; background: var(--bg-card, #1e293b); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 6px; text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- FILTER CHIPS -->
            <div class="filter-chips-row" style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
                <a href="medicine_requests.php" class="chip <?php echo ($filter === 'all') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'all') ? 'var(--accent-color, #0ea5e9)' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'all') ? '#fff' : 'var(--text-secondary)'; ?>; border: 1px solid var(--border-color);">
                    All Requests (<?php echo $total_requests; ?>)
                </a>
                <a href="medicine_requests.php?filter=Pending" class="chip <?php echo ($filter === 'Pending') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'Pending') ? '#f59e0b' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'Pending') ? '#fff' : '#f59e0b'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-clock"></i> Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="medicine_requests.php?filter=Approved" class="chip <?php echo ($filter === 'Approved') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'Approved') ? '#10b981' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'Approved') ? '#fff' : '#10b981'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-check-circle"></i> Approved (<?php echo $approved_count; ?>)
                </a>
                <a href="medicine_requests.php?filter=Rejected" class="chip <?php echo ($filter === 'Rejected') ? 'active' : ''; ?>" style="text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; background: <?php echo ($filter === 'Rejected') ? '#ef4444' : 'var(--bg-card, #1e293b)'; ?>; color: <?php echo ($filter === 'Rejected') ? '#fff' : '#ef4444'; ?>; border: 1px solid var(--border-color);">
                    <i class="fas fa-times-circle"></i> Rejected (<?php echo $rejected_count; ?>)
                </a>
            </div>

            <!-- REQUISITIONS DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">PHC Requisitions List</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>PHC Center</th>
                            <th>Requested Medicine</th>
                            <th>Quantity</th>
                            <th>Date / Staff</th>
                            <th>Admin Remarks</th>
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
                                        $badge_style = 'background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981;';
                                    } elseif ($st === 'Rejected') {
                                        $badge_style = 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid #ef4444;';
                                    } else {
                                        $badge_style = 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b;';
                                    }
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
                                    <td><span style="font-weight: bold; font-size: 14px;"><?php echo (int)$row['requested_quantity']; ?></span></td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($row['request_date'])); ?><br>
                                        <small style="color:var(--text-secondary);">By: <?php echo htmlspecialchars($row['staff_name'] ?? 'Staff'); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['admin_remarks'])): ?>
                                            <span style="color: var(--text-primary); font-size: 12px;"><?php echo htmlspecialchars($row['admin_remarks']); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status" style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; <?php echo $badge_style; ?>">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($st === 'Pending'): ?>
                                            <!-- Button to trigger Modal and pass Request ID dynamically -->
                                            <button type="button" 
                                                onclick="
                                                    document.getElementById('modalReqId').value = '<?php echo $row['request_id']; ?>';
                                                    document.getElementById('modalReqDisplay').innerText = '#REQ-<?php echo str_pad($row['request_id'], 4, '0', STR_PAD_LEFT); ?>';
                                                    document.getElementById('processModal').style.cssText = 'display: flex !important; opacity: 1 !important; visibility: visible !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0,0,0,0.8) !important; z-index: 999999 !important; justify-content: center !important; align-items: center !important;';
                                                "
                                                style="padding: 6px 12px; background: rgba(14,165,233,0.15); color: #0ea5e9; border: 1px solid #0ea5e9; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">
                                                Process
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;"><i class="fas fa-lock"></i> Processed</span>
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

<!-- ==============================
     PROCESS REQUISITION MODAL
     ============================== -->
<div class="modal-overlay" id="processModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 999999;">
    <div class="modal-card" style="background: var(--bg-card, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 10px; width: 100%; max-width: 450px; padding: 25px; color: var(--text-primary, #fff); box-shadow: 0 10px 30px rgba(0,0,0,0.6);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Process Requisition <span id="modalReqDisplay" style="color: var(--accent-color);"></span></h3>
            <button type="button" class="modal-close-btn" 
                onclick="document.getElementById('processModal').style.cssText = 'display: none !important; opacity: 0 !important; visibility: hidden !important;'"
                style="background: none; border: none; font-size: 26px; color: var(--text-secondary, #94a3b8); cursor: pointer; padding: 5px 10px;">&times;</button>
        </div>

        <form action="medicine_requests.php" method="POST" class="modal-form">
            <input type="hidden" name="action" value="process_request">
            <!-- Hidden input that will receive the ID via JavaScript -->
            <input type="hidden" name="request_id" id="modalReqId" value="">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Decision / Status *</label>
                <select name="status" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #475569); border-radius: 6px; box-sizing: border-box;">
                    <option value="">-- Select Decision --</option>
                    <option value="Approved">✅ Approve & Allocate Stock</option>
                    <option value="Rejected">❌ Reject Request</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Admin Remarks (Optional)</label>
                <textarea name="admin_remarks" rows="3" placeholder="e.g. Approved, dispatching with tomorrow's supply vehicle..." style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;"></textarea>
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: var(--accent-color, #0ea5e9); color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer;">
                Submit Decision
            </button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
</body>
</html>
<?php
$stmt_req->close();
?>