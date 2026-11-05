// ==========================================================================
// MedStock Tracker - District Inventory & Central Warehouse Controller
// Location: js/district_inventory.js
// ==========================================================================

document.addEventListener("DOMContentLoaded", () => {
    initTabNavigation();
    initWarehouseFilters();
    initModalEvents();
    initDispatchValidation();
});

// ---------- 1. TAB SWITCHING SYSTEM ----------
function switchTab(tabName) {
    const allButtons = document.querySelectorAll('.tab-btn');
    const allPanes   = document.querySelectorAll('.tab-pane');

    allButtons.forEach(btn => btn.classList.remove('active'));
    allPanes.forEach(pane => pane.classList.remove('active'));

    const activeBtn  = document.getElementById('btnTab' + capitalize(tabName));
    const activePane = document.getElementById('pane-' + tabName);

    if (activeBtn) activeBtn.classList.add('active');
    if (activePane) activePane.classList.add('active');
}

function capitalize(str) {
    if (str === 'inward_logs') return 'InwardLogs';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function initTabNavigation() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam  = urlParams.get('tab');
    if (tabParam && ['warehouse', 'clinics', 'inward_logs'].includes(tabParam)) {
        switchTab(tabParam);
    }
}

// ---------- 2. WAREHOUSE LIVE SEARCH & FILTER ----------
function initWarehouseFilters() {
    const searchInput = document.getElementById('warehouseSearchInput');
    const categorySelect = document.getElementById('warehouseCategoryFilter');

    if (searchInput) {
        searchInput.addEventListener('input', filterWarehouseTable);
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', filterWarehouseTable);
    }
}

function filterWarehouseTable() {
    const query = (document.getElementById('warehouseSearchInput')?.value || '').toLowerCase().trim();
    const category = (document.getElementById('warehouseCategoryFilter')?.value || '').toLowerCase().trim();
    const table = document.getElementById('warehouseTable');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const rowText = row.innerText.toLowerCase();
        const matchesQuery = query === '' || rowText.includes(query);
        const matchesCat   = category === '' || rowText.includes(category);

        if (matchesQuery && matchesCat) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// ---------- 3. MODALS MANAGEMENT ----------
function openInwardModal() {
    const modal = document.getElementById('inwardModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeInwardModal() {
    const modal = document.getElementById('inwardModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openDispatchModal() {
    const modal = document.getElementById('dispatchModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeDispatchModal() {
    const modal = document.getElementById('dispatchModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function prefillInward(medId, medName) {
    const select = document.getElementById('inwardMedicineSelect');
    if (select) select.value = medId;
    openInwardModal();
}

function prefillDispatch(medId, medName, availQty) {
    const select = document.getElementById('dispatchMedicineSelect');
    if (select) {
        select.value = medId;
        updateAvailableIndicator();
    }
    openDispatchModal();
}

function prefillDispatchForPhc(phcId, medId, medName) {
    const phcSelect = document.getElementById('dispatchPhcSelect');
    const medSelect = document.getElementById('dispatchMedicineSelect');
    if (phcSelect) phcSelect.value = phcId;
    if (medSelect) {
        medSelect.value = medId;
        updateAvailableIndicator();
    }
    openDispatchModal();
}

function updateAvailableIndicator() {
    const select = document.getElementById('dispatchMedicineSelect');
    const notice = document.getElementById('availStockNotice');
    const numSpan = document.getElementById('availStockNum');
    const qtyInput = document.getElementById('dispatchQty');
    if (!select || !notice) return;

    const opt = select.options[select.selectedIndex];
    const avail = opt ? opt.getAttribute('data-avail') : null;

    if (avail !== null && avail !== '') {
        const num = parseInt(avail, 10);
        numSpan.innerText = num.toLocaleString();
        notice.style.display = 'block';
        if (qtyInput) qtyInput.max = num;
    } else {
        notice.style.display = 'none';
        if (qtyInput) qtyInput.removeAttribute('max');
    }
}

function initModalEvents() {
    // Escape key listener
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeInwardModal();
            closeDispatchModal();
        }
    });

    // Backdrop click listener
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeInwardModal();
                closeDispatchModal();
            }
        });
    });
}

