// System Admin - Master PHC Directory Filter & Modal Script
function openPhcView(name, district, location, contact) {
    document.getElementById('modalPhcTitle').textContent = name + " - Details";
    document.getElementById('dtlName').textContent = name;
    document.getElementById('dtlDistrict').textContent = district;
    document.getElementById('dtlLocation').textContent = location;
    document.getElementById('dtlContact').textContent = contact;
    document.getElementById('phcDetailsModal').classList.add('active');
}

function closePhcView() {
    document.getElementById('phcDetailsModal').classList.remove('active');
}

document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("phcSearchInput");
    const districtFilter = document.getElementById("districtFilterSelect");
    const table = document.getElementById("masterPhcTable");
    const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const selectedDistrict = districtFilter.value;

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const nameCell = row.getElementsByTagName("td")[1].textContent || "";
            const districtCell = row.getElementsByTagName("td")[2].textContent || "";
            const villageCell = row.getElementsByTagName("td")[3].textContent || "";

            const matchesSearch = nameCell.toLowerCase().includes(searchText) || villageCell.toLowerCase().includes(searchText);
            const matchesDistrict = selectedDistrict === "" || districtCell.includes(selectedDistrict);

            if (matchesSearch && matchesDistrict) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }

    if (searchInput) searchInput.addEventListener("keyup", filterTable);
    if (districtFilter) districtFilter.addEventListener("change", filterTable);
});