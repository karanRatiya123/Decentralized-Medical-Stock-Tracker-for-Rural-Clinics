// District Admin - Reports & Charts Initialization
document.addEventListener("DOMContentLoaded", () => {
    // 1. Initialize Chart 1: Monthly Consumption Trends (Line Chart)
    const ctx1 = document.getElementById('consumptionChart').getContext('2d');
    const consumptionChart = new Chart(ctx1, {
        type: 'line',
        data: {
            labels: ['Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
            datasets: [{
                label: 'Units Dispensed',
                data: [9200, 10500, 11800, 12400, 11100, 13350],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#94a3b8', font: { size: 11 } } }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51, 65, 85, 0.3)' } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51, 65, 85, 0.3)' } }
            }
        }
    });

    // 2. Initialize Chart 2: PHC-wise Stock Level Comparison (Bar Chart)
    const ctx2 = document.getElementById('phcComparisonChart').getContext('2d');
    const phcComparisonChart = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: ['PHC Veraval', 'PHC Sutrapada', 'PHC Kodinar', 'PHC Una'],
            datasets: [{
                label: 'Available Stock Units',
                data: [4200, 3100, 5400, 3800],
                backgroundColor: ['#0ea5e9', '#10b981', '#8b5cf6', '#f59e0b'],
                borderWidth: 0,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51, 65, 85, 0.3)' } }
            }
        }
    });

    // 3. PDF Export / Print Handler
    const exportBtn = document.getElementById('exportPdfBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            window.print();
        });
    }

    // 4. Generate Report Button Interactive Feed
    const generateBtn = document.getElementById('generateReportBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', () => {
            const originalText = generateBtn.innerHTML;
            generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            setTimeout(() => {
                generateBtn.innerHTML = originalText;
                alert("Report successfully filtered and updated with latest district data!");
            }, 600);
        });
    }
});