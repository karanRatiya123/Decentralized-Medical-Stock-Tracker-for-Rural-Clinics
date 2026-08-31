document.addEventListener("DOMContentLoaded", () => {
    const profileForm = document.getElementById("profileForm");
    const successBanner = document.getElementById("profileSuccessBanner");

    if (profileForm) {
        profileForm.addEventListener("submit", (e) => {
            e.preventDefault();

            if (successBanner) {
                successBanner.style.display = "flex";
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }
});