// System Master Admin - Statewide Reports & Charts (Real-Time AJAX & Live Sync)

let consumptionChart = null;
let stockDistributionChart = null;
let isFetching = false;
let autoRefreshTimer = null;

const COLORS = ['#8b5cf6','#0ea5e9','#10b981','#f59e0b','#ec4899','#14b8a6','#f97316','#6366f1'];

// ── Initialize charts ──────────────────────────────────────────────────────────
function initCharts() {
    const ctx1 = document.getElementById('consumptionChart').getContext('2d');
    consumptionChart = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Units Dispensed',
                data: [],
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.12)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#94a3b8', font: { size: 11, weight: '600' } } },
                tooltip: { padding: 10, cornerRadius: 6 }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.25)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.25)' }, beginAtZero: true }
            }
        }
    });

    const ctx2 = document.getElementById('stockDistributionChart').getContext('2d');
    stockDistributionChart = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Stock Quantity',
                data: [],
                backgroundColor: COLORS,
                borderWidth: 0,
                borderRadius: 5,
                maxBarThickness: 38
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { padding: 10, cornerRadius: 6 }
            },
            scales: {
                x: { ticks: { color: '#94a3b8', font: { size: 11 } }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.25)' }, beginAtZero: true }
            }
        }
    });
}

// ── Fetch data from PHP AJAX endpoint and update visuals ───────────────────────
function loadReport(isSilent = false) {
    if (isFetching) return;
    isFetching = true;

    const districtSelect = document.getElementById('reportDistrictSelect');
    const periodSelect = document.getElementById('reportPeriodSelect');
    const categorySelect = document.getElementById('reportCategorySelect');

    const district = districtSelect ? districtSelect.value : 'all';
    const period   = periodSelect ? periodSelect.value : 'month';
    const category = categorySelect ? categorySelect.value : 'overview';

    const btn = document.getElementById('generateReportBtn');
    if (!isSilent && btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        document.getElementById('reportTableBody').innerHTML =
            '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-secondary);"><i class="fas fa-sync-alt fa-spin"></i> Loading analytics data...</td></tr>';
    }

    fetch(`get_report_data.php?district=${encodeURIComponent(district)}&period=${encodeURIComponent(period)}&category=${encodeURIComponent(category)}`)
        .then(res => {
            if (!res.ok) throw new Error('HTTP error ' + res.status);
            return res.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Server error');

            // 1. Update Chart 1: Consumption Trend
            if (consumptionChart) {
                consumptionChart.data.labels = data.chart_labels || [];
                consumptionChart.data.datasets[0].data = data.chart_consumed || [];
                consumptionChart.update(isSilent ? 'none' : 'default');
            }

            // 2. Update Chart 2: Stock Distribution
            if (stockDistributionChart) {
                stockDistributionChart.data.labels = data.comparison_names || [];
                stockDistributionChart.data.datasets[0].data = data.comparison_stocks || [];
                stockDistributionChart.data.datasets[0].backgroundColor =
                    (data.comparison_names || []).map((_, i) => COLORS[i % COLORS.length]);
                stockDistributionChart.update(isSilent ? 'none' : 'default');
            }

            // 3. Update Chart 2 Title
            const chart2Title = document.getElementById('chart2Title');
            if (chart2Title && data.chart2_title) {
                chart2Title.textContent = data.chart2_title;
            }

            // 4. Update Table
            const tbody = document.getElementById('reportTableBody');
            if (tbody && data.table_html) {
                tbody.innerHTML = data.table_html;
            }

            // 5. Update KPI Cards
            if (data.kpis) {
                const kd = document.getElementById('kpiDistricts');
                const kp = document.getElementById('kpiPhcs');
                const kv = document.getElementById('kpiVisits');
                const ku = document.getElementById('kpiDispensed');
                if (kd) kd.textContent = data.kpis.total_districts;
                if (kp) kp.textContent = data.kpis.total_phcs;
                if (kv) kv.textContent = data.kpis.total_visits;
                if (ku) ku.textContent = data.kpis.total_dispensed;
            }

            // 6. Update Headings & Live timestamp
            const districtLabel = districtSelect ? districtSelect.selectedOptions[0].text : 'All Districts';
            const tableTitle = document.getElementById('tableReportTitle');
            if (tableTitle) {
                tableTitle.textContent = `Performance Overview — ${districtLabel} (${data.period_label})`;
            }

            const updatedTime = document.getElementById('lastUpdatedTime');
            if (updatedTime) {
                updatedTime.textContent = `(Synced: ${data.timestamp || new Date().toLocaleTimeString()})`;
            }
        })
        .catch(err => {
            console.error('System report fetch error:', err);
            if (!isSilent) {
                const tbody = document.getElementById('reportTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:2rem;color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Failed to load live report. Please check database connection.</td></tr>';
                }
            }
        })
        .finally(() => {
            isFetching = false;
            if (!isSilent && btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Generate Report';
            }
        });
}

// ── Initialize on page load & setup real-time background sync ─────────────────
document.addEventListener("DOMContentLoaded", () => {
    initCharts();

    // Initial report load
    loadReport(false);

    // Event listeners for filter triggers
    const generateBtn = document.getElementById('generateReportBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', () => loadReport(false));
    }

    const districtSelect = document.getElementById('reportDistrictSelect');
    if (districtSelect) {
        districtSelect.addEventListener('change', () => loadReport(false));
    }

    const periodSelect = document.getElementById('reportPeriodSelect');
    if (periodSelect) {
        periodSelect.addEventListener('change', () => loadReport(false));
    }

    const categorySelect = document.getElementById('reportCategorySelect');
    if (categorySelect) {
        categorySelect.addEventListener('change', () => loadReport(false));
    }

    // PDF Export / Print
    const exportBtn = document.getElementById('exportPdfBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => window.print());
    }

    // ── REAL-TIME BACKGROUND SYNC ─────────────────────────────────────────────
    // Polls database silently every 20 seconds to keep visuals up to date as new data is entered
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(() => {
        if (!document.hidden) {
            loadReport(true); // silent background refresh
        }
    }, 20000);
});
