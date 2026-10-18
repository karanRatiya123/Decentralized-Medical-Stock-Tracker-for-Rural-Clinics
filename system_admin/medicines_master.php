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

// ── HANDLE ADD / EDIT MASTER MEDICINE (POST) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $edit_id      = isset($_POST['edit_id']) && !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $med_name     = trim($_POST['med_name'] ?? '');
    $category     = trim($_POST['med_category'] ?? '');
    $dosage_form  = trim($_POST['med_form'] ?? 'Tablet');
    $pack_price   = (float)($_POST['pack_price'] ?? 0);
    $tablet_price = (float)($_POST['tablet_price'] ?? 0);

    if (empty($med_name) || empty($category)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Medicine Name and Category are required.']);
            exit();
        } else {
            $_SESSION['flash_error'] = 'Medicine Name and Category are required.';
            header("Location: medicines_master.php");
            exit();
        }
    }

    if ($pack_price < 0 || $tablet_price < 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Prices cannot be negative.']);
            exit();
        } else {
            $_SESSION['flash_error'] = 'Prices cannot be negative.';
            header("Location: medicines_master.php");
            exit();
        }
    }

    // Combine form and category cleanly
    $full_category = $category;
    if (!empty($dosage_form) && !str_contains(strtolower($category), strtolower($dosage_form))) {
        $full_category = $category . ' (' . $dosage_form . ')';
    }

    $pack_qty = ($tablet_price > 0 && $pack_price > 0) ? (int)round($pack_price / $tablet_price) : 10;
    if ($pack_qty <= 0) $pack_qty = 10;

    if ($edit_id > 0) {
        // UPDATE EXISTING MEDICINE
        $stmt_chk = $conn->prepare("SELECT medicine_id FROM medicines WHERE medicine_name = ? AND medicine_id != ?");
        $stmt_chk->bind_param("si", $med_name, $edit_id);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows > 0) {
            $stmt_chk->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Another medicine with this name already exists in the catalog.']);
                exit();
            } else {
                $_SESSION['flash_error'] = 'Another medicine with this name already exists in the catalog.';
                header("Location: medicines_master.php");
                exit();
            }
        }
        $stmt_chk->close();

        $stmt_up = $conn->prepare("
            UPDATE medicines 
            SET medicine_name = ?, category = ?, pack_quantity = ?, pack_price = ?, tablet_price = ?
            WHERE medicine_id = ?
        ");
        $stmt_up->bind_param("ssiddd", $med_name, $full_category, $pack_qty, $pack_price, $tablet_price, $edit_id);

        if ($stmt_up->execute()) {
            $stmt_up->close();
            $msg = "Master medicine '$med_name' updated successfully!";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: medicines_master.php");
                exit();
            }
        } else {
            $err = 'Database update error: ' . $conn->error;
            $stmt_up->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $err]);
                exit();
            } else {
                $_SESSION['flash_error'] = $err;
                header("Location: medicines_master.php");
                exit();
            }
        }
    } else {
        // INSERT NEW MEDICINE
        $stmt_chk = $conn->prepare("SELECT medicine_id FROM medicines WHERE medicine_name = ?");
        $stmt_chk->bind_param("s", $med_name);
        $stmt_chk->execute();
        if ($stmt_chk->get_result()->num_rows > 0) {
            $stmt_chk->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "Medicine '$med_name' already exists in the catalog."]);
                exit();
            } else {
                $_SESSION['flash_error'] = "Medicine '$med_name' already exists in the catalog.";
                header("Location: medicines_master.php");
                exit();
            }
        }
        $stmt_chk->close();

        $stmt_ins = $conn->prepare("
            INSERT INTO medicines (medicine_name, category, pack_quantity, pack_price, tablet_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt_ins->bind_param("ssidd", $med_name, $full_category, $pack_qty, $pack_price, $tablet_price);

        if ($stmt_ins->execute()) {
            $stmt_ins->close();
            $msg = "New medicine '$med_name' successfully added to the database!";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: medicines_master.php");
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
                header("Location: medicines_master.php");
                exit();
            }
        }
    }
}

