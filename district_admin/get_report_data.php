<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ── ROUTE GUARD (District Admin Only) ──────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'district_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

require_once '../config/database.php';

$admin_id = (int)$_SESSION['user_id'];
$district = $_SESSION['district'] ?? '';
$district_esc = $conn->real_escape_string($district);

// Read query parameters
$phc_id   = isset($_GET['phc_id']) ? (int)$_GET['phc_id'] : 0;
$period   = isset($_GET['period']) ? trim($_GET['period']) : 'month';
$category = isset($_GET['category']) ? trim($_GET['category']) : 'overview';

// ── VERIFY PHC OWNERSHIP (If specific PHC is requested) ──────────────────────
if ($phc_id > 0) {
    $stmt_chk = $conn->prepare("SELECT phc_id, phc_name FROM phcs WHERE phc_id = ? AND (district = ? OR district_admin_id = ?)");
    $stmt_chk->bind_param("isi", $phc_id, $district, $admin_id);
    $stmt_chk->execute();
    $phc_row = $stmt_chk->get_result()->fetch_assoc();
    $stmt_chk->close();
    if (!$phc_row) {
        $phc_id = 0; // fallback to district-wide if not owned
    }
}

// ── DATE RANGE CONDITIONS ─────────────────────────────────────────────────────
if ($period === 'today') {
    $date_cond_pv = " AND DATE(pv.visit_timestamp) = CURDATE() ";
    $date_cond_si = " AND DATE(s.received_date) = CURDATE() ";
    $date_cond_mr = " AND DATE(mr.request_date) = CURDATE() ";
    $period_label = "Today (" . date("d M Y") . ")";
    $date_group_sql = "DATE_FORMAT(pv.visit_timestamp, '%h %p')";
    $date_order_sql = "MIN(pv.visit_timestamp)";
} elseif ($period === 'quarter') {
    $date_cond_pv = " AND pv.visit_timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY) ";
    $date_cond_si = " AND s.received_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) ";
    $date_cond_mr = " AND mr.request_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) ";
    $period_label = "Last 3 Months (" . date('M Y', strtotime('-90 days')) . " - " . date('M Y') . ")";
    $date_group_sql = "DATE_FORMAT(pv.visit_timestamp, '%b %Y')";
    $date_order_sql = "MIN(pv.visit_timestamp)";
} elseif ($period === 'year') {
    $date_cond_pv = " AND YEAR(pv.visit_timestamp) = YEAR(CURDATE()) ";
    $date_cond_si = " AND YEAR(s.received_date) = YEAR(CURDATE()) ";
    $date_cond_mr = " AND YEAR(mr.request_date) = YEAR(CURDATE()) ";
    $period_label = "Year to Date (" . date('Y') . ")";
    $date_group_sql = "DATE_FORMAT(pv.visit_timestamp, '%b %Y')";
    $date_order_sql = "MIN(pv.visit_timestamp)";
} else { // default 'month'
    $date_cond_pv = " AND MONTH(pv.visit_timestamp) = MONTH(CURDATE()) AND YEAR(pv.visit_timestamp) = YEAR(CURDATE()) ";
    $date_cond_si = " AND MONTH(s.received_date) = MONTH(CURDATE()) AND YEAR(s.received_date) = YEAR(CURDATE()) ";
    $date_cond_mr = " AND MONTH(mr.request_date) = MONTH(CURDATE()) AND YEAR(mr.request_date) = YEAR(CURDATE()) ";
    $period_label = date('F Y');
    $date_group_sql = "DATE_FORMAT(pv.visit_timestamp, '%d %b')";
    $date_order_sql = "MIN(pv.visit_timestamp)";
}

// ── BASE PHC FILTER CLAUSES ──────────────────────────────────────────────────
if ($phc_id > 0) {
    $filter_phc_pv = " pv.phc_id = $phc_id ";
    $filter_phc_si = " s.phc_id = $phc_id ";
    $filter_phc_mr = " mr.phc_id = $phc_id ";
    $filter_phc_p  = " p.phc_id = $phc_id ";
} else {
    $filter_phc_pv = " (p.district = '$district_esc' OR p.district_admin_id = $admin_id) ";
    $filter_phc_si = " (p.district = '$district_esc' OR p.district_admin_id = $admin_id) ";
    $filter_phc_mr = " (p.district = '$district_esc' OR p.district_admin_id = $admin_id) ";
    $filter_phc_p  = " (p.district = '$district_esc' OR p.district_admin_id = $admin_id) ";
}

