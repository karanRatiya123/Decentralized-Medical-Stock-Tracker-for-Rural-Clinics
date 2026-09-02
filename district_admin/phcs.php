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
// 1. HANDLE DOCUMENT PROPOSAL UPLOAD (POST) - SIMPLE 2-FIELD FORM
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_phc') {
    $description = trim($_POST['description'] ?? '');

    if (!empty($description) && isset($_FILES['proposal_doc']) && $_FILES['proposal_doc']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['proposal_doc']['tmp_name'];
        $file_name = $_FILES['proposal_doc']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = '../uploads/phc_proposals/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_file_name = 'proposal_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            $destination   = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $doc_path = 'uploads/phc_proposals/' . $new_file_name;

                $stmt_prop = $conn->prepare("
                    INSERT INTO phc_creation_requests (district_admin_id, district, description, document_path, submitted_date, status)
                    VALUES (?, ?, ?, ?, NOW(), 'Pending Review')
                ");
                $stmt_prop->bind_param("isss", $admin_id, $district, $description, $doc_path);

                if ($stmt_prop->execute()) {
                    $prop_id = $stmt_prop->insert_id;
                    $success_msg = "PHC proposal document (#PROP-" . str_pad($prop_id, 4, '0', STR_PAD_LEFT) . ") uploaded & sent to System Admin!";
                } else {
                    $error_msg = "Database error: " . $conn->error;
                }
                $stmt_prop->close();
            } else {
                $error_msg = "Failed to upload document file.";
            }
        } else {
            $error_msg = "Invalid file type. Allowed formats: PDF, DOC, DOCX, JPG, PNG.";
        }
    } else {
        $error_msg = "Please write a description and select the official document file to upload.";
    }
}

// =========================================================================
// 2. FETCH ACTIVE PHC CENTERS IN THIS DISTRICT
// =========================================================================
$search = trim($_GET['search'] ?? '');

$sql_phcs = "
    SELECT p.*,
           (SELECT COUNT(*) FROM staff WHERE phc_id = p.phc_id) AS total_staff,
           (SELECT COUNT(*) FROM doctors WHERE phc_id = p.phc_id AND status = 'Active') AS total_doctors
    FROM phcs p
    WHERE (p.district = ? OR p.district_admin_id = ?)
";

if (!empty($search)) {
    $sql_phcs .= " AND (p.phc_name LIKE ? OR p.Taluka LIKE ? OR p.village LIKE ?) ";
}

$sql_phcs .= " ORDER BY p.phc_name ASC";

$stmt_phcs = $conn->prepare($sql_phcs);

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_phcs->bind_param("sisss", $district, $admin_id, $search_param, $search_param, $search_param);
} else {
    $stmt_phcs->bind_param("si", $district, $admin_id);
}

$stmt_phcs->execute();
$phcs_result = $stmt_phcs->get_result();

