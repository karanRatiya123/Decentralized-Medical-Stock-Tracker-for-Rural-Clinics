<?php
session_start();
require_once '../config/database.php';

// Session verification (Staff Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../login.php");
    exit();
}

$staff_id = (int)($_SESSION['staff_id'] ?? 0);
$success_msg = '';
$error_msg   = '';

// =========================================================================
// 1. HANDLE PROFILE & PASSWORD UPDATE (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $staff_name   = trim($_POST['staff_name'] ?? '');
    $contact_no   = trim($_POST['contact_no'] ?? '');
    $shift        = trim($_POST['shift'] ?? 'Morning');
    $new_password = trim($_POST['new_password'] ?? '');

    if (!empty($staff_name)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_up = $conn->prepare("UPDATE staff SET staff_name = ?, contact_no = ?, shift = ?, password = ? WHERE staff_id = ?");
            $stmt_up->bind_param("ssssi", $staff_name, $contact_no, $shift, $hashed_password, $staff_id);
        } else {
            $stmt_up = $conn->prepare("UPDATE staff SET staff_name = ?, contact_no = ?, shift = ? WHERE staff_id = ?");
            $stmt_up->bind_param("sssi", $staff_name, $contact_no, $shift, $staff_id);
        }

        if ($stmt_up->execute()) {
            $_SESSION['staff_name'] = $staff_name;
            $_SESSION['shift']      = $shift;
            $success_msg = "Your profile changes and settings have been saved successfully!";
        } else {
            $error_msg = "Error updating profile: " . $conn->error;
        }
        $stmt_up->close();
    } else {
        $error_msg = "Staff full name cannot be left blank.";
    }
}

