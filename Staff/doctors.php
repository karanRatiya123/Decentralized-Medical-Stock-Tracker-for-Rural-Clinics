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

$success_msg = '';
$error_msg   = '';

// =========================================================================
// 1. HANDLE ADD NEW DOCTOR FORM (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    $doctor_name    = trim($_POST['doctor_name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $doctor_type    = trim($_POST['doctor_type'] ?? 'Permanent');
    $visiting_days  = trim($_POST['visiting_days'] ?? '');
    $contact_no     = trim($_POST['contact_no'] ?? '');
    $status         = trim($_POST['status'] ?? 'Active');

    if (!empty($doctor_name)) {
        $stmt_doc = $conn->prepare("
            INSERT INTO doctors (phc_id, doctor_name, specialization, doctor_type, visiting_days, contact_no, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_doc->bind_param("issssss", $phc_id, $doctor_name, $specialization, $doctor_type, $visiting_days, $contact_no, $status);

        if ($stmt_doc->execute()) {
            $success_msg = "Doctor record added successfully!";
        } else {
            $error_msg = "Error adding doctor: " . $conn->error;
        }
        $stmt_doc->close();
    } else {
        $error_msg = "Please enter the doctor's name.";
    }
}

// =========================================================================
// 2. FETCH DOCTORS LIST FOR THIS PHC
// =========================================================================
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_list = $conn->prepare("
        SELECT d.*, 
               (SELECT COUNT(*) FROM patient_visits WHERE doctor_id = d.doctor_id) AS total_consultations
        FROM doctors d
        WHERE d.phc_id = ? AND (d.doctor_name LIKE ? OR d.specialization LIKE ?)
        ORDER BY d.status ASC, d.doctor_name ASC
    ");
    $stmt_list->bind_param("iss", $phc_id, $search_param, $search_param);
} else {
    $stmt_list = $conn->prepare("
        SELECT d.*, 
               (SELECT COUNT(*) FROM patient_visits WHERE doctor_id = d.doctor_id) AS total_consultations
        FROM doctors d
        WHERE d.phc_id = ?
        ORDER BY d.status ASC, d.doctor_name ASC
    ");
    $stmt_list->bind_param("i", $phc_id);
}

$stmt_list->execute();
$doctors_result = $stmt_list->get_result();
$total_doctors  = $doctors_result->num_rows;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/doctors.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR -->
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
            <li class="active"><a href="doctors.php"><i class="fas fa-user-md"></i><span>Doctors</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
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

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>Doctors Directory</h2>
                <p><?php echo $phc_name; ?> - Permanent & Visiting Medical Officers</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display"><i class="far fa-calendar-alt"></i><span><?php echo date("d M Y, h:i A"); ?></span></div>
            </div>
        </header>

        <div class="dashboard-container">
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display:flex; background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#10b981; padding:12px 16px; border-radius:6px; margin-bottom:20px;">
                    <i class="fas fa-check-circle" style="margin-right:8px;"></i><span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display:flex; background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#ef4444; padding:12px 16px; border-radius:6px; margin-bottom:20px;">
                    <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <div class="page-action-bar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:15px; flex-wrap:wrap;">
                <form action="doctors.php" method="GET" style="display:flex; gap:10px; flex:1; max-width:450px;">
                    <div class="search-box" style="flex:1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Doctor Name or Specialization..." style="width:100%; padding:10px 14px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; font-size:13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding:10px 18px; border:none; background:var(--accent-color, #0ea5e9); color:#fff; border-radius:6px; cursor:pointer;">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="doctors.php" style="padding:10px 14px; background:var(--bg-card, #1e293b); border:1px solid var(--border-color); color:var(--text-secondary); border-radius:6px; text-decoration:none; font-size:13px;">Clear</a>
                    <?php endif; ?>
                </form>

                <button type="button" class="btn-primary-action" 
                    onclick="document.getElementById('doctorModal').style.cssText = 'display:flex !important; opacity:1 !important; visibility:visible !important; position:fixed !important; top:0 !important; left:0 !important; width:100vw !important; height:100vh !important; background:rgba(0,0,0,0.8) !important; z-index:999999 !important; justify-content:center !important; align-items:center !important;'"
                    style="padding:10px 18px; border:none; background:var(--accent-color, #0ea5e9); color:#fff; border-radius:6px; font-weight:bold; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-user-plus"></i> Add New Doctor
                </button>
            </div>

            <div class="data-panel">
                <div class="panel-header" style="margin-bottom:15px;"><h3 class="section-title">Medical Officers & Doctors Directory (<?php echo $total_doctors; ?>)</h3></div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doc ID</th>
                            <th>Doctor Name</th>
                            <th>Specialization</th>
                            <th>Type</th>
                            <th>Visiting Schedule</th>
                            <th>Contact</th>
                            <th>Consultations</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($doctors_result && $doctors_result->num_rows > 0): ?>
                            <?php while ($row = $doctors_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#DOC-<?php echo str_pad($row['doctor_id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['doctor_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['specialization'] ?? 'General Medicine'); ?></td>
                                    <td><span style="font-size:12px; padding:3px 8px; border-radius:4px; background:rgba(14,165,233,0.15); color:#0ea5e9; font-weight:bold;"><?php echo htmlspecialchars($row['doctor_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['visiting_days'] ?: 'All Working Days'); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_no'] ?? '-'); ?></td>
                                    <td><strong><?php echo (int)$row['total_consultations']; ?> Patients</strong></td>
                                    <td>
                                        <span class="badge-status" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:bold; <?php echo ($row['status'] === 'Active') ? 'background:rgba(16,185,129,0.15); color:#10b981; border:1px solid #10b981;' : 'background:rgba(239,68,68,0.15); color:#ef4444; border:1px solid #ef4444;'; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--text-secondary); padding:30px;">No doctors registered yet. Click "Add New Doctor" to create one.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p></footer>
    </main>
</div>

<!-- ADD DOCTOR MODAL -->
<div class="modal-overlay" id="doctorModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:999999;">
    <div class="modal-card" style="background:var(--bg-card, #1e293b); border:1px solid var(--border-color, #334155); border-radius:10px; width:100%; max-width:480px; padding:25px; color:var(--text-primary, #fff); box-shadow:0 10px 30px rgba(0,0,0,0.6);">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">Register Medical Officer</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('doctorModal').style.cssText = 'display:none !important; opacity:0 !important; visibility:hidden !important;'" style="background:none; border:none; font-size:26px; color:var(--text-secondary); cursor:pointer;">&times;</button>
        </div>
        <form action="doctors.php" method="POST" class="modal-form">
            <input type="hidden" name="action" value="add_doctor">
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Doctor Name *</label>
                <input type="text" name="doctor_name" required placeholder="e.g. Dr. Rajesh Verma" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Specialization</label>
                    <input type="text" name="specialization" placeholder="e.g. General Physician / Pediatric" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Doctor Type *</label>
                    <select name="doctor_type" required style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                        <option value="Permanent">Permanent</option>
                        <option value="Visiting">Visiting</option>
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Visiting Schedule / Days</label>
                    <input type="text" name="visiting_days" placeholder="e.g. Mon, Wed, Fri" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
                <div class="form-group">
                    <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Contact Number</label>
                    <input type="tel" name="contact_no" placeholder="e.g. 9876543210" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; margin-bottom:6px; color:var(--text-secondary);">Status</label>
                <select name="status" style="width:100%; padding:10px; background:var(--bg-input, #334155); border:1px solid var(--border-color, #475569); border-radius:6px; color:#fff; box-sizing:border-box;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" style="width:100%; padding:12px; background:var(--accent-color, #0ea5e9); color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Save Doctor Profile</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
</body>
</html>
<?php $stmt_list->close(); ?>