// System Admin - Master Medicines Script
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openMedModalBtn");
    const closeModalBtn = document.getElementById("closeMedModalBtn");
    const modal = document.getElementById("medModal");
    const form = document.getElementById("medForm");
    const banner = document.getElementById("medSuccessBanner");
    const searchInput = document.getElementById("medSearchInput");
    const table = document.getElementById("masterMedTable");

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

    if (searchInput && table) {
        searchInput.addEventListener("keyup", () => {
            const searchText = searchInput.value.toLowerCase();
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName("td")[1].textContent || "";
                const catCell = rows[i].getElementsByTagName("td")[2].textContent || "";

                if (nameCell.toLowerCase().includes(searchText) || catCell.toLowerCase().includes(searchText)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }
});