// ── 1. CONSUMPTION TRENDS (CHART 1) ──────────────────────────────────────────
$chart_labels = [];
$chart_consumed = [];

$query_trend = "
    SELECT {$date_group_sql} AS time_slot,
           COALESCE(SUM(vm.quantity_issued), 0) AS total_issued
    FROM visit_medicines vm
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id
    JOIN phcs p ON pv.phc_id = p.phc_id
    WHERE {$filter_phc_pv}
      {$date_cond_pv}
    GROUP BY {$date_group_sql}
    ORDER BY {$date_order_sql} ASC
";
$res_trend = $conn->query($query_trend);

if ($res_trend && $res_trend->num_rows > 0) {
    while ($row = $res_trend->fetch_assoc()) {
        $chart_labels[] = $row['time_slot'];
        $chart_consumed[] = (int)$row['total_issued'];
    }
} else {
    // Graceful fallback for empty interval so Chart.js renders a clean baseline
    if ($period === 'today') {
        $chart_labels = ['09 AM', '11 AM', '01 PM', '03 PM', '05 PM'];
        $chart_consumed = [0, 0, 0, 0, 0];
    } elseif ($period === 'month') {
        $chart_labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        $chart_consumed = [0, 0, 0, 0];
    } else {
        $chart_labels = [date('M', strtotime('-2 months')), date('M', strtotime('-1 month')), date('M')];
        $chart_consumed = [0, 0, 0];
    }
}

// ── 2. STOCK LEVEL COMPARISON (CHART 2) ──────────────────────────────────────
$phc_names = [];
$phc_stocks = [];

if ($phc_id > 0) {
    // If a single PHC is selected: show top medicines in stock for that PHC
    $query_stock = "
        SELECT m.medicine_name, COALESCE(i.quantity, 0) AS stock_qty
        FROM medicines m
        JOIN inventory i ON m.medicine_id = i.medicine_id
        WHERE i.phc_id = $phc_id
        ORDER BY stock_qty DESC
        LIMIT 8
    ";
    $res_stock = $conn->query($query_stock);
    if ($res_stock && $res_stock->num_rows > 0) {
        while ($row = $res_stock->fetch_assoc()) {
            $phc_names[] = $row['medicine_name'];
            $phc_stocks[] = (int)$row['stock_qty'];
        }
    }
} else {
    // All PHCs comparison
    $query_stock = "
        SELECT p.phc_name, COALESCE(SUM(i.quantity), 0) AS total_stock
        FROM phcs p
        LEFT JOIN inventory i ON p.phc_id = i.phc_id
        WHERE {$filter_phc_p}
        GROUP BY p.phc_id, p.phc_name
        ORDER BY total_stock DESC, p.phc_name ASC
        LIMIT 10
    ";
    $res_stock = $conn->query($query_stock);
    if ($res_stock && $res_stock->num_rows > 0) {
        while ($row = $res_stock->fetch_assoc()) {
            $phc_names[] = $row['phc_name'];
            $phc_stocks[] = (int)$row['total_stock'];
        }
    }
}

if (empty($phc_names)) {
    $phc_names = ['No stock data'];
    $phc_stocks = [0];
}

