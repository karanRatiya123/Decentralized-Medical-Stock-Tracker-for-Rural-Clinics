document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn = document.getElementById("openDoctorModalBtn");
    const closeModalBtn = document.getElementById("closeDoctorModalBtn");
    const doctorModal = document.getElementById("doctorModal");

    if (openModalBtn && closeModalBtn && doctorModal) {
        openModalBtn.addEventListener("click", () => {
            doctorModal.style.display = "flex";
            doctorModal.classList.add("active");
        });

        closeModalBtn.addEventListener("click", () => {
            doctorModal.style.display = "none";
            doctorModal.classList.remove("active");
        });

        window.addEventListener("click", (e) => {
            if (e.target === doctorModal) {
                doctorModal.style.display = "none";
                doctorModal.classList.remove("active");
            }
        });
    }
});