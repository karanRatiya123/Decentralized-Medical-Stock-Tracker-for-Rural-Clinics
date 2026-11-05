// ==========================================================================
// MedStock Tracker - District Medicine Requisitions Controller
// Location: js/district_medicine_requests.js
// ==========================================================================

document.addEventListener("DOMContentLoaded", () => {
    initProcessModalEvents();
});

let activeProcessingRow = null;

function openProcessModal(reqId, phcName, medName, reqQty, distStock, rowElem) {
    activeProcessingRow = rowElem || null;

    const modalReqId = document.getElementById('modalReqId');
    const modalReqDisplay = document.getElementById('modalReqDisplay');
    const modalPhcName = document.getElementById('modalPhcName');
    const modalMedName = document.getElementById('modalMedName');
    const modalReqQty = document.getElementById('modalReqQty');
    const modalDistStock = document.getElementById('modalDistStock');
    const supplyQtyInput = document.getElementById('supplyQtyInput');
    const modal = document.getElementById('processModal');

    if (modalReqId) modalReqId.value = reqId;
    if (modalReqDisplay) modalReqDisplay.innerText = '#REQ-' + String(reqId).padStart(4, '0');
    if (modalPhcName) modalPhcName.innerText = phcName;
    if (modalMedName) modalMedName.innerText = medName;
    if (modalReqQty) modalReqQty.innerText = Number(reqQty).toLocaleString() + ' Units';

    if (modalDistStock) {
        if (distStock >= reqQty) {
            modalDistStock.innerHTML = `<span style="color: #10b981;"><i class="fas fa-check-circle"></i> ${Number(distStock).toLocaleString()} Units (Sufficient)</span>`;
        } else {
            modalDistStock.innerHTML = `<span style="color: #ef4444;"><i class="fas fa-triangle-exclamation"></i> Only ${Number(distStock).toLocaleString()} in Warehouse (Shortage)</span>`;
        }
    }

    if (supplyQtyInput) {
        supplyQtyInput.value = reqQty;
        supplyQtyInput.max = distStock > 0 ? distStock : 999999;
    }

    // Reset decision select to default 'Approved'
    const decisionStatus = document.getElementById('decisionStatus');
    if (decisionStatus) {
        decisionStatus.value = 'Approved';
        toggleSupplyOptions();
    }

    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeProcessModal() {
    const modal = document.getElementById('processModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function toggleSupplyOptions() {
    const decision = document.getElementById('decisionStatus')?.value;
    const supplyBox = document.getElementById('supplyConfigBox');
    const btnSubmit = document.getElementById('btnSubmitDecision');

    if (!supplyBox || !btnSubmit) return;

    if (decision === 'Approved') {
        supplyBox.style.display = 'block';
        btnSubmit.className = 'btn-form-submit';
        btnSubmit.innerHTML = '<i class="fas fa-check"></i><span>Confirm & Execute Dispatch</span>';
    } else {
        supplyBox.style.display = 'none';
        btnSubmit.className = 'btn-form-submit btn-reject';
        btnSubmit.innerHTML = '<i class="fas fa-times"></i><span>Confirm Rejection</span>';
    }
}

function initProcessModalEvents() {
    // Escape key listener
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeProcessModal();
    });

    // Backdrop click listener
    const modal = document.getElementById('processModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeProcessModal();
        });
    }

    // Attach interactive preview handler to modal form
    const form = document.querySelector('#processModal form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const decision = document.getElementById('decisionStatus')?.value || 'Approved';
            const reqDisplay = document.getElementById('modalReqDisplay')?.innerText || '#REQ';
            const medName = document.getElementById('modalMedName')?.innerText || 'Medicine';
            const phcName = document.getElementById('modalPhcName')?.innerText || 'PHC';
            const supplyQty = document.getElementById('supplyQtyInput')?.value || '0';

            if (decision === 'Approved') {
                showToast(`Requisition ${reqDisplay} Approved! ${Number(supplyQty).toLocaleString()} units of ${medName} dispatched to ${phcName}. District & PHC inventories synchronized.`, 'success');
            } else {
                showToast(`Requisition ${reqDisplay} has been Rejected.`, 'error');
            }

            // If we have row reference, visually update status
            if (activeProcessingRow) {
                const statusCell = activeProcessingRow.querySelector('.badge-status');
                const actionCell = activeProcessingRow.querySelector('td:last-child');
                if (statusCell) {
                    if (decision === 'Approved') {
                        statusCell.className = 'badge-status badge-approved';
                        statusCell.innerText = 'Approved';
                    } else {
                        statusCell.className = 'badge-status badge-rejected';
                        statusCell.innerText = 'Rejected';
                    }
                }
                if (actionCell) {
                    actionCell.innerHTML = `<span style="color: var(--text-secondary); font-size: 12px;"><i class="fas fa-check-double" style="color: #10b981;"></i> Fulfilled</span>`;
                }
            }

            // Note: form also continues normal submit to update database or can stay on page for testing
        });
    }
}

// Toast notification function
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
    const titleText = type === 'success' ? 'Requisition Action Executed' : 'Requisition Action';

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
