<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ── ROUTE GUARD (System Admin Only) ───────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'system_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

require_once '../config/database.php';

// Read query parameters
$district_filter = isset($_GET['district']) ? trim($_GET['district']) : 'all';
$period          = isset($_GET['period']) ? trim($_GET['period']) : 'month';
$category        = isset($_GET['category']) ? trim($_GET['category']) : 'overview';

$is_all_districts = ($district_filter === 'all' || $district_filter === '0' || $district_filter === '');
$district_esc     = $conn->real_escape_string($district_filter);

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

// ── DISTRICT FILTER CLAUSE ────────────────────────────────────────────────────
if ($is_all_districts) {
    $dist_cond_p  = "";
    $dist_cond_da = "";
} else {
    $dist_cond_p  = " AND p.district = '$district_esc' ";
    $dist_cond_da = " WHERE da.district = '$district_esc' ";
}

// ── 1. STATEWIDE OR DISTRICT CONSUMPTION TRENDS (CHART 1) ─────────────────────
$chart_labels = [];
$chart_consumed = [];

$query_trend = "
    SELECT {$date_group_sql} AS time_slot,
           COALESCE(SUM(vm.quantity_issued), 0) AS total_issued
    FROM visit_medicines vm
    JOIN patient_visits pv ON vm.visit_id = pv.visit_id
    JOIN phcs p ON pv.phc_id = p.phc_id
    WHERE 1=1
      {$dist_cond_p}
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

// ── 2. STOCK LEVEL DISTRIBUTION (CHART 2) ─────────────────────────────────────
$comparison_names = [];
$comparison_stocks = [];

if ($is_all_districts) {
    // Group stock units by District
    $query_stock = "
        SELECT p.district AS group_name, COALESCE(SUM(i.quantity), 0) AS total_stock
        FROM phcs p
        LEFT JOIN inventory i ON p.phc_id = i.phc_id
        WHERE p.district IS NOT NULL AND p.district != ''
        GROUP BY p.district
        ORDER BY total_stock DESC, p.district ASC
    ";
} else {
    // Single district selected: group by PHC inside this district
    $query_stock = "
        SELECT p.phc_name AS group_name, COALESCE(SUM(i.quantity), 0) AS total_stock
        FROM phcs p
        LEFT JOIN inventory i ON p.phc_id = i.phc_id
        WHERE p.district = '$district_esc'
        GROUP BY p.phc_id, p.phc_name
        ORDER BY total_stock DESC, p.phc_name ASC
        LIMIT 10
    ";
}
$res_stock = $conn->query($query_stock);
if ($res_stock && $res_stock->num_rows > 0) {
    while ($row = $res_stock->fetch_assoc()) {
        $comparison_names[] = $row['group_name'];
        $comparison_stocks[] = (int)$row['total_stock'];
    }
}

if (empty($comparison_names)) {
    $comparison_names = ['No data'];
    $comparison_stocks = [0];
}

// ── 3. DETAILED PERFORMANCE SUMMARY TABLE ───────────────────────────────────
$query_table = "
    SELECT 
        da.district_admin_id,
        da.district,
        da.admin_name,
        da.status AS admin_status,
        -- Total PHCs under this district
        (SELECT COUNT(*) FROM phcs p WHERE p.district = da.district OR p.district_admin_id = da.district_admin_id) AS total_phcs,
        -- Total patient visits in this district in period
        (SELECT COUNT(*) FROM patient_visits pv 
         JOIN phcs p ON pv.phc_id = p.phc_id 
         WHERE (p.district = da.district OR p.district_admin_id = da.district_admin_id) {$date_cond_pv}) AS total_visits,
        -- Total medicine units dispensed in this district in period
        (SELECT COALESCE(SUM(vm.quantity_issued), 0) 
         FROM visit_medicines vm 
         JOIN patient_visits pv ON vm.visit_id = pv.visit_id 
         JOIN phcs p ON pv.phc_id = p.phc_id 
         WHERE (p.district = da.district OR p.district_admin_id = da.district_admin_id) {$date_cond_pv}) AS dispensed_units,
        -- Pending PHC creation proposals from this district
        (SELECT COUNT(*) FROM phc_creation_requests cr 
         WHERE cr.district = da.district AND cr.status = 'Pending Review') AS pending_proposals,
        -- Current stock in this district
        (SELECT COALESCE(SUM(i.quantity), 0) 
         FROM inventory i 
         JOIN phcs p ON i.phc_id = p.phc_id 
         WHERE (p.district = da.district OR p.district_admin_id = da.district_admin_id)) AS current_inventory
    FROM district_admin da
    {$dist_cond_da}
    ORDER BY da.district ASC
";
$res_table = $conn->query($query_table);

$table_html = '';
$kpi_districts = 0;
$kpi_phcs = 0;
$kpi_visits = 0;
$kpi_dispensed = 0;

if ($res_table && $res_table->num_rows > 0) {
    while ($row = $res_table->fetch_assoc()) {
        $kpi_districts++;
        $p_count = (int)$row['total_phcs'];
        $v_count = (int)$row['total_visits'];
        $d_count = (int)$row['dispensed_units'];
        $prop_count = (int)$row['pending_proposals'];

        $kpi_phcs += $p_count;
        $kpi_visits += $v_count;
        $kpi_dispensed += $d_count;

        $status_badge = ($row['admin_status'] === 'Active') 
            ? '<span class="badge" style="background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3);padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fas fa-check-circle"></i> Active</span>'
            : '<span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fas fa-ban"></i> Suspended</span>';

        $table_html .= '<tr>';
        $table_html .= '<td><strong>' . htmlspecialchars($row['district']) . '</strong></td>';
        $table_html .= '<td>' . htmlspecialchars($row['admin_name']) . '</td>';
        $table_html .= '<td><span style="font-weight:600;color:var(--accent-color);">' . number_format($p_count) . '</span> PHCs</td>';
        $table_html .= '<td><span style="font-weight:600;color:#0ea5e9;">' . number_format($v_count) . '</span> Visits</td>';
        $table_html .= '<td><span style="font-weight:600;color:#10b981;">' . number_format($d_count) . '</span> Units</td>';
        $table_html .= '<td>';
        if ($prop_count > 0) {
            $table_html .= '<span class="badge" style="background:#f59e0b;color:#fff;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:bold;">' . $prop_count . ' Under Review</span>';
        } else {
            $table_html .= '<span style="color:var(--text-secondary);font-size:12px;"><i class="fas fa-check"></i> 0 Pending</span>';
        }
        $table_html .= '</td>';
        $table_html .= '<td>' . $status_badge . '</td>';
        $table_html .= '</tr>';
    }
} else {
    $table_html = '<tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-secondary);"><i class="fas fa-folder-open" style="font-size:24px;margin-bottom:8px;display:block;"></i>No district records found matching the selected filter.</td></tr>';
}

echo json_encode([
    'success'           => true,
    'chart_labels'      => $chart_labels,
    'chart_consumed'    => $chart_consumed,
    'comparison_names'  => $comparison_names,
    'comparison_stocks' => $comparison_stocks,
    'table_html'        => $table_html,
    'period_label'      => $period_label,
    'chart2_title'      => $is_all_districts ? 'District-wise Stock Distribution' : 'PHC-wise Stock Level Comparison',
    'kpis'              => [
        'total_districts' => number_format($kpi_districts),
        'total_phcs'      => number_format($kpi_phcs),
        'total_visits'    => number_format($kpi_visits),
        'total_dispensed' => number_format($kpi_dispensed)
    ],
    'timestamp'         => date('h:i:s A')
]);
