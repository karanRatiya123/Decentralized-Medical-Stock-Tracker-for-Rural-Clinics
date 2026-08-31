<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {

        // ========================================================
        // 1. CHECK STAFF TABLE
        // ========================================================
        $stmt_staff = $conn->prepare("
            SELECT s.*, p.phc_name, p.Taluka, p.district 
            FROM staff s 
            LEFT JOIN phcs p ON s.phc_id = p.phc_id 
            WHERE s.username = ?
        ");
        $stmt_staff->bind_param("s", $username);
        $stmt_staff->execute();
        $result_staff = $stmt_staff->get_result();
        $user_staff = $result_staff->fetch_assoc();

        if ($user_staff && (password_verify($password, $user_staff['password']) || $password === $user_staff['password'])) {
            $_SESSION['user_id']    = $user_staff['staff_id'];
            $_SESSION['staff_id']   = $user_staff['staff_id'];
            $_SESSION['username']   = $user_staff['username'];
            $_SESSION['staff_name'] = $user_staff['staff_name'];
            $_SESSION['role']       = 'staff';
            $_SESSION['phc_id']     = $user_staff['phc_id'];
            $_SESSION['phc_name']   = $user_staff['phc_name'] ?? '';
            $_SESSION['taluka']     = $user_staff['Taluka'] ?? '';
            $_SESSION['district']   = $user_staff['district'] ?? '';
            $_SESSION['shift']      = $user_staff['shift'];

            header("Location: Staff/dashboard.php");
            exit();
        }

        // ========================================================
        // 2. CHECK DISTRICT ADMIN TABLE
        // ========================================================
        $stmt_dist = $conn->prepare("SELECT * FROM district_admin WHERE username = ?");
        $stmt_dist->bind_param("s", $username);
        $stmt_dist->execute();
        $result_dist = $stmt_dist->get_result();
        $user_dist = $result_dist->fetch_assoc();

        if ($user_dist && (password_verify($password, $user_dist['password']) || $password === $user_dist['password'])) {
            $_SESSION['user_id']           = $user_dist['district_admin_id'];
            $_SESSION['district_admin_id'] = $user_dist['district_admin_id'];
            $_SESSION['admin_name']        = $user_dist['admin_name'];
            $_SESSION['username']          = $user_dist['username'];
            $_SESSION['role']              = 'district_admin';
            $_SESSION['district']          = $user_dist['district'];
            $_SESSION['email']             = $user_dist['email'] ?? '';
            $_SESSION['contact_no']        = $user_dist['contact_no'] ?? '';

            header("Location: district_admin/dashboard.php");
            exit();
        }

        // ========================================================
        // 3. CHECK SYSTEM ADMIN (MASTER ROOT)
        // ========================================================
        if ($username === 'root_admin' && $password === 'admin123') {
            $_SESSION['user_id']           = 1;
            $_SESSION['system_admin_name'] = 'Super Admin (State IT Cell)';
            $_SESSION['username']          = 'root_admin';
            $_SESSION['role']              = 'system_admin';

            header("Location: system_admin/dashboard.php");
            exit();
        }

        // If credentials did not match any of the 3 roles
        $error = "Invalid username or password.";

    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MedStock Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body">

<!-- TOP BAR WITH THEME TOGGLE BUTTON -->
<div class="login-top-bar">
    <button class="theme-toggle-btn" id="themeToggleBtn" title="Switch Theme">
        <i class="fas fa-sun"></i>
    </button>
</div>

<div class="login-container">
    <!-- BRANDING HEADER -->
    <div class="login-brand-header">
        <div class="brand-icon-large">
            <i class="fas fa-clinic-medical"></i>
        </div>
        <h2>MedStock Tracker</h2>
        <p>Rural Primary Health Center Management System</p>
    </div>

    <!-- LOGIN CARD -->
    <div class="login-card">
        <div class="login-card-header">
            <h3>Portal Login</h3>
            <p>Please sign in with your portal credentials</p>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 15px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="login-form">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required placeholder="Enter your username">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-login-submit">
                <i class="fas fa-sign-in-alt"></i> Sign In to Dashboard
            </button>
        </form>
    </div>

    <!-- LOGIN FOOTER -->
    <div class="login-footer">
        <p>&copy; 2026 MedStock Tracker. All Rights Reserved.</p>
        <p>Developed for Rural Healthcare Distribution</p>
    </div>
</div>

<!-- Scripts -->
<script src="js/theme.js"></script>
</body>
</html>