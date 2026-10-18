// js/patients.js
document.addEventListener("DOMContentLoaded", function () {
    const openBtn = document.getElementById("openRegisterModalBtn");
    const closeBtn = document.getElementById("closeModalBtn");
    const modal = document.getElementById("registerModal");

    const historyDrawer = document.getElementById("historyDrawer");
    const closeDrawerBtn = document.getElementById("closeDrawerBtn");
    const patientLinks = document.querySelectorAll(".patient-name-link");
    const drawerName = document.getElementById("drawerPatientName");
    const drawerId = document.getElementById("drawerPatientId");

    // 1. Open Modal
    if (openBtn && modal) {
        openBtn.addEventListener("click", function (e) {
            e.preventDefault();
            modal.classList.add("active");
            modal.style.display = "flex";
        });
    }

    // 2. Close Modal on 'X' Button
    if (closeBtn && modal) {
        closeBtn.addEventListener("click", function (e) {
            e.preventDefault();
            modal.classList.remove("active");
            modal.style.display = "none";
        });
    }

    // 3. Close Modal on clicking outside the card
    window.addEventListener("click", function (e) {
        if (e.target === modal) {
            modal.classList.remove("active");
            modal.style.display = "none";
        }
    });

    // 4. Open Patient History Drawer
    patientLinks.forEach(function (link) {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const name = this.getAttribute("data-name");
            const id = this.getAttribute("data-id");

            if (drawerName) drawerName.textContent = name;
            if (drawerId) drawerId.textContent = "Patient ID: P-" + id;
            
            if (historyDrawer) {
                historyDrawer.classList.add("active");
                historyDrawer.style.right = "0px";
            }
        });
    });

    // 5. Close Patient History Drawer
    if (closeDrawerBtn && historyDrawer) {
        closeDrawerBtn.addEventListener("click", function () {
            historyDrawer.classList.remove("active");
            historyDrawer.style.right = "-400px";
        });
    }
});