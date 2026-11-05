document.addEventListener("DOMContentLoaded", () => {
    // Modal Elements
    const openModalBtn = document.getElementById("openRegisterModalBtn");
    const closeModalBtn = document.getElementById("closeModalBtn");
    const registerModal = document.getElementById("registerModal");
    const registerForm = document.getElementById("registerForm");

    // Drawer Elements
    const historyDrawer = document.getElementById("historyDrawer");
    const closeDrawerBtn = document.getElementById("closeDrawerBtn");
    const patientNameLinks = document.querySelectorAll(".patient-name-link");
    const drawerPatientName = document.getElementById("drawerPatientName");
    const drawerPatientId = document.getElementById("drawerPatientId");

    // Helper functions for Modal
    function openModal() {
        if (registerModal) {
            registerModal.style.display = "flex";
            registerModal.classList.add("active");
        }
    }

    function closeModal() {
        if (registerModal) {
            registerModal.style.display = "none";
            registerModal.classList.remove("active");
        }
    }

    // 1. Open/Close Register Modal
    if (openModalBtn) {
        openModalBtn.addEventListener("click", openModal);
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener("click", closeModal);
    }

    if (registerModal) {
        window.addEventListener("click", (e) => {
            if (e.target === registerModal) {
                closeModal();
            }
        });
    }

    // 2. Open/Close Patient History Drawer
    patientNameLinks.forEach(link => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            const name = link.getAttribute("data-name");
            const id = link.getAttribute("data-id");

            if (drawerPatientName) drawerPatientName.textContent = "History: " + name;
            if (drawerPatientId) drawerPatientId.textContent = "Patient ID: " + id;

            if (historyDrawer) {
                historyDrawer.style.right = "0";
                historyDrawer.classList.add("active");
            }
        });
    });

    if (closeDrawerBtn && historyDrawer) {
        closeDrawerBtn.addEventListener("click", () => {
            historyDrawer.style.right = "-400px";
            historyDrawer.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === historyDrawer) {
                historyDrawer.style.right = "-400px";
                historyDrawer.classList.remove("active");
            }
        });
    }
});