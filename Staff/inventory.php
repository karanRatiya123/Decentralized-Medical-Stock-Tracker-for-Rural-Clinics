<?php
session_start();

$phc_name = $phc_name ?? '';
$taluka = $taluka ?? '';
$district = $district ?? '';
$staff_name = $staff_name ?? '';
$low_stock_count = $low_stock_count ?? '';
$near_expiry_count = $near_expiry_count ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- EMBEDDED INVENTORY STYLES (Guarantees CSS loads without path or cache issues) -->
    <style>
        .page-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .action-buttons-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box input {
            background-color: var(--bg-input, #334155);
            border: 1px solid var(--border-color, #475569);
            padding: 10px 15px;
            border-radius: 6px;
            color: var(--text-primary, #f8fafc);
            width: 320px;
            font-size: 13px;
            outline: none;
        }

        .btn-primary-action {
            background-color: var(--accent-color, #0ea5e9);
            color: var(--btn-text-on-accent, #ffffff);
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-primary-action:hover {
            background-color: var(--accent-hover, #0284c7);
        }

        .filter-chips-row {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .chip {
            background-color: var(--bg-card, #1e293b);
            border: 1px solid var(--border-color, #334155);
            color: var(--text-secondary, #94a3b8);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }

        .chip.active, .chip:hover {
            background-color: var(--accent-color, #0ea5e9);
            color: var(--btn-text-on-accent, #ffffff);
            border-color: var(--accent-color, #0ea5e9);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-low {
            background-color: rgba(239, 71, 111, 0.15);
            color: #ef476f;
            border: 1px solid rgba(239, 71, 111, 0.3);
        }

        .badge-normal {
            background-color: rgba(14, 165, 233, 0.15);
            color: var(--accent-color, #0ea5e9);
            border: 1px solid rgba(14, 165, 233, 0.3);
        }

        .btn-action.btn-restock {
            background-color: #ef476f;
            color: #ffffff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action.btn-view {
            background-color: var(--border-color, #334155);
            color: var(--text-primary, #f8fafc);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
    </style>
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
                <a href="inventory.php" class="active">
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
                <h2>PHC Medicine Inventory & Catalog</h2>
                <p><?php echo $phc_name; ?> - Real-time Stock Levels & Medicine Master</p>
            </div>
            <div class="header-right">
                <div class="search-bar-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Inventory...">
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

            <!-- ACTION BAR: Search & Restock -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="inventorySearchInput" placeholder="Search by Medicine Name or Category...">
                </div>
                <div class="action-buttons-group">
                    <a href="stock_in.php" class="btn-primary-action">
                        <i class="fas fa-box-open"></i> Receive Stock
                    </a>
                </div>
            </div>

            <!-- STATUS FILTER CHIPS -->
            <div class="filter-chips-row">
                <button type="button" class="chip active">All Inventory</button>
                <button type="button" class="chip">In Stock</button>
                <button type="button" class="chip status-low">Low Stock (<?php echo $low_stock_count; ?>)</button>
                <button type="button" class="chip status-expiry">Near Expiry (<?php echo $near_expiry_count; ?>)</button>
            </div>

            <!-- INVENTORY DATA TABLE -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Current Available Stock</h3>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Current Quantity</th>
                            <th>Pack Price (₹)</th>
                            <th>Tablet Price (₹)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic inventory rows will load here -->
                        <!--
                        <tr>
                            <td>M101</td>
                            <td><strong>Paracetamol 500mg</strong></td>
                            <td>Analgesic / Antipyretic</td>
                            <td><span class="stock-badge low">8 Tablets</span></td>
                            <td>₹45.00</td>
                            <td>₹4.50</td>
                            <td><span class="badge badge-low">Low Stock</span></td>
                            <td>
                                <a href="stock_in.php" class="btn-action btn-restock">Restock</a>
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

<!-- Scripts -->
<script src="../js/theme.js"></script>
</body>
</html>