// =========================================================================
// 2. FETCH LATEST PROFILE & PHC DATA
// =========================================================================
$stmt_info = $conn->prepare("
    SELECT s.*, p.phc_name, p.village, p.Taluka, p.district, p.address AS phc_address, p.contact_no AS phc_phone
    FROM staff s
    LEFT JOIN phcs p ON s.phc_id = p.phc_id
    WHERE s.staff_id = ?
");
$stmt_info->bind_param("i", $staff_id);
$stmt_info->execute();
$staff = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

$staff_name = htmlspecialchars($staff['staff_name'] ?? 'Staff Member');
$phc_name   = htmlspecialchars($staff['phc_name'] ?? 'PHC Center');
$username   = htmlspecialchars($staff['username'] ?? '');
$shift      = htmlspecialchars($staff['shift'] ?? 'Morning');
$contact_no = htmlspecialchars($staff['contact_no'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & Settings - <?php echo $phc_name; ?> - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        .profile-avatar-card {
            background: var(--bg-card, #1e293b);
            border: 1px solid var(--border-color, #334155);
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .avatar-circle {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0ea5e9, #6366f1);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin: 0 auto 15px;
            box-shadow: 0 8px 16px rgba(14, 165, 233, 0.3);
        }
        .info-list-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-color, #334155);
            font-size: 13px;
        }
        .info-list-item:last-child {
            border-bottom: none;
        }
        .info-list-item i {
            width: 25px;
            color: var(--accent-color, #0ea5e9);
            font-size: 14px;
        }
        .info-list-item .label {
            color: var(--text-secondary, #94a3b8);
            width: 100px;
        }
        .info-list-item .value {
            font-weight: 600;
            color: var(--text-primary, #f8fafc);
            flex: 1;
            text-align: right;
        }
        .form-section-title {
            font-size: 14px;
            font-weight: bold;
            color: var(--accent-color, #0ea5e9);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .input-with-icon {
            position: relative;
        }
        .input-with-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary, #94a3b8);
            font-size: 14px;
        }
        .input-with-icon input, .input-with-icon select {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: var(--bg-input, #0f172a);
            border: 1px solid var(--border-color, #334155);
            border-radius: 8px;
            color: var(--text-primary, #f8fafc);
            font-size: 13px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .input-with-icon input:focus, .input-with-icon select:focus {
            border-color: var(--accent-color, #0ea5e9);
            outline: none;
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- SIDEBAR NAVIGATION -->
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
            <li><a href="doctors.php"><i class="fas fa-user-md"></i><span>Doctors</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports</span></a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user-circle"></i><span>Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-card-small">
                <div class="user-avatar"><i class="fas fa-user"></i></div>
                <div class="user-details"><h4><?php echo $staff_name; ?></h4><p>Role: PHC Staff</p></div>
            </div>
            <a href="../login.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </nav>

    <!-- MAIN CONTENT AREA -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <h2>Staff Profile & Account Settings</h2>
                <p>Manage your account credentials and view assigned health facility details</p>
            </div>
            <div class="header-right">
                <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme"><i class="fas fa-sun"></i></button>
                <div class="date-time-display"><i class="far fa-calendar-alt"></i><span><?php echo date("d M Y, h:i A"); ?></span></div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- Alert Messages -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert-banner success-banner" style="display:flex; background:rgba(16,185,129,0.15); border:1px solid #10b981; color:#10b981; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert-banner error-banner" style="display:flex; background:rgba(239,68,68,0.15); border:1px solid #ef4444; color:#ef4444; padding:12px 18px; border-radius:8px; margin-bottom:20px; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle"></i><span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- LEFT COLUMN: Profile Card & Facility Details -->
                <div>
                    <div class="profile-avatar-card">
                        <div class="avatar-circle">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3 style="margin:0 0 5px 0; font-size:18px;"><?php echo $staff_name; ?></h3>
                        <p style="color:var(--accent-color, #0ea5e9); font-size:13px; font-weight:600; margin:0 0 10px 0;">PHC Staff / Dispensing Officer</p>
                        
                        <div style="display:inline-flex; gap:8px; justify-content:center; margin-bottom:20px;">
                            <span style="background:rgba(16,185,129,0.15); color:#10b981; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:bold; border:1px solid rgba(16,185,129,0.3);">
                                <i class="fas fa-circle" style="font-size:7px; vertical-align:middle; margin-right:3px;"></i> Active
                            </span>
                            <span style="background:rgba(14,165,233,0.15); color:#0ea5e9; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:bold; border:1px solid rgba(14,165,233,0.3);">
                                ID: #S<?php echo str_pad($staff_id, 3, '0', STR_PAD_LEFT); ?>
                            </span>
                        </div>

                        <!-- Facility Key/Value List -->
                        <div style="text-align:left; border-top:1px solid var(--border-color, #334155); padding-top:15px;">
                            <div class="info-list-item">
                                <i class="fas fa-hospital"></i>
                                <span class="label">Center</span>
                                <span class="value"><?php echo $phc_name; ?></span>
                            </div>
                            <div class="info-list-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="label">Taluka</span>
                                <span class="value"><?php echo htmlspecialchars($staff['Taluka'] ?? '-'); ?></span>
                            </div>
                            <div class="info-list-item">
                                <i class="fas fa-globe-asia"></i>
                                <span class="label">District</span>
                                <span class="value"><?php echo htmlspecialchars($staff['district'] ?? '-'); ?></span>
                            </div>
                            <div class="info-list-item">
                                <i class="fas fa-clock"></i>
                                <span class="label">Shift</span>
                                <span class="value"><?php echo $shift; ?></span>
                            </div>
                            <div class="info-list-item">
                                <i class="fas fa-calendar-check"></i>
                                <span class="label">Joined</span>
                                <span class="value"><?php echo date("d M Y", strtotime($staff['joining_date'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Editable Form -->
                <div class="data-panel" style="padding:28px; border-radius:12px;">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Personal Details Section -->
                        <div class="form-section-title">
                            <i class="fas fa-id-badge"></i> Personal & Contact Information
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary); font-weight:600;">Full Name *</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="staff_name" value="<?php echo htmlspecialchars($staff['staff_name']); ?>" required placeholder="Enter full name">
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary); font-weight:600;">Username (Login ID)</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-at"></i>
                                    <input type="text" value="<?php echo $username; ?>" disabled style="opacity:0.6; cursor:not-allowed;">
                                </div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:30px;">
                            <div>
                                <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary); font-weight:600;">Contact Phone Number</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" name="contact_no" value="<?php echo $contact_no; ?>" placeholder="e.g. 9876543210" pattern="[0-9]{10}">
                                </div>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary); font-weight:600;">Assigned Duty Shift</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-business-time"></i>
                                    <select name="shift">
                                        <option value="Morning" <?php echo ($shift === 'Morning') ? 'selected' : ''; ?>>Morning Shift (08:00 AM - 02:00 PM)</option>
                                        <option value="Evening" <?php echo ($shift === 'Evening') ? 'selected' : ''; ?>>Evening Shift (02:00 PM - 08:00 PM)</option>
                                        <option value="Night" <?php echo ($shift === 'Night') ? 'selected' : ''; ?>>Night Shift (08:00 PM - 08:00 AM)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Security & Password Section -->
                        <div class="form-section-title" style="border-top:1px solid var(--border-color, #334155); padding-top:20px;">
                            <i class="fas fa-lock"></i> Security & Authentication
                        </div>

                        <div style="margin-bottom:25px;">
                            <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary); font-weight:600;">New Password (Optional)</label>
                            <div class="input-with-icon">
                                <i class="fas fa-key"></i>
                                <input type="password" name="new_password" placeholder="Leave blank if you do not wish to change your password">
                            </div>
                            <small style="color:var(--text-secondary); font-size:11px; margin-top:4px; display:block;">Only fill this field if you want to set a new password for your login account.</small>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" class="btn-primary-action" style="padding:12px 28px; background:var(--accent-color, #0ea5e9); color:#fff; border:none; border-radius:8px; font-weight:bold; font-size:14px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(14, 165, 233, 0.3);">
                                <i class="fas fa-save"></i> Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <footer class="main-footer"><p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p></footer>
    </main>
</div>

<script src="../js/theme.js"></script>
</body>
</html>