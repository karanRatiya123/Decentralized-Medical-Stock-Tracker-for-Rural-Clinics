// District Admin - Profile Form Submission Script
document.addEventListener("DOMContentLoaded", () => {
    const profileForm   = document.getElementById("adminProfileForm");
    const successBanner = document.getElementById("profileSuccessBanner");
    const successText   = document.getElementById("profileBannerText");
    const errorBanner   = document.getElementById("profileErrorBanner");
    const errorText     = document.getElementById("profileErrorText");
    const submitBtn     = document.getElementById("saveProfileBtn");

    if (profileForm) {
        profileForm.addEventListener("submit", (e) => {
            e.preventDefault();

            if (successBanner) successBanner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            const origText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }

            const formData = new FormData(profileForm);

            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (successText) successText.textContent = data.message;
                    if (successBanner) {
                        successBanner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }

                    // Update dynamic names in UI
                    const cardName = document.getElementById("cardAdminName");
                    const sideName = document.getElementById("sidebarAdminName");
                    if (cardName && data.admin_name) cardName.textContent = data.admin_name;
                    if (sideName && data.admin_name) sideName.textContent = data.admin_name;

                    setTimeout(() => {
                        if (successBanner) successBanner.style.display = "none";
                    }, 4000);
                } else {
                    if (errorText) errorText.textContent = data.message;
                    if (errorBanner) {
                        errorBanner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            })
            .catch(err => {
                console.error("Save district profile error:", err);
                if (errorText) errorText.textContent = "A server error occurred while updating profile.";
                if (errorBanner) {
                    errorBanner.style.display = "flex";
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
            });
        });
    }
});