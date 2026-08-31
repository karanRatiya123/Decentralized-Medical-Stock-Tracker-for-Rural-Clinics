// System Admin - PHC Document Review & Database Entry Script
let currentReqId = null;
let currentAction = null;

function openReviewModal(reqId, action) {
    currentReqId = reqId;
    currentAction = action;
    
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const modal = document.getElementById('reviewModal');
    const dbFields = document.getElementById('phcDatabaseFields');

    if (modalTitle) modalTitle.textContent = action === 'Approve' ? 'Verify Document & Register PHC' : `Reject Proposal (${reqId})`;
    
    if (submitBtn) {
        submitBtn.textContent = action === 'Approve' ? 'Confirm Approval & Insert into Database' : 'Confirm Rejection';
        submitBtn.style.backgroundColor = action === 'Approve' ? '#10b981' : '#ef4444';
    }
    
    // Show database fields only when approving, hide when rejecting
    if (dbFields) {
        dbFields.style.display = action === 'Approve' ? 'block' : 'none';
        
        // Make inputs required only if approving
        const inputs = dbFields.querySelectorAll('input');
        inputs.forEach(input => {
            input.required = action === 'Approve';
        });
    }

    if (modal) modal.classList.add('active');
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    const form = document.getElementById('reviewForm');
    if (modal) modal.classList.remove('active');
    if (form) form.reset();
}

document.addEventListener("DOMContentLoaded", () => {
    const closeModalBtn = document.getElementById('closeModalBtn');
    const reviewForm = document.getElementById('reviewForm');

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
            closeReviewModal();
            
            const banner = document.getElementById('sysActionBanner');
            const bannerText = document.getElementById('sysActionBannerText');
            
            if (banner && bannerText) {
                if (currentAction === 'Approve') {
                    bannerText.textContent = `Proposal ${currentReqId} approved! New PHC successfully added to the master database.`;
                } else {
                    bannerText.textContent = `Proposal ${currentReqId} has been rejected.`;
                }
                banner.style.display = 'flex';
                setTimeout(() => {
                    banner.style.display = 'none';
                }, 4000);
            }
        });
    }
});