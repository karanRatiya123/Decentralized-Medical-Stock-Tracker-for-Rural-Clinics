<?php
session_start();
require_once '../config/database.php';

// ── ROUTE GUARD (System Admin Only) ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$system_admin_name = htmlspecialchars($_SESSION['system_admin_name'] ?? $_SESSION['username'] ?? 'System Master Admin');

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── HANDLE AJAX / POST ACTIONS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $edit_id     = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $district    = trim($_POST['district_name'] ?? '');
    $fullname    = trim($_POST['admin_name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = trim($_POST['password'] ?? '');
    $contact_no  = trim($_POST['contact_no'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $status      = in_array($_POST['account_status'] ?? '', ['Active', 'Inactive']) ? $_POST['account_status'] : 'Active';

    if (empty($district) || empty($fullname) || empty($username) || empty($contact_no) || empty($email)) {
        $msg = 'Please fill in all required fields.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        } else {
            $_SESSION['flash_error'] = $msg;
            header("Location: districts.php");
            exit();
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid official email address.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        } else {
            $_SESSION['flash_error'] = $msg;
            header("Location: districts.php");
            exit();
        }
    }

    if ($edit_id > 0) {
        // UPDATE EXISTING DISTRICT ADMIN
        $stmt_chk = $conn->prepare("SELECT district_admin_id FROM district_admin WHERE username = ? AND district_admin_id != ?");
        $stmt_chk->bind_param("si", $username, $edit_id);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows > 0) {
            $stmt_chk->close();
            $msg = 'This login username is already taken.';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_error'] = $msg;
                header("Location: districts.php");
                exit();
            }
        }
        $stmt_chk->close();

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt_up = $conn->prepare("UPDATE district_admin SET district = ?, admin_name = ?, username = ?, password = ?, contact_no = ?, email = ?, status = ? WHERE district_admin_id = ?");
            $stmt_up->bind_param("sssssssi", $district, $fullname, $username, $hashed, $contact_no, $email, $status, $edit_id);
        } else {
            $stmt_up = $conn->prepare("UPDATE district_admin SET district = ?, admin_name = ?, username = ?, contact_no = ?, email = ?, status = ? WHERE district_admin_id = ?");
            $stmt_up->bind_param("ssssssi", $district, $fullname, $username, $contact_no, $email, $status, $edit_id);
        }

        if ($stmt_up->execute()) {
            $stmt_up->close();
            $msg = 'District Administrator account updated successfully!';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: districts.php");
                exit();
            }
        } else {
            $err = 'Database error while updating account.';
            $stmt_up->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $err]);
                exit();
            } else {
                $_SESSION['flash_error'] = $err;
                header("Location: districts.php");
                exit();
            }
        }
    } else {
        // CREATE NEW DISTRICT ADMIN
        if (empty($password)) {
            $msg = 'Password is required for a new account.';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_error'] = $msg;
                header("Location: districts.php");
                exit();
            }
        }

        $stmt_chk1 = $conn->prepare("SELECT district_admin_id FROM district_admin WHERE username = ?");
        $stmt_chk1->bind_param("s", $username);
        $stmt_chk1->execute();
        $taken = $stmt_chk1->get_result()->num_rows > 0;
        $stmt_chk1->close();

        if ($taken) {
            $msg = 'Username already registered to an existing account.';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_error'] = $msg;
                header("Location: districts.php");
                exit();
            }
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt_ins = $conn->prepare("
            INSERT INTO district_admin (admin_name, username, password, district, contact_no, email, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_ins->bind_param("sssssss", $fullname, $username, $hashed, $district, $contact_no, $email, $status);

        if ($stmt_ins->execute()) {
            $stmt_ins->close();
            $msg = "District Administrator for '$district' successfully assigned and registered!";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: districts.php");
                exit();
            }
        } else {
            $err = 'Database error: ' . $conn->error;
            $stmt_ins->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $err]);
                exit();
            } else {
                $_SESSION['flash_error'] = $err;
                header("Location: districts.php");
                exit();
            }
        }
    }
}

// ── PENDING PHC PROPOSALS BADGE ───────────────────────────────────────────────
$res_prop = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop->fetch_assoc()['cnt'] ?? 0);

