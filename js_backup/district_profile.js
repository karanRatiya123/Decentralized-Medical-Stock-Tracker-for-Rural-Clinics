// District Admin - Profile Form Submission Script (Database AJAX Backed)
document.addEventListener("DOMContentLoaded", () => {
    const profileForm   = document.getElementById("adminProfileForm");
    const successBanner = document.getElementById("profileSuccessBanner");
    const errorBanner   = document.getElementById("profileErrorBanner");
    const successText   = document.getElementById("profileBannerText");
    const errorText     = document.getElementById("profileErrorText");
    const submitBtn     = document.getElementById("saveProfileBtn");

    if (profileForm) {
        profileForm.addEventListener("submit", (e) => {
            e.preventDefault();

            // Hide any active banners
            if (successBanner) successBanner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            // Button loading state
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Changes...';

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

                    // Update UI names live
                    const cardName = document.getElementById("cardAdminName");
                    const sideName = document.getElementById("sidebarAdminName");
                    if (cardName && data.admin_name) cardName.textContent = data.admin_name;
                    if (sideName && data.admin_name) sideName.textContent = data.admin_name;

                    // Clear password fields for security
                    const currentPass = document.getElementById("current_password");
                    const newPass     = document.getElementById("new_password");
                    if (currentPass) currentPass.value = "";
                    if (newPass) newPass.value = "";

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
                console.error("Profile update error:", err);
                if (errorText) errorText.textContent = "An unexpected network or server error occurred.";
                if (errorBanner) {
                    errorBanner.style.display = "flex";
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
});