function initDispatchValidation() {
    const dispatchForm = document.getElementById('dispatchForm');
    if (dispatchForm) {
        dispatchForm.addEventListener('submit', function(e) {
            const select = document.getElementById('dispatchMedicineSelect');
            const qtyInput = document.getElementById('dispatchQty');
            if (select && qtyInput) {
                const opt = select.options[select.selectedIndex];
                const avail = parseInt(opt ? opt.getAttribute('data-avail') : 0, 10) || 0;
                const entered = parseInt(qtyInput.value, 10) || 0;
                if (entered > avail) {
                    e.preventDefault();
                    showToast(`Cannot dispatch ${entered.toLocaleString()} units! Only ${avail.toLocaleString()} units available in District Warehouse.`, 'error');
                    qtyInput.focus();
                    return false;
                }
            }
        });
    }
}

// ---------- 4. INTERACTIVE ACTIONS WITH VISUAL FEEDBACK ----------
function handleInwardSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const medSelect = document.getElementById('inwardMedicineSelect');
    const medName = medSelect.options[medSelect.selectedIndex].text.split('(')[0].trim();
    const qty = document.getElementById('inwardQty').value;
    const batch = document.getElementById('inwardBatch').value;
    const expiry = document.getElementById('inwardExpiry').value;
    const supplier = form.querySelector('[name="supplier"]')?.value || 'State Medical Depot';

    // 1. Show interactive Toast feedback
    showToast(`Inward Received: +${Number(qty).toLocaleString()} units of ${medName} (Batch: ${batch}) recorded into District Warehouse!`, 'success');

    // 2. Prepend entry to Inward History Table (Tab 3)
    const logsTable = document.querySelector('#pane-inward_logs table tbody');
    if (logsTable) {
        const emptyRow = logsTable.querySelector('td[colspan]');
        if (emptyRow) emptyRow.closest('tr').remove();

        const receiptId = Math.floor(1000 + Math.random() * 9000);
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><strong style="color: var(--accent-color);">#INW-${receiptId}</strong></td>
            <td><strong>${escapeHtml(medName)}</strong></td>
            <td><span style="font-weight: 700; color: #10b981; font-size: 14px;">+${Number(qty).toLocaleString()} Units</span></td>
            <td><code>${escapeHtml(batch)}</code></td>
            <td><span style="font-size: 12px; color: var(--text-secondary);"><i class="far fa-calendar-alt"></i> ${escapeHtml(expiry)}</span></td>
            <td>${escapeHtml(supplier)}</td>
            <td><small style="color: var(--text-secondary);">Just now</small></td>
        `;
        logsTable.insertBefore(newRow, logsTable.firstChild);

        // Update Tab 3 badge count
        const badge = document.querySelector('#btnTabInwardLogs .tab-badge');
        if (badge) {
            const count = logsTable.querySelectorAll('tr').length;
            badge.innerText = count + ' batches';
        }
    }

    // 3. Dynamically increment KPI Total Reserve counter
    const kpiReserve = document.getElementById('kpiTotalReserve');
    if (kpiReserve) {
        const currentTotal = parseInt(kpiReserve.innerText.replace(/,/g, ''), 10) || 0;
        kpiReserve.innerText = (currentTotal + parseInt(qty, 10)).toLocaleString();
    }

    // 3. Reset & close
    form.reset();
    closeInwardModal();
}

function handleDispatchSubmit(e) {
    e.preventDefault();
    const phcSelect = document.getElementById('dispatchPhcSelect');
    const phcName   = phcSelect.options[phcSelect.selectedIndex].text.split('(')[0].trim();
    const medSelect = document.getElementById('dispatchMedicineSelect');
    const medName   = medSelect.options[medSelect.selectedIndex].text.split('(')[0].trim();
    const qty       = document.getElementById('dispatchQty').value;

    // Show interactive Toast feedback
    showToast(`Stock Dispatched: ${Number(qty).toLocaleString()} units of ${medName} dispatched to ${phcName}! District and PHC inventory synchronized.`, 'success');

    e.target.reset();
    closeDispatchModal();
}

// ---------- 5. TOAST NOTIFICATION ENGINE ----------
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-box toast-${type}`;
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    const titleText = type === 'success' ? 'Stock Action Executed' : 'Action Notification';

    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${iconClass}"></i></div>
        <div class="toast-text">
            <h5>${titleText}</h5>
            <p>${escapeHtml(message)}</p>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-fadeout');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
