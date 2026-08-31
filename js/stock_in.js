// js/stock_in.js

function openStockModal() {
    const modal = document.getElementById("stockModal");
    if (modal) {
        modal.classList.add("active");
        modal.style.display = "flex";
        modal.style.opacity = "1";
        modal.style.visibility = "visible";
    }
}

function closeStockModal() {
    const modal = document.getElementById("stockModal");
    if (modal) {
        modal.classList.remove("active");
        modal.style.opacity = "0";
        modal.style.visibility = "hidden";
        modal.style.display = "none";
    }
}

// Close when clicking outside on the background
window.addEventListener("click", function (e) {
    const modal = document.getElementById("stockModal");
    if (e.target === modal) {
        closeStockModal();
    }
});