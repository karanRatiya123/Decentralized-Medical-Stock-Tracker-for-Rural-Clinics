// System Admin - PHC Document Review & Database Entry Script (AJAX Backed)
let currentReqId = null;
let currentAction = null;

function openReviewModal(reqId, action, codeId) {
    currentReqId = reqId;
    currentAction = action;
    
    document.getElementById('modal_request_id').value = reqId;
    document.getElementById('modal_action').value = action;

    const modalTitle = document.getElementById('modalTitle');
    const submitBtn  = document.getElementById('modalSubmitBtn');
    const modal      = document.getElementById('reviewModal');
    const dbFields   = document.getElementById('phcDatabaseFields');

    const displayCode = codeId || (`REQ-P` + String(reqId).padStart(2, '0'));

    if (modalTitle) {
        modalTitle.textContent = (action === 'Approve') 
            ? `Verify Document & Register PHC (${displayCode})` 
            : `Reject Proposal (${displayCode})`;
    }
    
    if (submitBtn) {
        submitBtn.textContent = (action === 'Approve') 
            ? 'Confirm Approval & Register into Database' 
            : 'Confirm Rejection';
        submitBtn.style.backgroundColor = (action === 'Approve') ? '#10b981' : '#ef4444';
    }
    
    // Show database fields only when approving
    if (dbFields) {
        dbFields.style.display = (action === 'Approve') ? 'block' : 'none';
        
        const inputs = dbFields.querySelectorAll('input');
        inputs.forEach(input => {
            input.required = (action === 'Approve' && input.id !== 'db_contact' && input.id !== 'db_address');
        });
    }

    if (modal) modal.classList.add('active');
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    const form  = document.getElementById('reviewForm');
    if (modal) modal.classList.remove('active');
    if (form) form.reset();
}

document.addEventListener("DOMContentLoaded", () => {
    const closeModalBtn = document.getElementById('closeModalBtn');
    const reviewForm    = document.getElementById('reviewForm');
    const submitBtn     = document.getElementById('modalSubmitBtn');
    const banner        = document.getElementById('sysActionBanner');
    const bannerText    = document.getElementById('sysActionBannerText');
    const errorBanner   = document.getElementById('sysErrorBanner');
    const errorText     = document.getElementById('sysErrorBannerText');

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

            if (banner) banner.style.display = 'none';
            if (errorBanner) errorBanner.style.display = 'none';

            const origText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(reviewForm);

            fetch('phc_requests.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                closeReviewModal();
                if (data.success) {
                    if (bannerText) bannerText.textContent = data.message;
                    if (banner) {
                        banner.style.display = 'flex';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1400);
                } else {
                    if (errorText) errorText.textContent = data.message;
                    if (errorBanner) {
                        errorBanner.style.display = 'flex';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            })
            .catch(err => {
                console.error("Proposal review error:", err);
                closeReviewModal();
                if (errorText) errorText.textContent = "A server error occurred while processing the proposal.";
                if (errorBanner) {
                    errorBanner.style.display = 'flex';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            });
        });
    }
});