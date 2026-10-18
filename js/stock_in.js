document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openStockModalBtn");
    const closeModalBtn = document.getElementById("closeStockModalBtn");
    const stockModal = document.getElementById("stockModal");
    const stockForm = document.getElementById("stockForm");
    const successBanner = document.getElementById("stockSuccessBanner");

    if (openModalBtn && closeModalBtn && stockModal) {
        openModalBtn.addEventListener("click", () => {
            stockModal.classList.add("active");
        });

        closeModalBtn.addEventListener("click", () => {
            stockModal.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === stockModal) {
                stockModal.classList.remove("active");
            }
        });
    }

    if (stockForm) {
        stockForm.addEventListener("submit", (e) => {
            e.preventDefault();
            stockModal.classList.remove("active");
            stockForm.reset();

            if (successBanner) {
                successBanner.style.display = "flex";
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }
});