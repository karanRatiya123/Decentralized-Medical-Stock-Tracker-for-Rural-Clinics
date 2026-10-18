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

// ── HANDLE PROPOSAL APPROVAL / REJECTION (POST) ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $request_id = (int)($_POST['request_id'] ?? 0);
    $action     = trim($_POST['action'] ?? '');
    $remarks    = trim($_POST['sys_remarks'] ?? '');

    if ($request_id <= 0 || !in_array($action, ['Approve', 'Reject'])) {
        $msg = 'Invalid proposal request or action selected.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        } else {
            $_SESSION['flash_error'] = $msg;
            header("Location: phc_requests.php");
            exit();
        }
    }

    // Fetch existing request
    $stmt_req = $conn->prepare("SELECT * FROM phc_creation_requests WHERE request_id = ?");
    $stmt_req->bind_param("i", $request_id);
    $stmt_req->execute();
    $prop = $stmt_req->get_result()->fetch_assoc();
    $stmt_req->close();

    if (!$prop) {
        $msg = 'Proposal request not found in database.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        } else {
            $_SESSION['flash_error'] = $msg;
            header("Location: phc_requests.php");
            exit();
        }
    }

    if ($action === 'Approve') {
        $phc_name = trim($_POST['db_phc_name'] ?? '');
        $taluka   = trim($_POST['db_taluka'] ?? '');
        $village  = trim($_POST['db_village'] ?? '');
        $contact  = trim($_POST['db_contact'] ?? '');
        $address  = trim($_POST['db_address'] ?? '');

        if (empty($phc_name) || empty($taluka) || empty($village)) {
            $msg = 'PHC Name, Taluka, and Village are required for registration.';
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_error'] = $msg;
                header("Location: phc_requests.php");
                exit();
            }
        }

        $district_admin_id = (int)$prop['district_admin_id'];
        $district          = $prop['district'];

        // Verify valid district_admin_id to satisfy foreign key constraint
        $chk_da = $conn->prepare("SELECT district_admin_id FROM district_admin WHERE district_admin_id = ?");
        $chk_da->bind_param("i", $district_admin_id);
        $chk_da->execute();
        if ($chk_da->get_result()->num_rows === 0) {
            $chk_da2 = $conn->prepare("SELECT district_admin_id FROM district_admin WHERE district = ? LIMIT 1");
            $chk_da2->bind_param("s", $district);
            $chk_da2->execute();
            $row_da2 = $chk_da2->get_result()->fetch_assoc();
            if ($row_da2) {
                $district_admin_id = (int)$row_da2['district_admin_id'];
            } else {
                // Get any active district admin as fallback
                $chk_any = $conn->query("SELECT district_admin_id FROM district_admin LIMIT 1");
                if ($chk_any && $row_any = $chk_any->fetch_assoc()) {
                    $district_admin_id = (int)$row_any['district_admin_id'];
                }
            }
            $chk_da2->close();
        }
        $chk_da->close();

        // Begin transaction
        $conn->begin_transaction();
        try {
            // 1. Insert new PHC into master phcs table
            $stmt_ins = $conn->prepare("
                INSERT INTO phcs (district_admin_id, phc_name, village, Taluka, district, address, contact_no, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt_ins->bind_param("issssss", $district_admin_id, $phc_name, $village, $taluka, $district, $address, $contact);
            $stmt_ins->execute();
            $new_phc_id = $stmt_ins->insert_id;
            $stmt_ins->close();

            // 2. Update status of the proposal request
            $stmt_up = $conn->prepare("UPDATE phc_creation_requests SET status = 'Approved', admin_remarks = ? WHERE request_id = ?");
            $stmt_up->bind_param("si", $remarks, $request_id);
            $stmt_up->execute();
            $stmt_up->close();

            $conn->commit();
            $msg = "Proposal approved! New facility '$phc_name' (ID: PHC-$new_phc_id) has been created and registered into the active system database.";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: phc_requests.php");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $err = 'Database error during registration: ' . $e->getMessage();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $err]);
                exit();
            } else {
                $_SESSION['flash_error'] = $err;
                header("Location: phc_requests.php");
                exit();
            }
        }
    } else {
        // Rejection
        $stmt_up = $conn->prepare("UPDATE phc_creation_requests SET status = 'Rejected', admin_remarks = ? WHERE request_id = ?");
        $stmt_up->bind_param("si", $remarks, $request_id);
        if ($stmt_up->execute()) {
            $stmt_up->close();
            $msg = "Proposal #REQ-P" . str_pad($request_id, 2, '0', STR_PAD_LEFT) . " has been officially marked as Rejected.";
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $msg]);
                exit();
            } else {
                $_SESSION['flash_success'] = $msg;
                header("Location: phc_requests.php");
                exit();
            }
        } else {
            $err = 'Failed to update proposal status.';
            $stmt_up->close();
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $err]);
                exit();
            } else {
                $_SESSION['flash_error'] = $err;
                header("Location: phc_requests.php");
                exit();
            }
        }
    }
}

