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
    <title>Reports & Analytics - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/reports.css">
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
                <h2>Reports & Analytics</h2>
                <p><?php echo $phc_name; ?> - Select Duration & Download PDF Reports</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Reports...">
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
            <div class="alert-banner success-banner" id="pdfSuccessBanner" style="display: none;">
                <i class="fas fa-file-pdf"></i>
                <span id="successBannerText">PDF report generated successfully!</span>
            </div>

            <div class="reports-grid">
                
                <!-- Report Card 1: Daily Patient Visits -->
                <div class="report-card">
                    <div class="report-icon-wrapper">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div class="report-info">
                        <h3>Daily Patient Visits Report</h3>
                        <p>Summary of all patient visits attended at the PHC.</p>
                        
                        <!-- Duration Filter Selection -->
                        <div class="report-filter-group">
                            <label><i class="far fa-clock"></i> Select Time Duration:</label>
                            <select class="duration-select" id="duration_visits">
                                <option value="Today">Today</option>
                                <option value="This Week">This Week</option>
                                <option value="This Month" selected>This Month</option>
                                <option value="Custom Range">Custom Date Range</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-generate-pdf" onclick="triggerPdfDownload('Daily_Patient_Visits_Report.pdf', 'duration_visits')">
                        <i class="fas fa-download"></i> Generate & Download PDF
                    </button>
                </div>

                <!-- Report Card 2: Medicine Consumption -->
                <div class="report-card">
                    <div class="report-icon-wrapper">
                        <i class="fas fa-prescription-bottle-alt"></i>
                    </div>
                    <div class="report-info">
                        <h3>Medicine Consumption Report</h3>
                        <p>Detailed log of all medicines dispensed from inventory.</p>
                        
                        <!-- Duration Filter Selection -->
                        <div class="report-filter-group">
                            <label><i class="far fa-clock"></i> Select Time Duration:</label>
                            <select class="duration-select" id="duration_meds">
                                <option value="Today">Today</option>
                                <option value="This Week" selected>This Week</option>
                                <option value="This Month">This Month</option>
                                <option value="Last 3 Months">Last 3 Months</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-generate-pdf" onclick="triggerPdfDownload('Medicine_Consumption_Report.pdf', 'duration_meds')">
                        <i class="fas fa-download"></i> Generate & Download PDF
                    </button>
                </div>

                <!-- Report Card 3: Stock-In & Inventory Status -->
                <div class="report-card">
                    <div class="report-icon-wrapper">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="report-info">
                        <h3>Current Inventory & Stock-In Log</h3>
                        <p>Status of available quantities and incoming stock shipments.</p>
                        
                        <!-- Duration Filter Selection -->
                        <div class="report-filter-group">
                            <label><i class="far fa-clock"></i> Select Time Duration:</label>
                            <select class="duration-select" id="duration_stock">
                                <option value="Current Live Stock" selected>Current Live Status</option>
                                <option value="Last 30 Days Stock-In">Last 30 Days Stock-In</option>
                                <option value="Yearly Stock Log">Yearly Stock Log</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-generate-pdf" onclick="triggerPdfDownload('Inventory_Stock_Report.pdf', 'duration_stock')">
                        <i class="fas fa-download"></i> Generate & Download PDF
                    </button>
                </div>

                <!-- Report Card 4: Doctor-wise Activity -->
                <div class="report-card">
                    <div class="report-icon-wrapper">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="report-info">
                        <h3>Doctor Activity & Consultation Report</h3>
                        <p>Breakdown of patient consultations by permanent & visiting doctors.</p>
                        
                        <!-- Duration Filter Selection -->
                        <div class="report-filter-group">
                            <label><i class="far fa-clock"></i> Select Time Duration:</label>
                            <select class="duration-select" id="duration_doctors">
                                <option value="This Week">This Week</option>
                                <option value="This Month" selected>This Month</option>
                                <option value="All Time">All Time</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-generate-pdf" onclick="triggerPdfDownload('Doctor_Activity_Report.pdf', 'duration_doctors')">
                        <i class="fas fa-download"></i> Generate & Download PDF
                    </button>
                </div>

            </div>

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
<script src="../js/reports.js"></script>
</body>
</html>