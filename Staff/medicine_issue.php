<?php
session_start();

$phc_name = $phc_name ?? '';
$taluka = $taluka ?? '';
$district = $district ?? '';
$staff_name = $staff_name ?? '';
$staff_id = $staff_id ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Issue & Visits - <?php echo $phc_name; ?> - MedStock Tracker</title>
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
    <li>
        <a href="dashboard.php">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
    </li>
    <li>
        <a href="patients.php">
            <i class="fas fa-users"></i>
            <span>Patients</span>
        </a>
    </li>
    <li>
        <a href="medicine_issue.php">
            <i class="fas fa-pills"></i>
            <span>Medicine Issue</span>
        </a>
    </li>
    <li>
        <a href="inventory.php">
            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>
        </a>
    </li>
    <li>
        <a href="stock_in.php">
            <i class="fas fa-arrow-down"></i>
            <span>Stock Received</span>
        </a>
    </li>
    <li>
        <a href="medicine_requests.php">
            <i class="fas fa-file-invoice"></i>
            <span>Medicine Requests</span>
        </a>
    </li>
    <li>
        <a href="doctors.php">
            <i class="fas fa-user-md"></i>
            <span>Doctors</span>
        </a>
    </li>
    <li>
        <a href="reports.php">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
    </li>
    <li>
        <a href="profile.php">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </li>
</ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $staff_name; ?></h4>
                    <p>Role: PHC Staff</p>
                </div>
            </div>
            <a href="../login.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- ==============================
         2. MAIN CONTENT AREA
         ============================== -->
    <main class="main-content">
        
        <!-- TOP HEADER BAR -->
        <header class="top-header">
            <div class="header-left">
                <h2>Medicine Dispensing & Visit Counter</h2>
                <p><?php echo $phc_name; ?> - Record Patient Visit & Issue Stock</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Patient...">
                </div>
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme">
                    <i class="fas fa-sun"></i>
                </button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="dashboard-container">

            <!-- SUCCESS NOTIFICATION BANNER -->
            <div class="alert-banner success-banner" id="dispenseSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Visit recorded successfully! Inventory updated automatically.</span>
            </div>

            <!-- TWO-COLUMN CLINICAL DISPENSING CONTAINER -->
            <form id="dispenseForm">
                <div class="dispense-grid">
                    
                    <!-- LEFT COLUMN: Patient & Doctor Selection -->
                    <div class="dispense-panel">
                        <h3 class="section-title"><i class="fas fa-user-check"></i> 1. Visit Details</h3>
                        
                        <div class="form-group">
                            <label for="select_patient">Select Patient</label>
                            <select id="select_patient" name="patient_id" required>
                                <option value="">-- Search or Select Patient --</option>
                                <!-- Dynamic patient options will load here -->
                                <!--
                                <option value="P1258">P1258 - Ramesh Bhai (45 / M)</option>
                                -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="select_doctor">Attending Doctor</label>
                            <select id="select_doctor" name="doctor_id" required>
                                <option value="">-- Select Doctor --</option>
                                <!-- Dynamic doctor options will load here -->
                                <!--
                                <option value="D1">Dr. Patel (Permanent - General Physician)</option>
                                -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Recording Staff Member</label>
                            <input type="text" value="<?php echo $staff_name . ($staff_id ? " (Staff ID: {$staff_id})" : ''); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                        </div>

                        <div class="form-group">
                            <label>Visit Timestamp</label>
                            <input type="text" value="<?php echo date("d-m-Y h:i A"); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Prescribed Medicines (Dynamic Cart) -->
                    <div class="dispense-panel">
                        <div class="panel-header" style="margin-bottom: 15px;">
                            <h3 class="section-title"><i class="fas fa-pills"></i> 2. Prescribed Medicines</h3>
                            <button type="button" class="btn-action btn-view" id="addMedicineRowBtn">+ Add Medicine</button>
                        </div>

                        <div id="medicineRowsContainer">
                            <!-- Initial Medicine Row -->
                            <div class="medicine-row-card">
                                <div class="form-group" style="flex: 2; margin-bottom: 0;">
                                    <label>Medicine Name</label>
                                    <select name="medicine_id[]" required class="med-select">
                                        <option value="">Select Medicine</option>
                                        <!-- Dynamic medicine options will load here -->
                                        <!--
                                        <option value="M1" data-stock="450">Paracetamol 500mg (Stock: 450)</option>
                                        -->
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                    <label>Qty Issued</label>
                                    <input type="number" name="quantity_issued[]" min="1" value="1" required class="qty-input">
                                </div>
                                <button type="button" class="btn-remove-row" title="Remove Medicine">&times;</button>
                            </div>
                        </div>

                        <div class="stock-warning-note">
                            <i class="fas fa-info-circle"></i> Inventory quantities will automatically deduct upon completion.
                        </div>
                    </div>

                </div>

                <!-- SUBMIT ACTION BAR -->
                <div class="dispense-submit-bar">
                    <button type="submit" class="btn-primary-action btn-complete-visit">
                        <i class="fas fa-check-circle"></i> Complete Visit & Issue Medicines
                    </button>
                </div>
            </form>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
            <p>Developed for Rural PHCs</p>
        </footer>

    </main>
</div>

<!-- Scripts -->
<script src="../js/theme.js"></script>
<script src="../js/medicine_issue.js"></script>
</body>
</html>