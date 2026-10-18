// System Admin - Master PHC Directory Filter & Modal Script
function openPhcView(name, district, location, contact, address, dho) {
    document.getElementById('modalPhcTitle').textContent = name + " - Facility Details";
    document.getElementById('dtlName').textContent     = name;
    document.getElementById('dtlDistrict').textContent = district;
    document.getElementById('dtlLocation').textContent = location;
    document.getElementById('dtlContact').textContent  = contact;
    
    const dtlAddress = document.getElementById('dtlAddress');
    if (dtlAddress) dtlAddress.textContent = address || 'Not specified';

    const dtlDHO = document.getElementById('dtlDHO');
    if (dtlDHO) dtlDHO.textContent = dho || 'Assigned District DHO';

    document.getElementById('phcDetailsModal').classList.add('active');
}

function closePhcView() {
    document.getElementById('phcDetailsModal').classList.remove('active');
}

document.addEventListener("DOMContentLoaded", () => {
    const searchInput    = document.getElementById("phcSearchInput");
    const districtFilter = document.getElementById("districtFilterSelect");
    const table          = document.getElementById("masterPhcTable");
    const rows           = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

    function filterTable() {
        const searchText       = searchInput.value.toLowerCase().trim();
        const selectedDistrict = districtFilter.value.toLowerCase().trim();

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const nameCell     = row.getElementsByTagName("td")[1]?.textContent.toLowerCase() || "";
            const districtCell = row.getElementsByTagName("td")[2]?.textContent.toLowerCase() || "";
            const villageCell  = row.getElementsByTagName("td")[3]?.textContent.toLowerCase() || "";

            const matchesSearch   = searchText === "" || nameCell.includes(searchText) || villageCell.includes(searchText);
            const matchesDistrict = selectedDistrict === "" || districtCell === selectedDistrict;

            if (matchesSearch && matchesDistrict) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        }
    }

    if (searchInput)    searchInput.addEventListener("keyup", filterTable);
    if (districtFilter) districtFilter.addEventListener("change", filterTable);
});