document.addEventListener("DOMContentLoaded", () => {
    // Modal Elements
    const openModalBtn = document.getElementById("openRegisterModalBtn");
    const closeModalBtn = document.getElementById("closeModalBtn");
    const registerModal = document.getElementById("registerModal");
    const registerForm = document.getElementById("registerForm");
    const successBanner = document.getElementById("successBanner");

    // Drawer Elements
    const historyDrawer = document.getElementById("historyDrawer");
    const closeDrawerBtn = document.getElementById("closeDrawerBtn");
    const patientNameLinks = document.querySelectorAll(".patient-name-link");
    const drawerPatientName = document.getElementById("drawerPatientName");
    const drawerPatientId = document.getElementById("drawerPatientId");

    // 1. Open/Close Register Modal
    if (openModalBtn && closeModalBtn && registerModal) {
        openModalBtn.addEventListener("click", () => {
            registerModal.classList.add("active");
        });

        closeModalBtn.addEventListener("click", () => {
            registerModal.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === registerModal) {
                registerModal.classList.remove("active");
            }
        });
    }

    // 2. Handle Mock Registration Submission
    if (registerForm) {
        registerForm.addEventListener("submit", (e) => {
            e.preventDefault();
            registerModal.classList.remove("active");
            registerForm.reset();

            // Show success banner
            if (successBanner) {
                successBanner.style.display = "flex";
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }

    // 3. Open/Close Patient History Drawer
    patientNameLinks.forEach(link => {
        link.addEventListener("click", (e) => {
            e.preventDefault();
            const name = link.getAttribute("data-name");
            const id = link.getAttribute("data-id");

            if (drawerPatientName) drawerPatientName.textContent = "History: " + name;
            if (drawerPatientId) drawerPatientId.textContent = "Patient ID: " + id;

            if (historyDrawer) {
                historyDrawer.classList.add("active");
            }
        });
    });

    if (closeDrawerBtn && historyDrawer) {
        closeDrawerBtn.addEventListener("click", () => {
            historyDrawer.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === historyDrawer) {
                historyDrawer.classList.remove("active");
            }
        });
    }
});