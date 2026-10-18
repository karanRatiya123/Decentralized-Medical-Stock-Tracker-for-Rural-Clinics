// District Admin - Inventory Monitoring Search and Filter Script
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("inventorySearch");
    const phcFilter = document.getElementById("phcFilter");
    const table = document.getElementById("inventoryTable");
    const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const selectedPhc = phcFilter.value;

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const phcCell = row.getElementsByTagName("td")[0].textContent || "";
            const medicineCell = row.getElementsByTagName("td")[1].textContent || "";

            const matchesSearch = medicineCell.toLowerCase().includes(searchText);
            const matchesPhc = selectedPhc === "" || phcCell.includes(selectedPhc);

            if (matchesSearch && matchesPhc) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener("keyup", filterTable);
    }

    if (phcFilter) {
        phcFilter.addEventListener("change", filterTable);
    }
});