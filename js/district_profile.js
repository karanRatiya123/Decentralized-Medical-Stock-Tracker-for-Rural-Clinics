// District Admin - Profile Form Submission Script
document.addEventListener("DOMContentLoaded", () => {
    const profileForm = document.getElementById("adminProfileForm");
    const successBanner = document.getElementById("profileSuccessBanner");

    if (profileForm) {
        profileForm.addEventListener("submit", (e) => {
            e.preventDefault();
            
            // Show success notification banner
            if (successBanner) {
                successBanner.style.display = "flex";
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }
});