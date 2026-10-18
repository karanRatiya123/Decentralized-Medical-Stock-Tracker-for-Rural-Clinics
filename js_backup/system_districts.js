// System Admin - Districts & Admins Script (AJAX Backed)
document.addEventListener("DOMContentLoaded", () => {
    const openModalBtn  = document.getElementById("openDistrictModalBtn");
    const closeModalBtn = document.getElementById("closeDistrictModalBtn");
    const modal         = document.getElementById("districtModal");
    const form          = document.getElementById("districtForm");
    const banner        = document.getElementById("districtSuccessBanner");
    const bannerText    = document.getElementById("districtBannerText");
    const errorBanner   = document.getElementById("districtErrorBanner");
    const errorText     = document.getElementById("districtErrorText");
    const submitBtn     = document.getElementById("submitDistrictBtn");
    const modalTitle    = document.getElementById("modalTitle");
    const searchInput   = document.getElementById("districtSearchInput");
    const table         = document.getElementById("districtTable");

    // Open Modal for New District Admin
    if (openModalBtn && modal) {
        openModalBtn.addEventListener("click", () => {
            form.reset();
            document.getElementById("edit_id").value = "";
            document.getElementById("password").required = true;
            document.getElementById("password").placeholder = "Temporary password";
            if (modalTitle) modalTitle.textContent = "Assign New District Administrator";
            modal.classList.add("active");
        });
    }

    // Edit button click handlers
    document.querySelectorAll(".edit-admin-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            document.getElementById("edit_id").value        = btn.dataset.id;
            document.getElementById("district_name").value  = btn.dataset.district;
            document.getElementById("admin_name").value     = btn.dataset.admin;
            document.getElementById("username").value       = btn.dataset.username;
            document.getElementById("contact_no").value     = btn.dataset.contact;
            document.getElementById("email").value          = btn.dataset.email;
            document.getElementById("account_status").value = btn.dataset.status;
            
            // Password not strictly required when editing
            document.getElementById("password").required = false;
            document.getElementById("password").placeholder = "Leave blank to keep current password";
            
            if (modalTitle) modalTitle.textContent = "Edit District Administrator (" + btn.dataset.district + ")";
            modal.classList.add("active");
        });
    });

    // Close modal
    if (closeModalBtn && modal) {
        closeModalBtn.addEventListener("click", () => modal.classList.remove("active"));
    }
    window.addEventListener("click", (e) => {
        if (modal && e.target === modal) modal.classList.remove("active");
    });

    // Submit form via AJAX
    if (form && modal) {
        form.addEventListener("submit", (e) => {
            e.preventDefault();

            if (banner) banner.style.display = "none";
            if (errorBanner) errorBanner.style.display = "none";

            const originalBtn = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(form);

            fetch('districts.php', {
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
                console.error("Save district error:", err);
                if (errorText) errorText.textContent = "An unexpected server error occurred.";
                if (errorBanner) {
                    errorBanner.style.display = "flex";
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtn;
            });
        });
    }

    // Client-side search in table
    if (searchInput && table) {
        searchInput.addEventListener("keyup", () => {
            const query = searchInput.value.toLowerCase();
            const rows = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (let i = 0; i < rows.length; i++) {
                const districtText = rows[i].getElementsByTagName("td")[1]?.textContent || "";
                const adminText    = rows[i].getElementsByTagName("td")[2]?.textContent || "";
                const userText     = rows[i].getElementsByTagName("td")[3]?.textContent || "";

                if (districtText.toLowerCase().includes(query) || adminText.toLowerCase().includes(query) || userText.toLowerCase().includes(query)) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    }
});