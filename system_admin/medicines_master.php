<?php
session_start();

$system_admin_name = $system_admin_name ?? '';
$pending_phc_requests = $pending_phc_requests ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Medicines Catalog - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_medicines_master.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="app-layout">
    <!-- ==============================
         1. SYSTEM ADMIN SIDEBAR
         ============================== -->
    <nav class="sidebar">
        <div class="brand-header">
            <i class="fas fa-server brand-icon" style="color: #8b5cf6;"></i>
            <div class="brand-text">
                <h3>MedStock</h3>
                <p>System Master Admin</p>
            </div>
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-th-large"></i><span>System Dashboard</span></a></li>
            <li><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts & Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li class="active"><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i><span>Admin Profile</span></a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-details">
                    <h4><?php echo $system_admin_name; ?></h4>
                    <p>Access Level: Root</p>
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
                <h2>Master Medicines Catalog</h2>
                <p>State-wide standardized medication registry and benchmark pricing</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date("d M Y, h:i A"); ?></span>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT CONTAINER -->
        <div class="dashboard-container">

            <!-- SUCCESS NOTIFICATION BANNER -->
            <div class="alert-banner success-banner" id="medSuccessBanner" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span id="medBannerText">Master medicine catalog updated successfully!</span>
            </div>

            <!-- ACTION BAR: Search & Add Medicine Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="medSearchInput" placeholder="Search Medicine Name or Category...">
                </div>
                <button type="button" class="btn-primary-action" id="openMedModalBtn">
                    <i class="fas fa-plus-circle"></i> Add New Master Medicine
                </button>
            </div>

            <!-- MASTER MEDICINES TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Standardized Medication Registry</h3>
                </div>
                <table class="data-table" id="masterMedTable">
                    <thead>
                        <tr>
                            <th>Med ID</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Dosage Form</th>
                            <th>Pack Price (₹)</th>
                            <th>Unit/Tablet Price (₹)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic master medicine rows will load here -->
                        <!--
                        <tr>
                            <td><strong>MED-01</strong></td>
                            <td>Paracetamol 500mg</td>
                            <td>Analgesic / Antipyretic</td>
                            <td>Tablet</td>
                            <td>₹45.00</td>
                            <td>₹4.50</td>
                            <td><span class="badge-status status-active">Authorized</span></td>
                            <td><button type="button" class="btn-action btn-edit">Edit</button></td>
                        </tr>
                        -->
                    </tbody>
                </table>
            </div>

        </div> <!-- End Dashboard Container -->
        
        <footer class="main-footer" style="display:block; padding: 20px 0; text-align: center; color: var(--text-secondary); font-size: 12px;">
            <p>&copy; 2026 MedStock Tracker. System Administration Portal.</p>
        </footer>

    </main>
</div>

<!-- ==============================
     ADD / EDIT MASTER MEDICINE MODAL
     ============================== -->
<div class="modal-overlay" id="medModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Add Authorized Master Medicine</h3>
            <button type="button" class="modal-close-btn" id="closeMedModalBtn">&times;</button>
        </div>
        <form id="medForm" class="modal-form">
            <div class="form-group">
                <label for="med_name">Medicine Name & Strength</label>
                <input type="text" id="med_name" required placeholder="e.g. Ibuprofen 400mg">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="med_category">Category (Type freely)</label>
                    <input type="text" id="med_category" required placeholder="e.g. Analgesic / Antibiotic">
                </div>
                <div class="form-group">
                    <label for="med_form">Dosage Form</label>
                    <select id="med_form" required>
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup / Liquid</option>
                        <option value="Injection">Injection</option>
                        <option value="Sachet">Sachet / Packet</option>
                    </select>
                </div>
            </div>

            <!-- PRICING FIELDS ADDED -->
            <div class="form-row">
                <div class="form-group">
                    <label for="pack_price">Standard Pack Price (₹)</label>
                    <input type="number" step="0.01" id="pack_price" required placeholder="e.g. 45.00">
                </div>
                <div class="form-group">
                    <label for="tablet_price">Price per Unit / Tablet (₹)</label>
                    <input type="number" step="0.01" id="tablet_price" required placeholder="e.g. 4.50">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="min_threshold">Low Stock Warning Threshold</label>
                    <input type="number" id="min_threshold" required placeholder="e.g. 20">
                </div>
                <div class="form-group">
                    <label for="med_status">Catalog Status</label>
                    <select id="med_status" required>
                        <option value="Authorized">Authorized</option>
                        <option value="Discontinued">Discontinued</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-submit">Save Master Medicine</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>
<script src="../js/system_medicines_master.js"></script>
</body>
</html>