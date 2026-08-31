document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openDoctorModalBtn");
    const closeModalBtn = document.getElementById("closeDoctorModalBtn");
    const doctorModal = document.getElementById("doctorModal");
    const doctorForm = document.getElementById("doctorForm");
    const successBanner = document.getElementById("doctorSuccessBanner");

    if (openModalBtn && closeModalBtn && doctorModal) {
        openModalBtn.addEventListener("click", () => {
            doctorModal.classList.add("active");
        });

        closeModalBtn.addEventListener("click", () => {
            doctorModal.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            e.target === doctorModal && doctorModal.classList.remove("active");
        });
    }

    if (doctorForm) {
        doctorForm.addEventListener("submit", (e) => {
            e.preventDefault();
            doctorModal.classList.remove("active");
            doctorForm.reset();

            if (successBanner) {
                successBanner.style.display = "flex";
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }
});