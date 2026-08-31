<?php
session_start();
require_once '../config/database.php';

// Session verification (Only logged-in PHC Staff can access)
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

// Handle New Patient Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    $patient_name = trim($_POST['patient_name'] ?? '');
    $age          = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $gender       = trim($_POST['gender'] ?? '');
    $mobile_no    = trim($_POST['mobile_no'] ?? '');
    $address      = trim($_POST['address'] ?? '');

    if (!empty($patient_name) && !empty($gender)) {
        $stmt_insert = $conn->prepare("
            INSERT INTO patients (patient_name, age, gender, mobile_no, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("sisss", $patient_name, $age, $gender, $mobile_no, $address);

        if ($stmt_insert->execute()) {
            $new_patient_id = $stmt_insert->insert_id;
            $success_msg = "Patient registered successfully! Patient ID: P-" . str_pad($new_patient_id, 4, '0', STR_PAD_LEFT);
        } else {
            $error_msg = "Error registering patient: " . $conn->error;
        }
        $stmt_insert->close();
    } else {
        $error_msg = "Please fill in all required fields (Name & Gender).";
    }
}

// Fetch Patient List
$search_query = trim($_GET['search'] ?? '');

if (!empty($search_query)) {
    $search_param = "%" . $search_query . "%";
    $stmt_patients = $conn->prepare("
        SELECT p.*, 
               (SELECT MAX(visit_timestamp) FROM patient_visits WHERE patient_id = p.patient_id) AS last_visit
        FROM patients p
        WHERE p.patient_name LIKE ? OR p.mobile_no LIKE ? OR p.patient_id LIKE ?
        ORDER BY p.patient_id DESC
    ");
    $stmt_patients->bind_param("sss", $search_param, $search_param, $search_query);
} else {
    $stmt_patients = $conn->prepare("
        SELECT p.*, 
               (SELECT MAX(visit_timestamp) FROM patient_visits WHERE patient_id = p.patient_id) AS last_visit
        FROM patients p
        ORDER BY p.patient_id DESC
    ");
}

$stmt_patients->execute();
$patients_result = $stmt_patients->get_result();
$total_patients = $patients_result->num_rows;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/patients.css">
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
            <li class="active"><a href="patients.php"><i class="fas fa-users"></i><span>Patients</span></a></li>
            <li><a href="medicine_issue.php"><i class="fas fa-pills"></i><span>Medicine Issue</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-warehouse"></i><span>Inventory</span></a></li>
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
        
        <header class="top-header">
            <div class="header-left">
                <h2>Patients Registry</h2>
                <p><?php echo $phc_name; ?> - Manage Patient Profiles & Dispensing History</p>
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

            <!-- ACTION BAR -->
            <div class="page-action-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
                <form action="patients.php" method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 450px;">
                    <div class="search-box" style="flex: 1;">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by Name, Phone or ID..." style="width: 100%; padding: 10px 14px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; font-size: 13px;">
                    </div>
                    <button type="submit" class="btn-primary-action" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; cursor: pointer;">Search</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="patients.php" style="padding: 10px 14px; background: var(--bg-card, #1e293b); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 6px; text-decoration: none; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </form>

                <button type="button" class="btn-primary-action" id="openRegisterModalBtn" style="padding: 10px 18px; border: none; background: var(--accent-color, #0ea5e9); color: #fff; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-plus"></i> Register New Patient
                </button>
            </div>

            <!-- PATIENTS TABLE -->
            <div class="data-panel">
                <div class="panel-header" style="margin-bottom: 15px;">
                    <h3 class="section-title">Registered Patients Directory (<?php echo $total_patients; ?> Records)</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Age / Gender</th>
                            <th>Mobile Number</th>
                            <th>Address / Village</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($patients_result && $patients_result->num_rows > 0): ?>
                            <?php while ($p = $patients_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>P-<?php echo str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <a href="#" class="patient-name-link" data-name="<?php echo htmlspecialchars($p['patient_name']); ?>" data-id="<?php echo str_pad($p['patient_id'], 4, '0', STR_PAD_LEFT); ?>" style="color: var(--accent-color, #0ea5e9); text-decoration: none; font-weight: bold;">
                                            <?php echo htmlspecialchars($p['patient_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['age'] ?? '-'); ?> / <?php echo htmlspecialchars($p['gender'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['mobile_no'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($p['address'] ?? '-'); ?></td>
                                    <td>
                                        <?php if (!empty($p['last_visit'])): ?>
                                            <span><?php echo date("d M Y, h:i A", strtotime($p['last_visit'])); ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary); font-size: 12px;">No visits</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="medicine_issue.php?patient_id=<?php echo $p['patient_id']; ?>" class="btn-action" style="padding: 5px 10px; background: rgba(14,165,233,0.15); color: #0ea5e9; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold;">
                                            <i class="fas fa-pills"></i> Issue Meds
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                    <?php echo !empty($search_query) ? 'No patients found matching "' . htmlspecialchars($search_query) . '"' : 'No patients registered yet. Click "Register New Patient" to add one.'; ?>
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
     REGISTER PATIENT MODAL
     ============================== -->
<div class="modal-overlay" id="registerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000;">
    <div class="modal-card" style="background: var(--bg-card, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 10px; width: 100%; max-width: 480px; padding: 25px; color: var(--text-primary, #fff); box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Register New Patient</h3>
            <button type="button" class="modal-close-btn" id="closeModalBtn" style="background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">&times;</button>
        </div>
        
        <form action="patients.php" method="POST" id="registerForm" class="modal-form">
            <input type="hidden" name="action" value="add_patient">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Full Name *</label>
                <input type="text" name="patient_name" required placeholder="e.g. Ramesh Patel" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Age</label>
                    <input type="number" name="age" min="1" max="120" placeholder="e.g. 45" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Gender *</label>
                    <select name="gender" required style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Mobile Number</label>
                <input type="tel" name="mobile_no" placeholder="e.g. 9876543210" pattern="[0-9]{10}" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Address / Village</label>
                <textarea name="address" rows="2" placeholder="e.g. Somnath Road, Veraval" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;"></textarea>
            </div>

            <button type="submit" style="width: 100%; padding: 12px; background: var(--accent-color, #0ea5e9); color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
                Save Patient Profile
            </button>
        </form>
    </div>
</div>

<!-- ==============================
     PATIENT HISTORY DRAWER
     ============================== -->
<div class="history-drawer" id="historyDrawer" style="position: fixed; top: 0; right: -400px; width: 380px; height: 100vh; background: var(--bg-card, #1e293b); border-left: 1px solid var(--border-color, #334155); box-shadow: -5px 0 25px rgba(0,0,0,0.5); z-index: 1001; transition: right 0.3s ease; padding: 20px; box-sizing: border-box; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color, #334155); padding-bottom: 15px; margin-bottom: 15px;">
        <div>
            <h3 id="drawerPatientName" style="margin: 0; font-size: 16px;">Patient History</h3>
            <span id="drawerPatientId" style="font-size: 12px; color: var(--text-secondary);">ID: -</span>
        </div>
        <button type="button" id="closeDrawerBtn" style="background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer;">&times;</button>
    </div>
    
    <div id="drawerContent">
        <p style="color: var(--text-secondary); font-size: 13px;">Click "Issue Meds" to dispense medications for this patient.</p>
    </div>
</div>

<!-- External JavaScript Files -->
<script src="../js/theme.js"></script>
<script src="../js/patients.js"></script>

</body>
</html>
<?php
$stmt_patients->close();
?>