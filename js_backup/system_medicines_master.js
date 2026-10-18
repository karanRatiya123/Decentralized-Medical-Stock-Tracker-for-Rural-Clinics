// System Admin - Master Medicines Script (AJAX Backed)
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn  = document.getElementById("openMedModalBtn");
    const closeModalBtn = document.getElementById("closeMedModalBtn");
    const modal         = document.getElementById("medModal");
    const form          = document.getElementById("medForm");
    const banner        = document.getElementById("medSuccessBanner");
    const bannerText    = document.getElementById("medBannerText");
    const errorBanner   = document.getElementById("medErrorBanner");
    const errorText     = document.getElementById("medErrorText");
    const searchInput   = document.getElementById("medSearchInput");
    const table         = document.getElementById("masterMedTable");
    const submitBtn     = document.getElementById("saveMedBtn");
    const modalTitle    = document.getElementById("medModalTitle");

    // Open Add Modal
    if (openModalBtn && modal) {
        openModalBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("edit_id").value = "";
            if (modalTitle) modalTitle.textContent = "Add Authorized Master Medicine";
            modal.classList.add("active");
        });
    }

    // Edit button handlers
    document.querySelectorAll(".edit-med-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("edit_id").value      = btn.dataset.id;
            document.getElementById("med_name").value     = btn.dataset.name;
            document.getElementById("med_category").value = btn.dataset.category;
            document.getElementById("med_form").value     = btn.dataset.form;
            document.getElementById("pack_price").value   = btn.dataset.pack;
            document.getElementById("tablet_price").value = btn.dataset.tablet;

            if (modalTitle) modalTitle.textContent = "Edit Master Medicine (" + btn.dataset.name + ")";
            modal.classList.add("active");
        });
    });

    // Close Modal
    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
    }
    window.addEventListener("click", (e) => {
        if (modal && e.target === modal) modal.classList.remove("active");
    });

    // Form submission via AJAX
    if (form && modal) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();

            if (banner) banner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            const origBtn = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(form);

            fetch('medicines_master.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    modal.classList.remove("active");
                    form.reset();
                    if (bannerText) bannerText.textContent = data.message;
                    if (banner) {
                        banner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    if (errorText) errorText.textContent = data.message;
                    if (errorBanner) {
                        errorBanner.style.display = "flex";
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            })
            .catch(err => {
                console.error("Save medicine error:", err);
                if (errorText) errorText.textContent = "An error occurred while saving the medicine.";
                if (errorBanner) {
                    errorBanner.style.display = "flex";
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtn;
            });
        });
    }

    // Client-side search in master table
    if (searchInput && table) {
        searchInput.addEventListener("keyup", () => {
            const searchText = searchInput.value.toLowerCase().trim();
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName("td")[1]?.textContent || "";
                const catCell  = rows[i].getElementsByTagName("td")[2]?.textContent || "";

                if (nameCell.toLowerCase().includes(searchText) || catCell.toLowerCase().includes(searchText)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }
});