// ── 3. DETAILED PERFORMANCE SUMMARY TABLE ───────────────────────────────────
$query_table = "
    SELECT 
        p.phc_id,
        p.phc_name,
        p.Taluka,
        -- Total patient visits in period
        (SELECT COUNT(*) FROM patient_visits pv 
         WHERE pv.phc_id = p.phc_id {$date_cond_pv}) AS total_visits,
        -- Total medicines dispensed in period
        (SELECT COALESCE(SUM(vm.quantity_issued), 0) 
         FROM visit_medicines vm 
         JOIN patient_visits pv ON vm.visit_id = pv.visit_id 
         WHERE pv.phc_id = p.phc_id {$date_cond_pv}) AS dispensed_units,
        -- Total batches received in period
        (SELECT COUNT(*) FROM stock_in s 
         WHERE s.phc_id = p.phc_id {$date_cond_si}) AS stock_batches,
        -- Pending requisitions
        (SELECT COUNT(*) FROM medicine_requests mr 
         WHERE mr.phc_id = p.phc_id AND mr.status = 'Pending') AS pending_reqs,
        -- Total inventory items in stock
        (SELECT COALESCE(SUM(i.quantity), 0) FROM inventory i 
         WHERE i.phc_id = p.phc_id) AS total_inventory
    FROM phcs p
    WHERE {$filter_phc_p}
    ORDER BY p.phc_name ASC
";
$res_table = $conn->query($query_table);

$table_html = '';
$kpi_visits = 0;
$kpi_dispensed = 0;
$kpi_stock_batches = 0;
$kpi_pending_reqs = 0;

if ($res_table && $res_table->num_rows > 0) {
    while ($row = $res_table->fetch_assoc()) {
        $v = (int)$row['total_visits'];
        $d = (int)$row['dispensed_units'];
        $b = (int)$row['stock_batches'];
        $r = (int)$row['pending_reqs'];

        $kpi_visits += $v;
        $kpi_dispensed += $d;
        $kpi_stock_batches += $b;
        $kpi_pending_reqs += $r;

        // Efficiency score calculation based on active fulfillment and activity
        $score = 96;
        if ($r > 5) {
            $score -= 15;
        } elseif ($r > 0) {
            $score -= ($r * 2);
        }
        if ($v > 0 && $d === 0) {
            $score -= 10;
        }
        $score = max(65, min(99, $score));

        if ($score >= 90) {
            $badge_class = 'score-high';
            $badge_label = $score . '% Excellent';
        } elseif ($score >= 75) {
            $badge_class = 'score-medium';
            $badge_label = $score . '% Good';
        } else {
            $badge_class = 'badge-critical';
            $badge_label = $score . '% Needs Action';
        }

        $table_html .= '<tr>';
        $table_html .= '<td><strong>' . htmlspecialchars($row['phc_name']) . '</strong><br><small style="color:var(--text-secondary);">' . htmlspecialchars($row['Taluka']) . ' Taluka</small></td>';
        $table_html .= '<td><span style="font-weight:600;color:var(--accent-color);">' . number_format($v) . '</span> Visits</td>';
        $table_html .= '<td><span style="font-weight:600;color:#10b981;">' . number_format($d) . '</span> Units</td>';
        $table_html .= '<td>' . number_format($b) . ' Batches</td>';
        $table_html .= '<td>';
        if ($r > 0) {
            $table_html .= '<span class="badge" style="background:#f59e0b;color:#fff;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:bold;">' . $r . ' Pending</span>';
        } else {
            $table_html .= '<span style="color:#10b981;font-size:12px;"><i class="fas fa-check-circle"></i> Clear</span>';
        }
        $table_html .= '</td>';
        $table_html .= '<td><span class="badge-score ' . $badge_class . '">' . $badge_label . '</span></td>';
        $table_html .= '</tr>';
    }
} else {
    $table_html = '<tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-secondary);"><i class="fas fa-folder-open" style="font-size:24px;margin-bottom:8px;display:block;"></i>No PHC records found matching the selected filter.</td></tr>';
}

echo json_encode([
    'success'         => true,
    'chart_labels'    => $chart_labels,
    'chart_consumed'  => $chart_consumed,
    'phc_names'       => $phc_names,
    'phc_stocks'      => $phc_stocks,
    'table_html'      => $table_html,
    'period_label'    => $period_label,
    'kpis'            => [
        'total_visits'     => number_format($kpi_visits),
        'total_dispensed'  => number_format($kpi_dispensed),
        'stock_batches'    => number_format($kpi_stock_batches),
        'pending_requests' => number_format($kpi_pending_reqs),
    ],
    'timestamp'       => date('h:i:s A')
]);