// ── FETCH ALL REGISTERED DISTRICT ADMINS ──────────────────────────────────────
$districts_query = "
    SELECT da.*, COUNT(p.phc_id) AS phc_count
    FROM district_admin da
    LEFT JOIN phcs p ON p.district_admin_id = da.district_admin_id OR p.district = da.district
    GROUP BY da.district_admin_id
    ORDER BY da.district ASC
";
$districts_res = $conn->query($districts_query);
$districts_list = $districts_res ? $districts_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Districts &amp; Admins - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_districts.css">
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
            <li class="active"><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts &amp; Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i><span>System Analytics</span></a></li>
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
                <h2>District Administrators Management</h2>
                <p>Create, assign, and manage District Health Officer (DHO) accounts across regions</p>
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

            <!-- NOTIFICATION BANNERS -->
            <div class="alert-banner success-banner" id="districtSuccessBanner" style="<?php echo !empty($flash_success) ? 'display: flex;' : 'display: none;'; ?>">
                <i class="fas fa-check-circle"></i>
                <span id="districtBannerText"><?php echo !empty($flash_success) ? htmlspecialchars($flash_success) : 'District Admin account saved successfully!'; ?></span>
            </div>
            <div class="alert-banner error-banner" id="districtErrorBanner" style="<?php echo !empty($flash_error) ? 'display: flex;' : 'display: none;'; ?> background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="districtErrorText"><?php echo !empty($flash_error) ? htmlspecialchars($flash_error) : 'An error occurred.'; ?></span>
            </div>

            <!-- ACTION BAR: Search & Add Button -->
            <div class="page-action-bar">
                <div class="search-box">
                    <input type="text" id="districtSearchInput" placeholder="Search by District or Admin Name...">
                </div>
                <button type="button" class="btn-primary-action" id="openDistrictModalBtn">
                    <i class="fas fa-user-plus"></i> Assign New District Admin
                </button>
            </div>

            <!-- DISTRICT ADMINS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Registered District Administrators</h3>
                    <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($districts_list); ?> Districts Registered</span>
                </div>
                <table class="data-table" id="districtTable">
                    <thead>
                        <tr>
                            <th>District ID</th>
                            <th>District Name</th>
                            <th>District Admin (DHO) Name</th>
                            <th>Username / Login</th>
                            <th>Contact &amp; Email</th>
                            <th>Assigned PHCs</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($districts_list)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 25px;">
                                    No district administrators registered yet. Click "Assign New District Admin" to add one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($districts_list as $row): 
                                $code_id = 'DIS-' . str_pad($row['district_admin_id'], 2, '0', STR_PAD_LEFT);
                                $status_badge = ($row['status'] === 'Active') ? 'status-active' : 'status-inactive';
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($code_id); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['district']); ?></td>
                                    <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($row['username']); ?></code></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['contact_no'] ?? '-'); ?><br>
                                        <small style="color:var(--text-secondary);"><?php echo htmlspecialchars($row['email'] ?? '-'); ?></small>
                                    </td>
                                    <td><?php echo number_format((int)$row['phc_count']); ?> PHCs</td>
                                    <td><span class="badge-status <?php echo $status_badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td>
                                        <button type="button" class="btn-action btn-edit edit-admin-btn"
                                            data-id="<?php echo (int)$row['district_admin_id']; ?>"
                                            data-district="<?php echo htmlspecialchars($row['district']); ?>"
                                            data-admin="<?php echo htmlspecialchars($row['admin_name']); ?>"
                                            data-username="<?php echo htmlspecialchars($row['username']); ?>"
                                            data-contact="<?php echo htmlspecialchars($row['contact_no'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($row['email'] ?? ''); ?>"
                                            data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
     ADD / EDIT DISTRICT ADMIN MODAL
     ============================== -->
