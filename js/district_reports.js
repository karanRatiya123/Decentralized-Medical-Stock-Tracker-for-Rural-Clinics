// District Admin - Reports & Charts (Real-Time AJAX & Live Sync)

let consumptionChart = null;
let phcComparisonChart = null;
let isFetching = false;
let autoRefreshTimer = null;

const COLORS = ['#0ea5e9','#10b981','#8b5cf6','#f59e0b','#ef4444','#ec4899','#14b8a6','#f97316'];

// ── Initialize charts with standard styles ─────────────────────────────────────
function initCharts() {
    const ctx1 = document.getElementById('consumptionChart').getContext('2d');
    consumptionChart = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Units Dispensed',
                data: [],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14,165,233,0.12)',
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

    const ctx2 = document.getElementById('phcComparisonChart').getContext('2d');
    phcComparisonChart = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Available Stock Units',
                data: [],
                backgroundColor: COLORS,
                borderWidth: 0,
                borderRadius: 5,
                maxBarThickness: 36
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

    const phcSelect = document.getElementById('reportPhcSelect');
    const periodSelect = document.getElementById('reportPeriodSelect');
    const categorySelect = document.getElementById('reportCategorySelect');

    const phcId    = phcSelect ? phcSelect.value : '0';
    const period   = periodSelect ? periodSelect.value : 'month';
    const category = categorySelect ? categorySelect.value : 'overview';

    const btn = document.getElementById('generateReportBtn');
    if (!isSilent && btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        document.getElementById('reportTableBody').innerHTML =
            '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-secondary);"><i class="fas fa-sync-alt fa-spin"></i> Generating real-time report...</td></tr>';
    }

    fetch(`get_report_data.php?phc_id=${encodeURIComponent(phcId)}&period=${encodeURIComponent(period)}&category=${encodeURIComponent(category)}`)
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

            // 2. Update Chart 2: PHC Stock or Medicine distribution
            if (phcComparisonChart) {
                phcComparisonChart.data.labels = data.phc_names || [];
                phcComparisonChart.data.datasets[0].data = data.phc_stocks || [];
                phcComparisonChart.data.datasets[0].backgroundColor =
                    (data.phc_names || []).map((_, i) => COLORS[i % COLORS.length]);
                phcComparisonChart.update(isSilent ? 'none' : 'default');
            }

            // 3. Update Table
            const tbody = document.getElementById('reportTableBody');
            if (tbody && data.table_html) {
                tbody.innerHTML = data.table_html;
            }

            // 4. Update KPI Metrics if present
            if (data.kpis) {
                const kv = document.getElementById('kpiVisits');
                const kd = document.getElementById('kpiDispensed');
                const kb = document.getElementById('kpiBatches');
                const kr = document.getElementById('kpiRequests');
                if (kv) kv.textContent = data.kpis.total_visits;
                if (kd) kd.textContent = data.kpis.total_dispensed;
                if (kb) kb.textContent = data.kpis.stock_batches;
                if (kr) kr.textContent = data.kpis.pending_requests;
            }

            // 5. Update Headings & Live timestamp
            const phcLabel = phcSelect ? phcSelect.selectedOptions[0].text : 'All PHCs';
            const tableTitle = document.getElementById('tableReportTitle');
            if (tableTitle) {
                tableTitle.textContent = `Performance Summary — ${phcLabel} (${data.period_label})`;
            }

            const updatedTime = document.getElementById('lastUpdatedTime');
            if (updatedTime) {
                updatedTime.textContent = `(Synced: ${data.timestamp || new Date().toLocaleTimeString()})`;
            }
        })
        .catch(err => {
            console.error('Report fetch error:', err);
            if (!isSilent) {
                const tbody = document.getElementById('reportTableBody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Failed to load live report. Please check database connection.</td></tr>';
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

    const phcSelect = document.getElementById('reportPhcSelect');
    if (phcSelect) {
        phcSelect.addEventListener('change', () => loadReport(false));
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