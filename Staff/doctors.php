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
    <title>Doctors Management - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/doctors.css">
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
                <h2>Doctors Directory</h2>
                <p><?php echo $phc_name; ?> - Permanent & Visiting Medical Officers</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Doctor...">
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
            <div class="alert-banner success-banner" id="doctorSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Doctor record successfully added to directory!</span>
            </div>

            <!-- ACTION BAR: Search & Add Doctor Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="doctorSearchInput" placeholder="Search by Doctor Name or Specialization...">
                </div>
                <button class="btn-primary-action" id="openDoctorModalBtn">
                    <i class="fas fa-user-plus"></i> Add New Doctor
                </button>
            </div>

            <!-- DOCTORS DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">PHC Associated Doctors</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Doctor ID</th>
                            <th>Doctor Name</th>
                            <th>Specialization</th>
                            <th>Employment Type</th>
                            <th>Mobile Number</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic doctor rows will load here -->
                        <!--
                        <tr>
                            <td><strong>D1</strong></td>
                            <td>Dr. Ramesh Patel</td>
                            <td>General Physician</td>
                            <td><span class="badge badge-permanent">Permanent</span></td>
                            <td>9824012345</td>
                            <td><span class="status-dot active"></span> Active</td>
                            <td>
                                <button class="btn-action btn-view">Edit</button>
                            </td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <!-- FOOTER -->
        <footer class="main-footer">
            <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
            <p>Developed for Rural PHCs</p>
        </footer>

    </main>
</div>

<!-- ==============================
     ADD DOCTOR MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="doctorModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Add New Doctor</h3>
            <button class="modal-close-btn" id="closeDoctorModalBtn">&times;</button>
        </div>
        <form id="doctorForm" class="modal-form">
            <div class="form-group">
                <label for="doctor_name">Doctor Full Name</label>
                <input type="text" id="doctor_name" name="doctor_name" required placeholder="e.g. Dr. Suresh Kumar">
            </div>
            
            <div class="form-group">
                <label for="specialization">Specialization</label>
                <input type="text" id="specialization" name="specialization" required placeholder="e.g. General Physician / Pediatrician">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="employment_type">Employment Type</label>
                    <select id="employment_type" name="employment_type" required>
                        <option value="">Select Type</option>
                        <option value="Permanent">Permanent</option>
                        <option value="Visiting">Visiting</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="mobile_no">Mobile Number</label>
                    <input type="text" id="mobile_no" name="mobile_no" required placeholder="10-digit mobile">
                </div>
            </div>

            <button type="submit" class="btn-submit">Save Doctor Record</button>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="../js/theme.js"></script>
<script src="../js/doctors.js"></script>
</body>
</html>x    