// ── PENDING PHC PROPOSALS BADGE ───────────────────────────────────────────────
$res_prop = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop->fetch_assoc()['cnt'] ?? 0);

// ── FETCH ALL MASTER MEDICINES ────────────────────────────────────────────────
$meds_res = $conn->query("SELECT * FROM medicines ORDER BY medicine_name ASC");
$medicines_list = $meds_res ? $meds_res->fetch_all(MYSQLI_ASSOC) : [];
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
            <li><a href="districts.php"><i class="fas fa-map-marked-alt"></i><span>Districts &amp; Admins</span></a></li>
            <li>
                <a href="phc_requests.php">
                    <i class="fas fa-file-upload"></i><span>PHC Document Requests</span>
                    <span class="badge" style="background:#f59e0b; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; float:right;"><?php echo $pending_phc_requests; ?></span>
                </a>
            </li>
            <li><a href="phc_directory.php"><i class="fas fa-hospital"></i><span>Master PHC Directory</span></a></li>
            <li class="active"><a href="medicines_master.php"><i class="fas fa-pills"></i><span>Master Medicines</span></a></li>
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

            <!-- NOTIFICATION BANNERS -->
            <div class="alert-banner success-banner" id="medSuccessBanner" style="<?php echo !empty($flash_success) ? 'display: flex;' : 'display: none;'; ?>">
                <i class="fas fa-check-circle"></i>
                <span id="medBannerText"><?php echo !empty($flash_success) ? htmlspecialchars($flash_success) : 'Master medicine catalog updated successfully!'; ?></span>
            </div>
            <div class="alert-banner error-banner" id="medErrorBanner" style="<?php echo !empty($flash_error) ? 'display: flex;' : 'display: none;'; ?> background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="medErrorText"><?php echo !empty($flash_error) ? htmlspecialchars($flash_error) : 'Error updating medicine catalog.'; ?></span>
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
                    <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($medicines_list); ?> Medicines in Catalog</span>
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
                        <?php if (empty($medicines_list)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 25px;">
                                    No medicines registered in master catalog yet. Click "Add New Master Medicine" above.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medicines_list as $row): 
                                $code_id = 'MED-' . str_pad($row['medicine_id'], 2, '0', STR_PAD_LEFT);
                                
                                $category_text = $row['category'] ?? '';
                                $form = 'Tablet';
                                if (preg_match('/\((.*?)\)/', $category_text, $m)) {
                                    $form = $m[1];
                                } elseif (stripos($category_text, 'syrup') !== false) {
                                    $form = 'Syrup';
                                } elseif (stripos($category_text, 'injection') !== false) {
                                    $form = 'Injection';
                                } elseif (stripos($category_text, 'capsule') !== false) {
                                    $form = 'Capsule';
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($code_id); ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($row['medicine_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td><?php echo htmlspecialchars($form); ?></td>
                                    <td>₹<?php echo number_format((float)$row['pack_price'], 2); ?></td>
                                    <td>₹<?php echo number_format((float)$row['tablet_price'], 2); ?></td>
                                    <td><span class="badge-status status-active">Authorized</span></td>
                                    <td>
                                        <button type="button" class="btn-action btn-edit edit-med-btn"
                                            data-id="<?php echo (int)$row['medicine_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($row['medicine_name']); ?>"
                                            data-category="<?php echo htmlspecialchars($row['category']); ?>"
                                            data-form="<?php echo htmlspecialchars($form); ?>"
                                            data-pack="<?php echo (float)$row['pack_price']; ?>"
                                            data-tablet="<?php echo (float)$row['tablet_price']; ?>">
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
     ADD / EDIT MASTER MEDICINE MODAL
     ============================== -->
<div class="modal-overlay" id="medModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="medModalTitle">Add Authorized Master Medicine</h3>
            <button type="button" class="modal-close-btn" id="closeMedModalBtn">&times;</button>
        </div>
        <form id="medForm" class="modal-form" method="POST" action="medicines_master.php">
            <input type="hidden" id="edit_id" name="edit_id" value="">
            <input type="hidden" name="ajax" value="1">

            <div class="form-group">
                <label for="med_name">Medicine Name &amp; Strength</label>
                <input type="text" id="med_name" name="med_name" required placeholder="e.g. Ibuprofen 400mg">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="med_category">Category</label>
                    <input type="text" id="med_category" name="med_category" required placeholder="e.g. Analgesic / NSAID">
                </div>
                <div class="form-group">
                    <label for="med_form">Dosage Form</label>
                    <select id="med_form" name="med_form" required>
                        <option value="Tablet">Tablet</option>
                        <option value="Capsule">Capsule</option>
                        <option value="Syrup">Syrup / Liquid</option>
                        <option value="Injection">Injection</option>
                        <option value="Sachet">Sachet / Packet</option>
                    </select>
                </div>
            </div>

            <!-- PRICING FIELDS -->
            <div class="form-row">
                <div class="form-group">
                    <label for="pack_price">Standard Pack Price (₹)</label>
                    <input type="number" step="0.01" id="pack_price" name="pack_price" required placeholder="e.g. 45.00">
                </div>
                <div class="form-group">
                    <label for="tablet_price">Price per Unit / Tablet (₹)</label>
                    <input type="number" step="0.01" id="tablet_price" name="tablet_price" required placeholder="e.g. 4.50">
                </div>
            </div>

            <button type="submit" class="btn-submit" id="saveMedBtn">Save Master Medicine</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>

<!-- INLINE SCRIPT GUARANTEES NO BROWSER CACHE ISSUES -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn  = document.getElementById("openMedModalBtn");
    const closeModalBtn = document.getElementById("closeMedModalBtn");
    const modal         = document.getElementById("medModal");
    const form          = document.getElementById("medForm");
    const banner        = document.getElementById("medSuccessBanner");
    const bannerText    = document.getElementById("medBannerText");
    const errorBanner   = document.getElementById("medErrorBanner");
    const errorText     = document.getElementById("medErrorText");
    const searchInput   = document.getElementById("medSearchInput");
    const table         = document.getElementById("masterMedTable");
    const submitBtn     = document.getElementById("saveMedBtn");
    const modalTitle    = document.getElementById("medModalTitle");

    // Open Add Modal
    if (openModalBtn && modal) {
        openModalBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("edit_id").value = "";
            if (modalTitle) modalTitle.textContent = "Add Authorized Master Medicine";
            modal.classList.add("active");
        });
    }

    // Edit button handlers
    document.querySelectorAll(".edit-med-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("edit_id").value      = btn.dataset.id;
            document.getElementById("med_name").value     = btn.dataset.name;
            document.getElementById("med_category").value = btn.dataset.category;
            document.getElementById("med_form").value     = btn.dataset.form;
            document.getElementById("pack_price").value   = btn.dataset.pack;
            document.getElementById("tablet_price").value = btn.dataset.tablet;

            if (modalTitle) modalTitle.textContent = "Edit Master Medicine (" + btn.dataset.name + ")";
            modal.classList.add("active");
        });
    });

    // Close Modal
    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
    }
    window.addEventListener("click", (e) => {
        if (modal && e.target === modal) modal.classList.remove("active");
    });

    // Form submission via AJAX
    if (form && modal) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();

            if (banner) banner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            const origBtn = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving to Database...';

            const formData = new FormData(form);

            fetch('medicines_master.php', {
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
                console.error("Save medicine error:", err);
                // Fallback to standard form submission if fetch failed
                form.submit();
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtn;
            });
        });
    }

    // Client-side search in master table
    if (searchInput && table) {
        searchInput.addEventListener("keyup", () => {
            const searchText = searchInput.value.toLowerCase().trim();
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName("td")[1]?.textContent || "";
                const catCell  = rows[i].getElementsByTagName("td")[2]?.textContent || "";

                if (nameCell.toLowerCase().includes(searchText) || catCell.toLowerCase().includes(searchText)) {
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