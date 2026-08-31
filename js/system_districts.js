// System Admin - Districts & Admins Script
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openDistrictModalBtn");
    const closeModalBtn = document.getElementById("closeDistrictModalBtn");
    const modal = document.getElementById("districtModal");
    const form = document.getElementById("districtForm");
    const banner = document.getElementById("districtSuccessBanner");

    if (openModalBtn && modal) {
        openModalBtn.addEventListener("click", () => modal.classList.add("active"));
    }
    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
    }
    window.addEventListener("click", (e) => {
        if (modal && e.target === modal) modal.classList.remove("active");
    });

    if (form && modal) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            modal.classList.remove("active");
            form.reset();
            if (banner) {
                banner.style.display = "flex";
                setTimeout(() => { banner.style.display = "none"; }, 4000);
            }
        });
    }
});