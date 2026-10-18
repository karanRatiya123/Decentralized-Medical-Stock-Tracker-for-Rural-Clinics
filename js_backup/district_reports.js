// District Admin - Reports & Charts (Fully AJAX-Driven)

let consumptionChart = null;
let phcComparisonChart = null;

const COLORS = ['#0ea5e9','#10b981','#8b5cf6','#f59e0b','#ef4444','#ec4899','#14b8a6','#f97316'];

// ── Initialize charts with empty data ─────────────────────────────────────────
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
                backgroundColor: 'rgba(14,165,233,0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: '#94a3b8', font: { size: 11 } } } },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.3)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.3)' } }
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
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51,65,85,0.3)' } }
            }
        }
    });
}

// ── Fetch data from AJAX endpoint and update everything ───────────────────────
function loadReport() {
    const phcId  = document.getElementById('reportPhcSelect').value;
    const period = document.getElementById('reportPeriodSelect').value;

    // Show loading spinner in table
    document.getElementById('reportTableBody').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-secondary);"><i class="fas fa-sync-alt fa-spin"></i> Generating report...</td></tr>';

    // Spin the Generate button icon
    const btn = document.getElementById('generateReportBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    fetch(`get_report_data.php?phc_id=${phcId}&period=${period}`)
        .then(res => res.json())
        .then(data => {
            // ── Update Chart 1: Consumption ──────────────────────────────────
            consumptionChart.data.labels   = data.chart_labels;
            consumptionChart.data.datasets[0].data = data.chart_consumed;
            consumptionChart.update();

            // ── Update Chart 2: PHC Stock ────────────────────────────────────
            phcComparisonChart.data.labels = data.phc_names;
            phcComparisonChart.data.datasets[0].data = data.phc_stocks;
            // Assign colors dynamically based on number of PHCs
            phcComparisonChart.data.datasets[0].backgroundColor =
                data.phc_names.map((_, i) => COLORS[i % COLORS.length]);
            phcComparisonChart.update();

            // ── Update Table ─────────────────────────────────────────────────
            document.getElementById('reportTableBody').innerHTML = data.table_html;

            // ── Update subtitle ──────────────────────────────────────────────
            const phcLabel = document.getElementById('reportPhcSelect').selectedOptions[0].text;
            document.getElementById('tableReportTitle').textContent =
                `Performance Summary — ${phcLabel} (${data.period_label})`;
        })
        .catch(err => {
            document.getElementById('reportTableBody').innerHTML =
                '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Failed to load report. Please try again.</td></tr>';
            console.error('Report fetch error:', err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Generate Report';
        });
}

// ── PDF Export / Print ────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
    initCharts();

    // Load default report on page open
    loadReport();

    // Generate Report button
    document.getElementById('generateReportBtn').addEventListener('click', loadReport);

    // PDF Export
    const exportBtn = document.getElementById('exportPdfBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => window.print());
    }
});