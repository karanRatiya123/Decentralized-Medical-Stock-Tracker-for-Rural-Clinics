<?php
session_start();

$phc_name = $phc_name ?? '';
$taluka = $taluka ?? '';
$district = $district ?? '';
$staff_name = $staff_name ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/patients.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. FIXED SIDEBAR NAVIGATION (Matching Dashboard)
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
        
        <!-- TOP HEADER BAR (Matching Dashboard) -->
        <header class="top-header">
            <div class="header-left">
                <h2>Patients Management</h2>
                <p><?php echo $phc_name; ?> - Registered Patient Database</p>
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

            <!-- SUCCESS NOTIFICATION BANNER (Hidden by default, shown upon registration) -->
            <div class="alert-banner success-banner" id="successBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Patient successfully registered and added to database!</span>
            </div>

            <!-- ACTION BAR: Search & Register Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="patientSearchInput" placeholder="Search by Patient Name, ID, or Mobile No...">
                </div>
                <button class="btn-primary-action" id="openRegisterModalBtn">
                    <i class="fas fa-user-plus"></i> Register New Patient
                </button>
            </div>

            <!-- QUICK FILTER CHIPS -->
            <div class="filter-chips-row">
                <button class="chip active">All Patients</button>
                <button class="chip">Visited Today</button>
                <button class="chip">Male</button>
                <button class="chip">Female</button>
            </div>

            <!-- PATIENTS DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">All Registered Patients</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Age / Sex</th>
                            <th>Mobile Number</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic patient rows will load here -->
                        <!--
                        <tr>
                            <td><strong>P1258</strong></td>
                            <td><a href="#" class="patient-name-link" data-name="Ramesh Bhai" data-id="P1258">Ramesh Bhai</a></td>
                            <td>45 / M</td>
                            <td>9876543210</td>
                            <td>Veraval Rural</td>
                            <td>
                                <a href="medicine_issue.php?patient_id=P1258" class="btn-action btn-visit">New Visit</a>
                            </td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER (Matching Dashboard) -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
            <p>Developed for Rural PHCs</p>
        </footer>

    </main>
</div>

<!-- ==============================
     REGISTER PATIENT MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="registerModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Register New Patient</h3>
            <button class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="registerForm" class="modal-form">
            <div class="form-group">
                <label for="patient_name">Patient Name</label>
                <input type="text" id="patient_name" name="patient_name" required placeholder="Enter full name">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" required placeholder="Age">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="mobile_no">Mobile Number</label>
                <input type="text" id="mobile_no" name="mobile_no" required placeholder="10-digit mobile number">
            </div>
            <div class="form-group">
                <label for="address">Address / Village</label>
                <textarea id="address" name="address" rows="3" placeholder="Enter residential address"></textarea>
            </div>
            <button type="submit" class="btn-submit">Save Patient Record</button>
        </form>
    </div>
</div>

<!-- ==============================
     PATIENT HISTORY SLIDER DRAWER
     ============================== -->
<div class="drawer-overlay" id="historyDrawer">
    <div class="drawer-panel">
        <div class="drawer-header">
            <div>
                <h3 id="drawerPatientName">Patient History</h3>
                <p id="drawerPatientId" style="font-size: 11px; color: var(--text-secondary);">ID: -</p>
            </div>
            <button class="modal-close-btn" id="closeDrawerBtn">&times;</button>
        </div>
        <div class="drawer-body">
            <h4 style="font-size: 13px; margin-bottom: 10px; color: var(--text-accent);">Previous Visit Records</h4>
            
            <!-- Dynamic patient visit history will populate here -->
            <!--
            <div class="history-card">
                <div class="history-date"><i class="far fa-calendar"></i> 02 May 2026, 10:30 AM</div>
                <div class="history-meta">Doctor: Dr. Patel (Permanent) | Staff: Anjali Sharma</div>
                <div class="history-meds">
                    <span class="med-tag">Paracetamol 500mg (10 tabs)</span>
                    <span class="med-tag">Cetirizine (5 tabs)</span>
                </div>
            </div>
            -->
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../js/theme.js"></script>
<script src="../js/patients.js"></script>
</body>
</html>