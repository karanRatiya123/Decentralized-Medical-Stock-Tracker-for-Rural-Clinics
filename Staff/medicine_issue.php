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
$staff_name =htmlspecialchars($_SESSION['staff_name'] ?? 'Staff Member');
$phc_name   =htmlspecialchars($_SESSION['phc_name'] ?? 'PHC Name');
$taluka     =($_SESSION['taluka'] ?? '');
$district   =($_SESSION['district'] ?? '');
$preselected_patient_id = (int)($_GET['patient_id'] ?? 0);
$success_msg = '';
$error_msg   = '';
// =========================================================================
// 1. HANDLE MEDICINE DISPENSING / ISSUE FORM SUBMISSION
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_medicines') {
    $patient_id  = (int)($_POST['patient_id'] ?? 0);
    $doctor_id   = (int)($_POST['doctor_id'] ?? 0);
    $medicines   = $_POST['medicine_id'] ?? [];
    $quantities  = $_POST['quantity'] ?? [];
    if ($patient_id > 0 && $doctor_id > 0 && !empty($medicines)) {
        // Start database transaction
        $conn->begin_transaction();
        try {
            // Step 1: Insert into patient_visits
            $stmt_visit = $conn->prepare("
                INSERT INTO patient_visits (patient_id, doctor_id, phc_id, staff_id, visit_timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt_visit->bind_param("iiii", $patient_id, $doctor_id, $phc_id, $staff_id);
            $stmt_visit->execute();
            $visit_id = $stmt_visit->insert_id;
            $stmt_visit->close();
            // Prepare statements for checking stock, inserting issue item, and updating inventory
            $stmt_check_stock = $conn->prepare("SELECT quantity FROM inventory WHERE phc_id = ? AND medicine_id = ?");
            $stmt_issue_item  = $conn->prepare("INSERT INTO visit_medicines (visit_id, medicine_id, quantity_issued) VALUES (?, ?, ?)");
            $stmt_deduct_inv  = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE phc_id = ? AND medicine_id = ?");
            for ($i = 0; $i < count($medicines); $i++) {
                $med_id = (int)$medicines[$i];
                $qty    = (int)($quantities[$i] ?? 0);
                if ($med_id > 0 && $qty > 0) {
                    // Check available stock
                    $stmt_check_stock->bind_param("ii", $phc_id, $med_id);
                    $stmt_check_stock->execute();
                    $stock_row = $stmt_check_stock->get_result()->fetch_assoc();
                    $available = (int)($stock_row['quantity'] ?? 0);
                    if ($available < $qty) {
                        throw new Exception("Insufficient stock for one of the selected medicines. Available: {$available}, Requested: {$qty}.");
                    }
                    // Record item in visit_medicines
                    $stmt_issue_item->bind_param("iii", $visit_id, $med_id, $qty);
                    $stmt_issue_item->execute();
                    // Deduct stock from inventory
                    $stmt_deduct_inv->bind_param("iii", $qty, $phc_id, $med_id);
                    $stmt_deduct_inv->execute();
                }
            }
            $stmt_check_stock->close();
            $stmt_issue_item->close();
            $stmt_deduct_inv->close();
            // Commit all changes
            $conn->commit();
            $success_msg = "Medicines dispensed successfully! Visit ID: #" . $visit_id;
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    } else {
        $error_msg = "Please select a patient, doctor, and at least one medicine with a valid quantity.";
    }

}

// =========================================================================
// 2. FETCH DATA FOR FOR FILLING THE INFO
// =========================================================================
$patients_query = $conn->query("SELECT patient_id, patient_name, age, gender, mobile_no FROM patients ORDER BY patient_name ASC");
// Fetch Active Doctors for this PHC
$stmt_docs = $conn->prepare("SELECT doctor_id, doctor_name, specialization FROM doctors WHERE phc_id = ? AND status = 'Active' ORDER BY doctor_name ASC");
$stmt_docs->bind_param("i", $phc_id);
$stmt_docs->execute();
$doctors_query = $stmt_docs->get_result();
// Fetch Available In-Stock Medicines for this PHC
$stmt_meds = $conn->prepare("
    SELECT m.medicine_id, m.medicine_name, m.category, i.quantity 
    FROM inventory i 
    JOIN medicines m ON i.medicine_id = m.medicine_id 
    WHERE i.phc_id = ? AND i.quantity > 0 
    ORDER BY m.medicine_name ASC
");
$stmt_meds->bind_param("i", $phc_id);
$stmt_meds->execute();
$available_medicines = $stmt_meds->get_result();
// =========================================================================
// 3. FETCH RECENT MEDICINE DISPENSING LOGS
// =========================================================================
$stmt_recent = $conn->prepare("
    SELECT pv.visit_id, pv.visit_timestamp, pt.patient_name, d.doctor_name,
           GROUP_CONCAT(CONCAT(m.medicine_name, ' (', vm.quantity_issued, ')') SEPARATOR ', ') AS issued_items
    FROM patient_visits pv
    JOIN patients pt ON pv.patient_id = pt.patient_id
    LEFT JOIN doctors d ON pv.doctor_id = d.doctor_id
    JOIN visit_medicines vm ON pv.visit_id = vm.visit_id
    JOIN medicines m ON vm.medicine_id = m.medicine_id
    WHERE pv.phc_id = ?
    GROUP BY pv.visit_id
    ORDER BY pv.visit_timestamp DESC
    LIMIT 10
");
$stmt_recent->bind_param("i", $phc_id);
$stmt_recent->execute();
$recent_issues = $stmt_recent->get_result();


?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Issue - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/medicine_issue.css">
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
            <li class="active"><a href="medicine_issue.php"><i class="fas fa-pills"></i><span>Medicine Issue</span></a></li>
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
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>Medicine Issue & Dispensing</h2>
                <p><?php echo $phc_name; ?> - Record Patient Prescriptions and Automatically Update Stock</p>
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
            <!-- DISPENSING FORM PANEL -->
            <div class="data-panel" style="margin-bottom: 25px;">
                <div class="panel-header">
                    <h3 class="section-title"><i class="fas fa-prescription-bottle-alt"></i> Issue Prescription Medicines</h3>
                </div>
                <form action="medicine_issue.php" method="POST" id="issueForm" style="padding: 10px 0;">
                    <input type="hidden" name="action" value="issue_medicines">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <!-- Patient Selection -->
                        <div class="form-group">
                            <label for="patient_id" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: var(--text-primary);">
                                Select Patient *
                            </label>
                            <select name="patient_id" id="patient_id" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #475569); border-radius: 6px; box-sizing: border-box;">
                                <option value="">-- Choose Patient --</option>
                                <?php if ($patients_query && $patients_query->num_rows > 0): ?>
                                    <?php while ($pat = $patients_query->fetch_assoc()): ?>
                                        <option value="<?php echo $pat['patient_id']; ?>" <?php echo ($preselected_patient_id === (int)$pat['patient_id']) ? 'selected' : ''; ?>>
                                            P-<?php echo str_pad($pat['patient_id'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($pat['patient_name']); ?> (<?php echo htmlspecialchars($pat['mobile_no'] ?? 'No Phone'); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <!-- Prescribing Doctor Selection -->
                        <div class="form-group">
                            <label for="doctor_id" style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 6px; color: var(--text-primary);">
                                Prescribing Doctor *
                            </label>
                            <select name="doctor_id" id="doctor_id" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #475569); border-radius: 6px; box-sizing: border-box;">
                                <option value="">-- Choose Doctor --</option>
                                <?php if ($doctors_query && $doctors_query->num_rows > 0): ?>
                                    <?php while ($doc = $doctors_query->fetch_assoc()): ?>
                                        <option value="<?php echo $doc['doctor_id']; ?>">
                                            <?php echo htmlspecialchars($doc['doctor_name']); ?> (<?php echo htmlspecialchars($doc['specialization'] ?? 'General Physician'); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>No active doctors registered for this PHC</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <!-- Medicines Selection Rows -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 13px; font-weight: bold; margin-bottom: 8px; color: var(--text-primary);">
                            Prescribed Medications
                        </label>
                        <div id="medicinesContainer">
                            <div class="medicine-row" style="display: flex; gap: 15px; align-items: center; margin-bottom: 12px;">
                                <div style="flex: 3;">
                                    <select name="medicine_id[]" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color, #475569); border-radius: 6px; box-sizing: border-box;">
                                        <option value="">-- Select Available Medicine --</option>
                                        <?php if ($available_medicines && $available_medicines->num_rows > 0): ?>
                                            <?php foreach ($available_medicines as $med): ?>
                                                <option value="<?php echo $med['medicine_id']; ?>">
                                                    <?php echo htmlspecialchars($med['medicine_name']); ?> [In Stock: <?php echo $med['quantity']; ?> Units]
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No medicines currently in stock</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <input type="number" name="quantity[]" min="1" required placeholder="Quantity" style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: #fff; box-sizing: border-box;">
                                </div>
                                <div>
                                    <button type="button" class="btn-remove-row" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; padding: 10px 14px; border-radius: 6px; cursor: pointer;" onclick="removeMedicineRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="addMedicineRowBtn" style="background: var(--bg-card, #1e293b); border: 1px dashed var(--border-color, #475569); color: var(--accent-color, #0ea5e9); padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-top: 5px;">
                            <i class="fas fa-plus"></i> Add Another Medicine
                        </button>
                    </div>
                    <!-- Dispensing Staff & Date Preview -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Recording Staff</label>
                            <input type="text" value="<?php echo $staff_name; ?> (Staff ID: #<?php echo $staff_id; ?>)" disabled style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-secondary); opacity: 0.8; box-sizing: border-box;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 12px; margin-bottom: 6px; color: var(--text-secondary);">Issue Date & Time</label>
                            <input type="text" value="<?php echo date("d M Y, h:i A"); ?>" disabled style="width: 100%; padding: 10px; background: var(--bg-input, #334155); border: 1px solid var(--border-color, #475569); border-radius: 6px; color: var(--text-secondary); opacity: 0.8; box-sizing: border-box;">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" style="padding: 12px 24px; background: var(--accent-color, #0ea5e9); color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle"></i> Confirm Dispensing & Deduct Inventory
                    </button>
                </form>
            </div>
            <!-- RECENT DISPENSING HISTORY LOG -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Recent Patient Dispensing Logs</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Visit ID</th>
                            <th>Patient Name</th>
                            <th>Doctor</th>
                            <th>Medicines Issued (Qty)</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_issues && $recent_issues->num_rows > 0): ?>
                            <?php while ($row = $recent_issues->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['visit_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'General OPD'); ?></td>
                                    <td><span style="color: #10b981; font-weight: bold;"><?php echo htmlspecialchars($row['issued_items']); ?></span></td>
                                    <td><?php echo date("d M Y, h:i A", strtotime($row['visit_timestamp'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 25px;">No medicine issue records found for this PHC today.</td>
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
<!-- JavaScript for dynamic row addition & theme toggle -->
<script src="../js/theme.js"></script>
<script>
    // Add dynamic medicine row
    document.getElementById('addMedicineRowBtn').addEventListener('click', function() {
        const container = document.getElementById('medicinesContainer');
        const firstRow = container.querySelector('.medicine-row');
        const newRow = firstRow.cloneNode(true);
        
        // Reset inputs in new row
        newRow.querySelector('select').selectedIndex = 0;
        newRow.querySelector('input').value = '';
        container.appendChild(newRow);
    });
    // Remove medicine row
    function removeMedicineRow(btn) {
        const container = document.getElementById('medicinesContainer');
        const rows = container.querySelectorAll('.medicine-row');
        if (rows.length > 1) {
            btn.closest('.medicine-row').remove();
        } else {
            alert("At least one medicine must be selected.");
        }
    }
</script>
</body>
</html>
<?php
$stmt_docs->close();
$stmt_meds->close();
$stmt_recent->close();
?>