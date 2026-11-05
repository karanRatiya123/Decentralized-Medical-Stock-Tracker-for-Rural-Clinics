document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openStockModalBtn");
    const closeModalBtn = document.getElementById("closeStockModalBtn");
    const stockModal = document.getElementById("stockModal");

    if (openModalBtn && closeModalBtn && stockModal) {
        openModalBtn.addEventListener("click", () => {
            stockModal.style.display = "flex";
            stockModal.classList.add("active");
        });

        closeModalBtn.addEventListener("click", () => {
            stockModal.style.display = "none";
            stockModal.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === stockModal) {
                stockModal.style.display = "none";
                stockModal.classList.remove("active");
            }
        });
    }
});