// =========================================================================
// 3. FETCH PROPOSALS SENT TO SYSTEM ADMIN
// =========================================================================
$stmt_proposals = $conn->prepare("
    SELECT * FROM phc_creation_requests 
    WHERE district_admin_id = ? OR district = ?
    ORDER BY submitted_date DESC
");
$stmt_proposals->bind_param("is", $admin_id, $district);
$stmt_proposals->execute();
$proposals_result = $stmt_proposals->get_result();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHC Centers & Proposals - District Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/district_admin.css">
    <link rel="stylesheet" href="../css/phcs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- DISTRICT ADMIN SIDEBAR -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-shield-alt brand-icon"></i>
            <div class="brand-text"><h3>MedStock</h3><p>District Admin</p></div>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li class="active"><a href="phcs.php"><i class="fas fa-hospital"></i><span>PHC Centers</span></a></li>
            <li><a href="staff.php"><i class="fas fa-user-tie"></i><span>Staff Management</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory Monitor</span></a></li>
            <li><a href="medicine_requests.php"><i class="fas fa-file-invoice"></i><span>Medicine Requests</span></a></li>
            <li><a href="alerts.php"><i class="fas fa-bell"></i><span>Alerts & Expiry</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>District Reports</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-shield"></i><span>Admin Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <div class="user-details"><h4><?php echo $admin_name; ?></h4><p>District: <?php echo $district; ?></p></div>
            </div>
            <a href="../login.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>PHC Centers & New Facility Proposals</h2>
                <p>Monitor active facilities in <?php echo $district; ?> or submit proposal documents for new PHCs</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display"><i class="far fa-calendar-alt"></i><span><?php echo date("d M Y, h:i A"); ?></span></div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- Alert Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display:flex; background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#10b981; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display:flex; background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#ef4444; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle"></i><span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <!-- ACTION BAR -->
            <div class="page-action-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:15px; flex-wrap:wrap;">
                <form action="phcs.php" method="GET" style="display:flex; gap:10px; flex:1; max-width:450px;">
                    <div class="search-box" style="flex:1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search PHC by Name or Taluka..." style="width:100%; padding:10px 14px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; font-size:13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding:10px 18px; border:none; background:var(--accent-color, #0ea5e9); color:#fff; border-radius:6px; cursor:pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="phcs.php" style="padding:10px 14px; background:var(--bg-card, #1e293b); border:1px solid var(--border-color); color:var(--text-secondary); border-radius:6px; text-decoration:none; font-size:13px;">Clear</a>
                    <?php endif; ?>
                </form>

                <!-- Trigger Button to Open Clean 2-Field Modal -->
                <button type="button" class="btn-primary-action" 
                    onclick="document.getElementById('phcRequestModal').style.cssText = 'display:flex !important; opacity:1 !important; visibility:visible !important; position:fixed !important; top:0 !important; left:0 !important; width:100vw !important; height:100vh !important; background:rgba(0,0,0,0.8) !important; z-index:999999 !important; justify-content:center !important; align-items:center !important;'"
                    style="background-color: var(--accent-color, #0ea5e9); color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-upload"></i> Request New PHC (Upload Document)
                </button>
            </div>

            <!-- TABLE 1: ACTIVE PHC CENTERS -->
            <div class="data-panel" style="margin-bottom: 25px;">
                <div class="panel-header" style="margin-bottom:15px;"><h3 class="section-title">Active Health Centers (<?php echo $district; ?>)</h3></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>PHC ID</th>
                            <th>PHC Name</th>
                            <th>Taluka / Village</th>
                            <th>Contact Phone</th>
                            <th>Staff Count</th>
                            <th>Doctors</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($phcs_result && $phcs_result->num_rows > 0): ?>
                            <?php while ($phc = $phcs_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#PHC-<?php echo str_pad($phc['phc_id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($phc['phc_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($phc['Taluka'] ?: $phc['village'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($phc['contact_no'] ?? '-'); ?></td>
                                    <td><span style="font-weight:bold;"><?php echo (int)$phc['total_staff']; ?> Staff</span></td>
                                    <td><span style="font-weight:bold; color:#10b981;"><?php echo (int)$phc['total_doctors']; ?> Active</span></td>
                                    <td>
                                        <span class="badge-status" style="padding:3px 8px; border-radius:12px; font-size:11px; font-weight:bold; <?php echo ($phc['status'] === 'Active') ? 'background:rgba(16,185,129,0.15); color:#10b981;' : 'background:rgba(239,68,68,0.15); color:#ef4444;'; ?>">
                                            <?php echo htmlspecialchars($phc['status'] ?? 'Active'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--text-secondary); padding:25px;">No PHCs found matching your query.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- TABLE 2: PHC CREATION PROPOSALS SENT TO SYSTEM ADMIN -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom:15px;">
                    <h3 class="section-title"><i class="fas fa-paper-plane" style="color:var(--accent-color);"></i> PHC Document Proposals Sent to System Admin</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Proposal ID</th>
                            <th>Description / Note</th>
                            <th>Attached Document</th>
                            <th>Date Submitted</th>
                            <th>Status</th>
                            <th>System Admin Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($proposals_result && $proposals_result->num_rows > 0): ?>
                            <?php while ($prop = $proposals_result->fetch_assoc()): ?>
                                <?php
                                    $pst = $prop['status'];
                                    if ($pst === 'Approved') {
                                        $badge_class = 'background:rgba(16,185,129,0.15); color:#10b981; border:1px solid #10b981;';
                                    } elseif ($pst === 'Rejected') {
                                        $badge_class = 'background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid #ef4444;';
                                    } else {
                                        $badge_class = 'background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid #f59e0b;';
                                    }
                                ?>
                                <tr>
                                    <td><strong>#PROP-<?php echo str_pad($prop['request_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($prop['description']); ?></td>
                                    <td>
                                        <?php if (!empty($prop['document_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($prop['document_path']); ?>" target="_blank" style="color:var(--accent-color); text-decoration:none; font-weight:bold; display:inline-flex; align-items:center; gap:5px;">
                                                <i class="fas fa-file-pdf"></i> View Attached Document
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($prop['submitted_date'])); ?></td>
                                    <td>
                                        <span class="badge-status" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($pst); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($prop['admin_remarks'])): ?>
                                            <span><?php echo htmlspecialchars($prop['admin_remarks']); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-secondary); font-size:12px;">Pending Review</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; color:var(--text-secondary); padding:25px;">No PHC creation proposals submitted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. District Administration Portal.</p></footer>
    </main>
</div>

<!-- ==============================
     CLEAN 2-FIELD PROPOSAL MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="phcRequestModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:999999;">
    <div class="modal-card" style="background:var(--bg-card, #1e293b); border:1px solid var(--border-color, #334155); border-radius:10px; width:100%; max-width:500px; padding:25px; color:var(--text-primary, #fff); box-shadow:0 10px 30px rgba(0,0,0,0.6);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Request New PHC Center</h3>
            <button type="button" class="modal-close-btn" 
                onclick="document.getElementById('phcRequestModal').style.cssText = 'display:none !important; opacity:0 !important; visibility:hidden !important;'"
                style="background:none; border:none; font-size:26px; color:var(--text-secondary); cursor:pointer;">&times;</button>
        </div>

        <form action="phcs.php" method="POST" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="action" value="request_phc">

            <!-- Field 1: Description / Proposal Note -->
            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Proposal Description / Note *</label>
                <textarea name="description" required rows="4" placeholder="e.g. Proposal to set up a new Primary Health Center at Talala Rural. Complete location and infrastructure plan is attached in the document." style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;"></textarea>
            </div>

            <!-- Field 2: Upload Document -->
            <div class="form-group" style="margin-bottom:25px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Upload Official PHC Proposal Document (PDF / DOC / Image) *</label>
                <input type="file" name="proposal_doc" required accept=".pdf,.doc,.docx,.jpg,.png" style="width:100%; padding:8px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                <small style="color:var(--text-secondary); font-size:11px; display:block; margin-top:4px;">Upload the official proposal document containing all new PHC details, budget, and location.</small>
            </div>

            <button type="submit" style="width:100%; padding:12px; background:var(--accent-color, #0ea5e9); color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
                <i class="fas fa-paper-plane"></i> Send Proposal to System Admin
            </button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
</body>
</html>
<?php
$stmt_phcs->close();
$stmt_proposals->close();
?>