<div class="modal-overlay" id="districtModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Assign District Administrator</h3>
            <button type="button" class="modal-close-btn" id="closeDistrictModalBtn">&times;</button>
        </div>
        <form id="districtForm" class="modal-form" method="POST" action="districts.php">
            <input type="hidden" id="edit_id" name="edit_id" value="">
            <input type="hidden" name="ajax" value="1">

            <div class="form-group">
                <label for="district_name">District Name</label>
                <input type="text" id="district_name" name="district_name" required placeholder="e.g. Porbandar">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="admin_name">Admin / DHO Full Name</label>
                    <input type="text" id="admin_name" name="admin_name" required placeholder="e.g. Dr. V. P. Rathod">
                </div>
                <div class="form-group">
                    <label for="username">Login Username</label>
                    <input type="text" id="username" name="username" required placeholder="e.g. district_admin_pb">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Set Login Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password (leave blank to keep current if editing)">
                </div>
                <div class="form-group">
                    <label for="contact_no">Contact Number</label>
                    <input type="text" id="contact_no" name="contact_no" required placeholder="10-digit mobile number">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Official Email Address</label>
                <input type="email" id="email" name="email" required placeholder="dho.district@gujarat.gov.in">
            </div>

            <div class="form-group">
                <label for="account_status">Account Status</label>
                <select id="account_status" name="account_status" required>
                    <option value="Active">Active (Can Login)</option>
                    <option value="Inactive">Inactive (Suspended)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit" id="submitDistrictBtn">Save District Admin Account</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>

<!-- INLINE SCRIPT PREVENTS STALE BROWSER CACHING -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn  = document.getElementById("openDistrictModalBtn");
    const closeModalBtn = document.getElementById("closeDistrictModalBtn");
    const modal         = document.getElementById("districtModal");
    const form          = document.getElementById("districtForm");
    const banner        = document.getElementById("districtSuccessBanner");
    const bannerText    = document.getElementById("districtBannerText");
    const errorBanner   = document.getElementById("districtErrorBanner");
    const errorText     = document.getElementById("districtErrorText");
    const submitBtn     = document.getElementById("submitDistrictBtn");
    const modalTitle    = document.getElementById("modalTitle");
    const searchInput   = document.getElementById("districtSearchInput");
    const table         = document.getElementById("districtTable");

    // Open Modal for New District Admin
    if (openModalBtn && modal) {
        openModalBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("edit_id").value = "";
            document.getElementById("password").required = true;
            document.getElementById("password").placeholder = "Temporary password";
            if (modalTitle) modalTitle.textContent = "Assign New District Administrator";
            modal.classList.add("active");
        });
    }

    // Edit button click handlers
    document.querySelectorAll(".edit-admin-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("edit_id").value        = btn.dataset.id;
            document.getElementById("district_name").value  = btn.dataset.district;
            document.getElementById("admin_name").value     = btn.dataset.admin;
            document.getElementById("username").value       = btn.dataset.username;
            document.getElementById("contact_no").value     = btn.dataset.contact;
            document.getElementById("email").value          = btn.dataset.email;
            document.getElementById("account_status").value = btn.dataset.status;
            
            document.getElementById("password").required = false;
            document.getElementById("password").placeholder = "Leave blank to keep current password";
            
            if (modalTitle) modalTitle.textContent = "Edit District Administrator (" + btn.dataset.district + ")";
            modal.classList.add("active");
        });
    });

    // Close modal
    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
    }
    window.addEventListener("click", (e) => {
        if (modal && e.target === modal) modal.classList.remove("active");
    });

    // Submit form via AJAX
    if (form && modal) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();

            if (banner) banner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            const originalBtn = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving to Database...';

            const formData = new FormData(form);

            fetch('districts.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    modal.classList.remove("active");
                    form.reset();
                    if (bannerText) bannerText.textContent = data.message;
                    if (banner) {
                        banner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    if (errorText) errorText.textContent = data.message;
                    if (errorBanner) {
                        errorBanner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            })
            .catch(err => {
                console.error("Save district error:", err);
                form.submit();
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtn;
            });
        });
    }

    // Client-side search in table
    if (searchInput && table) {
        searchInput.addEventListener("keyup", () => {
            const query = searchInput.value.toLowerCase();
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {
                const districtText = rows[i].getElementsByTagName("td")[1]?.textContent || "";
                const adminText    = rows[i].getElementsByTagName("td")[2]?.textContent || "";
                const userText     = rows[i].getElementsByTagName("td")[3]?.textContent || "";

                if (districtText.toLowerCase().includes(query) || adminText.toLowerCase().includes(query) || userText.toLowerCase().includes(query)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }
});
</script>
</body>
</html>