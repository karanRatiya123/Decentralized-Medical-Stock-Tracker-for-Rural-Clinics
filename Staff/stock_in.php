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
    <title>Stock Received - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/stock_in.css">
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
                <h2>Stock Received (Stock In)</h2>
                <p><?php echo $phc_name; ?> - Log Incoming Shipments & Expiry Dates</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Shipments...">
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
            <div class="alert-banner success-banner" id="stockSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>New stock received successfully! Inventory quantity updated.</span>
            </div>

            <!-- ACTION BAR: Search & Receive Stock Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="stockSearchInput" placeholder="Search by Medicine Name or Shipment ID...">
                </div>
                <button class="btn-primary-action" id="openStockModalBtn">
                    <i class="fas fa-box-open"></i> Receive New Stock
                </button>
            </div>

            <!-- STOCK-IN HISTORY TABLE -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Incoming Shipment Logs</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Shipment ID</th>
                            <th>Medicine Name</th>
                            <th>Quantity Received</th>
                            <th>Batch Expiry Date</th>
                            <th>Received Date</th>
                            <th>Logged By Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic stock-in logs will load here -->
                        <!--
                        <tr>
                            <td><strong>SHP-5021</strong></td>
                            <td>Paracetamol 500mg</td>
                            <td><span class="qty-badge">+200 Tabs</span></td>
                            <td>15 Oct 2027</td>
                            <td>25 Aug 2026</td>
                            <td>Dr. Anjali Sharma</td>
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
     RECEIVE STOCK MODAL (Popup)
     ============================== -->
<div class="modal-overlay" id="stockModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Log Incoming Medicine Shipment</h3>
            <button class="modal-close-btn" id="closeStockModalBtn">&times;</button>
        </div>
        <form id="stockForm" class="modal-form">
            <div class="form-group">
                <label for="medicine_select">Select Medicine</label>
                <select id="medicine_select" name="medicine_id" required>
                    <option value="">-- Choose Medicine --</option>
                    <!-- Dynamic medicine options will load here -->
                    <!--
                    <option value="M101">Paracetamol 500mg</option>
                    -->
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="qty_received">Quantity Received</label>
                    <input type="number" id="qty_received" name="quantity" min="1" required placeholder="e.g. 100">
                </div>
                <div class="form-group">
                    <label for="expiry_date">Batch Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date" required>
                </div>
            </div>

            <div class="form-group">
                <label>Received Date</label>
                <input type="text" value="<?php echo date("d-m-Y"); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label>Logged By Staff</label>
                <input type="text" value="<?php echo $staff_name; ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
            </div>

            <button type="submit" class="btn-submit">Save Shipment & Update Inventory</button>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="../js/theme.js"></script>
<script src="../js/stock_in.js"></script>
</body>
</html>