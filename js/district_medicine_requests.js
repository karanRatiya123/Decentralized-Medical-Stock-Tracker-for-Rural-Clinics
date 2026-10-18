// District Admin - Medicine Requests Action Script
let currentReqId = null;
let currentAction = null;

function openActionModal(reqId, action) {
    currentReqId = reqId;
    currentAction = action;
    
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const modal = document.getElementById('actionModal');

    if (modalTitle) modalTitle.textContent = `${action} Request (${reqId})`;
    if (submitBtn) {
        submitBtn.textContent = `Confirm ${action}`;
        submitBtn.style.backgroundColor = action === 'Approve' ? '#1E6B3A' : '#A32929';
    }
    if (modal) modal.classList.add('active');
}

function closeActionModal() {
    const modal = document.getElementById('actionModal');
    const form = document.getElementById('actionForm');
    if (modal) modal.classList.remove('active');
    if (form) form.reset();
}

document.addEventListener("DOMContentLoaded", () => {
    const closeModalBtn = document.getElementById('closeModalBtn');
    const actionForm = document.getElementById('actionForm');

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeActionModal);
    }

    window.addEventListener('click', (e) => {
        const modal = document.getElementById('actionModal');
        if (e.target === modal) {
            closeActionModal();
        }
    });

    if (actionForm) {
        actionForm.addEventListener('submit', (e) => {
            e.preventDefault();
            closeActionModal();
            
            const banner = document.getElementById('adminActionBanner');
            const bannerText = document.getElementById('actionBannerText');
            
            if (banner && bannerText) {
                bannerText.textContent = `Request ${currentReqId} has been ${currentAction.toLowerCase()}d successfully.`;
                banner.style.display = 'flex';
                setTimeout(() => {
                    banner.style.display = 'none';
                }, 4000);
            }
        });
    }
});