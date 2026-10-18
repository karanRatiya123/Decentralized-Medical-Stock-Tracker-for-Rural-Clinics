document.addEventListener("DOMContentLoaded", () => {
    const addRowBtn = document.getElementById("addMedicineRowBtn");
    const container = document.getElementById("medicineRowsContainer");
    const dispenseForm = document.getElementById("dispenseForm");
    const successBanner = document.getElementById("dispenseSuccessBanner");

    // 1. Add new medicine row dynamically
    if (addRowBtn && container) {
        addRowBtn.addEventListener("click", () => {
            const newRow = document.createElement("div");
            newRow.className = "medicine-row-card";
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2; margin-bottom: 0;">
                    <label>Medicine Name</label>
                    <select name="medicine_id[]" required class="med-select">
                        <option value="">Select Medicine</option>
                        <option value="M1" data-stock="450">Paracetamol 500mg (Stock: 450)</option>
                        <option value="M2" data-stock="120">ORS Packets (Stock: 120)</option>
                        <option value="M3" data-stock="85">Cetirizine 10mg (Stock: 85)</option>
                        <option value="M4" data-stock="12">Amoxicillin 250mg (Stock: 12 - Low)</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Qty Issued</label>
                    <input type="number" name="quantity_issued[]" min="1" value="1" required class="qty-input">
                </div>
                <button type="button" class="btn-remove-row" title="Remove Medicine">&times;</button>
            `;
            container.appendChild(newRow);

            // Attach event listener to the new delete button
            attachRemoveEvent(newRow);
        });
    }

    // 2. Remove row function
    function attachRemoveEvent(rowElement) {
        const removeBtn = rowElement.querySelector(".btn-remove-row");
        removeBtn.addEventListener("click", () => {
            // Keep at least one row
            if (container.children.length > 1) {
                rowElement.remove();
            } else {
                alert("At least one medicine must be listed for this visit.");
            }
        });
    }

    // Attach to initial row
    const initialRow = container.querySelector(".medicine-row-card");
    if (initialRow) {
        attachRemoveEvent(initialRow);
    }

    // 3. Handle Form Submission Preview
    if (dispenseForm) {
        dispenseForm.addEventListener("submit", (e) => {
            e.preventDefault();
            dispenseForm.reset();

            if (successBanner) {
                successBanner.style.display = "flex";
                setTimeout(() => {
                    successBanner.style.display = "none";
                }, 4000);
            }
        });
    }
});