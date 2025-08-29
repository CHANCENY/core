const currentURI = window.location.href;

// Sanitize variable name: spaces -> _, only letters/_, uppercase
function sanitizeName(input) {
    if (!input) return "";
    let sanitized = input.trim().replace(/\s+/g, "_");
    sanitized = sanitized.replace(/[^A-Za-z_]/g, "");
    return sanitized.toUpperCase();
}

// Modal elements
const modal = document.getElementById("variable-modal");
const varInput = document.getElementById("var-input");
const varValueInput = document.getElementById("var-value-input");
const varId = document.getElementById("var-id");
const modalTitle = document.getElementById("modal-title");

function openModal(mode, id = null, name = "", value = "") {
    modal.style.display = "flex";
    varInput.value = name;
    varValueInput.value = value;
    varId.value = id || "";
    modalTitle.textContent = mode === "edit" ? "Edit Variable" : "Add Variable";
    varInput.focus();
}

function closeModal() {
    modal.style.display = "none";
    varInput.value = "";
    varValueInput.value = "";
    varId.value = "";
}

// Cancel button
document.getElementById("cancel-variable").addEventListener("click", () => closeModal());

// Add new variable
document.getElementById("new-variable").addEventListener("click", () => openModal("add"));

// Save variable (Add/Edit)
document.getElementById("save-variable").addEventListener("click", () => {
    let id = varId.value;
    let name = sanitizeName(varInput.value);
    let value = varValueInput.value.trim();

    if (!name) {
        alert("Invalid variable name. Use only letters. Spaces become '_'.");
        return;
    }

    const action = id ? "edit" : "create";

    fetch(currentURI, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action, id, name, value })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const tbody = document.querySelector("#variables-table tbody");

                if (action === "create") {
                    const noRow = tbody.querySelector("tr td[colspan]");
                    if (noRow) noRow.parentNode.remove();

                    const row = document.createElement("tr");
                    row.setAttribute("data-id", data.id);
                    row.innerHTML = `
                    <td class="var-name">${data.name}</td>
                    <td class="var-value">${data.value}</td>
                    <td>
                        <button class="edit-btn">Edit</button>
                        <button class="delete-btn">Delete</button>
                    </td>
                `;
                    tbody.appendChild(row);
                }

                if (action === "edit") {
                    const row = document.querySelector(`#variables-table tr[data-id="${id}"]`);
                    if (row) {
                        row.querySelector(".var-name").textContent = data.name;
                        row.querySelector(".var-value").textContent = data.value;
                    }
                }

                closeModal();
            } else {
                alert("Error saving variable");
            }
        });
});

// Edit/Delete handlers
document.querySelector("#variables-table").addEventListener("click", function (e) {
    const row = e.target.closest("tr");
    if (!row) return;

    const id = row.getAttribute("data-id");
    const nameCell = row.querySelector(".var-name");
    const valueCell = row.querySelector(".var-value");

    if (e.target.classList.contains("edit-btn")) {
        openModal("edit", id, nameCell.textContent, valueCell.textContent);
    }

    if (e.target.classList.contains("delete-btn")) {
        if (!confirm("Delete this variable?")) return;

        fetch(currentURI, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "delete", id })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    const tbody = document.querySelector("#variables-table tbody");
                    if (tbody.children.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="3">No Variables</td></tr>`;
                    }
                } else {
                    alert("Error deleting variable");
                }
            });
    }
});

// Live sanitization for name
varInput.addEventListener("input", () => {
    varInput.value = sanitizeName(varInput.value);
});