// ── PENDING PHC PROPOSALS BADGE ───────────────────────────────────────────────
$res_prop_count = $conn->query("SELECT COUNT(*) AS cnt FROM phc_creation_requests WHERE status = 'Pending Review'");
$pending_phc_requests = (int)($res_prop_count->fetch_assoc()['cnt'] ?? 0);

// ── FETCH ALL INCOMING PROPOSALS ──────────────────────────────────────────────
$proposals_query = "
    SELECT r.*, da.admin_name
    FROM phc_creation_requests r
    LEFT JOIN district_admin da ON r.district_admin_id = da.district_admin_id
    ORDER BY r.submitted_date DESC
";
$proposals_res = $conn->query($proposals_query);
$proposals_list = $proposals_res ? $proposals_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHC Document Requests - System Admin - MedStock Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/system_admin.css">
    <link rel="stylesheet" href="../css/system_phc_requests.css">
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
            <li class="active">
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
                <h2>PHC Document Proposals Review</h2>
                <p>Review attached official documents, extract facility parameters, and register new PHCs into the database</p>
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
            <div class="alert-banner success-banner" id="sysActionBanner" style="<?php echo !empty($flash_success) ? 'display: flex;' : 'display: none;'; ?>">
                <i class="fas fa-check-circle"></i>
                <span id="sysActionBannerText"><?php echo !empty($flash_success) ? htmlspecialchars($flash_success) : 'PHC successfully registered into the master database!'; ?></span>
            </div>
            <div class="alert-banner error-banner" id="sysErrorBanner" style="<?php echo !empty($flash_error) ? 'display: flex;' : 'display: none;'; ?> background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i>
                <span id="sysErrorBannerText"><?php echo !empty($flash_error) ? htmlspecialchars($flash_error) : 'Error processing proposal request.'; ?></span>
            </div>

            <!-- REQUESTS TABLE PANEL -->
            <div class="data-panel">
                <div class="panel-header">
                    <h3 class="section-title">Incoming District PHC Proposals &amp; Documents</h3>
                    <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($proposals_list); ?> Proposals Recorded</span>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-id">Request ID</th>
                            <th class="col-district">District</th>
                            <th class="desc-col">Proposal Description</th>
                            <th class="doc-col">Attached Doc</th>
                            <th class="col-date">Date</th>
                            <th class="col-status">Status</th>
                            <th class="action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proposals_list)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 25px;">
                                    No PHC creation proposals submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($proposals_list as $row): 
                                $code_id = 'REQ-P' . str_pad($row['request_id'], 2, '0', STR_PAD_LEFT);
                                $status_badge = match($row['status']) {
                                    'Approved' => 'status-active',
                                    'Rejected' => 'status-inactive',
                                    default    => 'status-pending'
                                };
                            ?>
                                <tr>
                                    <td class="col-id"><strong><?php echo htmlspecialchars($code_id); ?></strong></td>
                                    <td class="col-district">
                                        <strong><?php echo htmlspecialchars($row['district']); ?></strong><br>
                                        <small style="color:var(--text-secondary);"><?php echo htmlspecialchars($row['admin_name'] ?? 'District Admin'); ?></small>
                                    </td>
                                    <td class="desc-col"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="doc-col">
                                        <?php if (!empty($row['document_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($row['document_path']); ?>" target="_blank" class="doc-link" style="color: var(--accent-color); font-weight: 500;">
                                                <i class="fas fa-file-pdf"></i> <?php echo basename($row['document_path']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-secondary);">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-date"><?php echo date("d M Y", strtotime($row['submitted_date'])); ?></td>
                                    <td class="col-status"><span class="badge-status <?php echo $status_badge; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td class="action-cell">
                                        <?php if ($row['status'] === 'Pending Review'): ?>
                                            <div class="action-buttons-group">
                                                <button type="button" class="btn-approve" onclick="openReviewModal(<?php echo (int)$row['request_id']; ?>, 'Approve', '<?php echo htmlspecialchars($code_id); ?>')" title="Review Document &amp; Register PHC">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn-reject" onclick="openReviewModal(<?php echo (int)$row['request_id']; ?>, 'Reject', '<?php echo htmlspecialchars($code_id); ?>')" title="Reject Proposal">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: var(--text-secondary);">
                                                <?php echo !empty($row['admin_remarks']) ? 'Note: ' . htmlspecialchars($row['admin_remarks']) : 'Completed'; ?>
                                            </span>
                                        <?php endif; ?>
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
     REVIEW PROPOSAL MODAL
     ============================== -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-card" style="max-width: 550px;">
        <div class="modal-header">
            <h3 id="modalTitle">Review Proposal &amp; Enter PHC Data</h3>
            <button type="button" class="modal-close-btn" id="closeModalBtn">&times;</button>
        </div>
        <form id="reviewForm" class="modal-form" method="POST" action="phc_requests.php">
            <input type="hidden" id="modal_request_id" name="request_id" value="">
            <input type="hidden" id="modal_action" name="action" value="Approve">
            <input type="hidden" name="ajax" value="1">
            
            <!-- Database fields to be filled from the uploaded document -->
            <div id="phcDatabaseFields">
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_phc_name">PHC Official Name</label>
                        <input type="text" id="db_phc_name" name="db_phc_name" placeholder="e.g. PHC Talala Rural">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_taluka">Taluka</label>
                        <input type="text" id="db_taluka" name="db_taluka" placeholder="e.g. Talala">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_village">Village / Town</label>
                        <input type="text" id="db_village" name="db_village" placeholder="e.g. Talala City">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="db_contact">Facility Contact Number</label>
                        <input type="text" id="db_contact" name="db_contact" placeholder="e.g. 02878-221099">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label for="db_address">Complete Street Address</label>
                    <input type="text" id="db_address" name="db_address" placeholder="e.g. Near State Highway, Talala">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="sys_remarks">System Admin Remarks / Approval Notes</label>
                <textarea id="sys_remarks" name="sys_remarks" required placeholder="Enter notes verified from official document..."></textarea>
            </div>

            <button type="submit" class="btn-submit" id="modalSubmitBtn">Confirm &amp; Register to Database</button>
        </form>
    </div>
</div>

<script src="../js/theme.js"></script>

<!-- INLINE SCRIPT GUARANTEES LIVE SUBMISSION WITHOUT BROWSER CACHE PROBLEMS -->
<script>
let currentReqId = null;
let currentAction = null;

function openReviewModal(reqId, action, codeId) {
    currentReqId = reqId;
    currentAction = action;
    
    document.getElementById('modal_request_id').value = reqId;
    document.getElementById('modal_action').value = action;

    const modalTitle = document.getElementById('modalTitle');
    const submitBtn  = document.getElementById('modalSubmitBtn');
    const modal      = document.getElementById('reviewModal');
    const dbFields   = document.getElementById('phcDatabaseFields');

    const displayCode = codeId || (`REQ-P` + String(reqId).padStart(2, '0'));

    if (modalTitle) {
        modalTitle.textContent = (action === 'Approve') 
            ? `Verify Document & Register PHC (${displayCode})` 
            : `Reject Proposal (${displayCode})`;
    }
    
    if (submitBtn) {
        submitBtn.textContent = (action === 'Approve') 
            ? 'Confirm Approval & Register into Database' 
            : 'Confirm Rejection';
        submitBtn.style.backgroundColor = (action === 'Approve') ? '#10b981' : '#ef4444';
    }
    
    // Show database fields only when approving
    if (dbFields) {
        dbFields.style.display = (action === 'Approve') ? 'block' : 'none';
        
        const inputs = dbFields.querySelectorAll('input');
        inputs.forEach(input => {
            input.required = (action === 'Approve' && input.id !== 'db_contact' && input.id !== 'db_address');
        });
    }

    if (modal) modal.classList.add('active');
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    const form  = document.getElementById('reviewForm');
    if (modal) modal.classList.remove('active');
    if (form) form.reset();
}

document.addEventListener("DOMContentLoaded", () => {
    const closeModalBtn = document.getElementById('closeModalBtn');
    const reviewForm    = document.getElementById('reviewForm');
    const submitBtn     = document.getElementById('modalSubmitBtn');
    const banner        = document.getElementById('sysActionBanner');
    const bannerText    = document.getElementById('sysActionBannerText');
    const errorBanner   = document.getElementById('sysErrorBanner');
    const errorText     = document.getElementById('sysErrorBannerText');

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeReviewModal);
    }

    window.addEventListener('click', (e) => {
        const modal = document.getElementById('reviewModal');
        if (e.target === modal) {
            closeReviewModal();
        }
    });

    if (reviewForm) {
        reviewForm.addEventListener('submit', (e) => {
            e.preventDefault();

            if (banner) banner.style.display = 'none';
            if (errorBanner) errorBanner.style.display = 'none';

            const origText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving to Database...';

            const formData = new FormData(reviewForm);

            fetch('phc_requests.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                closeReviewModal();
                if (data.success) {
                    if (bannerText) bannerText.textContent = data.message;
                    if (banner) {
                        banner.style.display = 'flex';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    if (errorText) errorText.textContent = data.message;
                    if (errorBanner) {
                        errorBanner.style.display = 'flex';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            })
            .catch(err => {
                console.error("Proposal review error:", err);
                // Fallback to normal submit if fetch fails
                reviewForm.submit();
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            });
        });
    }
});
</script>
</body>
</html>