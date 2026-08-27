"use strict";

/**
 * Initializes the Add Transactions page functionality.
 * @param {Object} config - Configuration object with acct, maxCheck, numRows, names
 */
function initAddTransactions(config) {
    // Set up autocomplete on all description fields
    for (let i = 0; i < config.numRows; i++) {
        const input = document.getElementById("description_" + i);
        if (input) {
            autocomplete(input, config.names);
        }
    }
}

/**
 * Copy the date down from the row above if this input field is empty.
 */
function steal_date(form, num, total) {
    if (num > 0) {
        const ob = form.elements["date_" + num];
        if (ob.value === "") {
            const prev = form.elements["date_" + (num - 1)];
            if (prev.value !== "") {
                ob.value = prev.value;
                ob.select();
            }
        }
    }
}

/**
 * Clean up a partial date.
 * "1" => 1st of current month/year
 * "11/1" => Nov 1 of current year (or last year if in future)
 */
function clean_date(dateIn) {
    if (!dateIn || dateIn.trim() === "") return dateIn;

    const args = dateIn.split("/");
    let month, day, year;
    const now = new Date();
    const currentYear = now.getFullYear();

    if (args.length === 1) {
        month = now.getMonth() + 1;
        day = parseInt(args[0], 10);
        year = currentYear;
    } else {
        month = parseInt(args[0], 10);
        day = parseInt(args[1], 10);
        year = args.length > 2 ? parseInt(args[2], 10) : currentYear;
    }

    if (args.length <= 2) {
        const thisMonth = now.getMonth() + 1;
        if (month > thisMonth) {
            year--;
        }
    }

    if (year < 100) {
        year += 2000;
    }

    return month + "/" + day + "/" + year;
}

/**
 * Suggest the next check number when type is "Check".
 */
function suggest_check_number(form, num, total) {
    const sel = form.elements["type_" + num];
    const selValue = sel.options[sel.selectedIndex].value;

    if (selValue !== "3") {
        return;
    }

    const ob = form.elements["num_" + num];
    if (ob.value === "") {
        let found = false;
        let anum = num;
        while (!found && anum > 0) {
            const prev = form.elements["num_" + (anum - 1)];
            if (prev.value !== "") {
                ob.value = parseInt(prev.value, 10) + 1;
                ob.select();
                found = true;
            }
            anum--;
        }
        if (!found && typeof APP_CONFIG !== "undefined" && APP_CONFIG.maxCheck !== "") {
            ob.value = parseInt(APP_CONFIG.maxCheck, 10) + 1;
            ob.select();
        }
    }
}

/**
 * When the amount field receives focus, look up the last amount for this description via AJAX.
 */
function onFocusAmount(form, num) {
    const ob = form.elements["description_" + num];
    const desc = ob.value;
    const amountOb = form.elements["amount_" + num];

    if (amountOb.value !== "" || desc === "") {
        return;
    }

    const acct = typeof APP_CONFIG !== "undefined" ? APP_CONFIG.acct : 0;
    const url = "ajax.php?acct=" + acct + "&function=lastAmount&desc=" + encodeURIComponent(desc);

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error !== 0) {
                console.error("AJAX error:", data.message);
                return;
            }
            const amt = Math.abs(data.data.amount);
            if (amt > 0.1) {
                amountOb.value = amt;
                amountOb.select();
            }
        })
        .catch(err => console.error("Fetch error:", err));
}

/**
 * Autocomplete function for text input fields.
 * @param {HTMLInputElement} inp - The input element
 * @param {string[]} arr - Array of possible values
 */
function autocomplete(inp, arr) {
    let currentFocus = -1;

    inp.addEventListener("input", function () {
        const val = this.value;
        closeAllLists();
        if (!val) return;
        currentFocus = -1;

        const listDiv = document.createElement("DIV");
        listDiv.setAttribute("id", this.id + "autocomplete-list");
        listDiv.setAttribute("class", "autocomplete_items");
        this.parentNode.appendChild(listDiv);

        for (let i = 0; i < arr.length; i++) {
            if (arr[i].substr(0, val.length).toUpperCase() === val.toUpperCase()) {
                const itemDiv = document.createElement("DIV");
                itemDiv.setAttribute("class", "autocomplete_div");
                itemDiv.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>";
                itemDiv.innerHTML += arr[i].substr(val.length);
                itemDiv.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                itemDiv.addEventListener("click", function () {
                    inp.value = this.getElementsByTagName("input")[0].value;
                    closeAllLists();
                    inp.focus();
                });
                listDiv.appendChild(itemDiv);
            }
        }
    });

    inp.addEventListener("keydown", function (e) {
        let x = document.getElementById(this.id + "autocomplete-list");
        if (x) x = x.getElementsByTagName("div");
        if (e.key === "ArrowDown") {
            currentFocus++;
            addActive(x);
        } else if (e.key === "ArrowUp") {
            currentFocus--;
            addActive(x);
        } else if (e.key === "Enter") {
            e.preventDefault();
            if (currentFocus > -1 && x) {
                x[currentFocus].click();
            }
        }
    });

    function addActive(x) {
        if (!x) return;
        removeActive(x);
        if (currentFocus >= x.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = x.length - 1;
        x[currentFocus].classList.add("autocomplete-active");
    }

    function removeActive(x) {
        for (let i = 0; i < x.length; i++) {
            x[i].classList.remove("autocomplete-active");
        }
    }

    function closeAllLists(elmnt) {
        const items = document.getElementsByClassName("autocomplete_items");
        for (let i = items.length - 1; i >= 0; i--) {
            if (elmnt !== items[i] && elmnt !== inp) {
                items[i].parentNode.removeChild(items[i]);
            }
        }
    }

    document.addEventListener("click", function (e) {
        closeAllLists(e.target);
    });
}

/**
 * Wires up the confirmation step for editing a reconciled transaction.
 *
 * Only the date and check number are editable on a reconciled transaction. Save
 * stays disabled until one of them actually changes, and the change has to be
 * confirmed in a modal that spells out exactly what is changing. Without
 * JavaScript the form posts straight through and the server renders an
 * equivalent confirmation page.
 *
 * @param {Object} config - ids of the form, save button, modal, and change list
 */
function initReconciledEdit(config) {
    const form = document.getElementById(config.formId);
    const saveBtn = document.getElementById(config.saveButtonId);
    const confirmField = document.getElementById(config.confirmFieldId);
    const modalEl = document.getElementById(config.modalId);
    const confirmBtn = document.getElementById(config.confirmButtonId);
    const changeList = document.getElementById(config.changeListId);

    if (!form || !saveBtn || !confirmField || !modalEl || !confirmBtn || !changeList) {
        return;
    }

    // Without Bootstrap there is no modal to show. Leave the form alone so it
    // posts straight through and the server renders its confirmation page.
    if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
        return;
    }

    const dateInput = form.elements["date"];
    const numInput = form.elements["num"];
    const labels = config.labels || {};
    const noneLabel = labels.none || "(none)";

    const originalDate = normalizeDateValue(dateInput.value);
    const originalNum = normalizeCheckNumber(numInput.value);

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    function currentChanges() {
        const changes = [];
        const newDate = normalizeDateValue(dateInput.value);
        const newNum = normalizeCheckNumber(numInput.value);

        if (newDate !== originalDate) {
            changes.push({
                label: labels.date || "Date",
                from: originalDate || noneLabel,
                to: newDate || noneLabel
            });
        }
        if (newNum !== originalNum) {
            changes.push({
                label: labels.num || "ChkNo",
                from: originalNum || noneLabel,
                to: newNum || noneLabel
            });
        }
        return changes;
    }

    function refreshSaveState() {
        const changed = currentChanges().length > 0;
        saveBtn.disabled = !changed;
        saveBtn.title = changed ? "" : "No changes to save";
    }

    function renderChanges(changes) {
        changeList.replaceChildren();
        changes.forEach(function (change) {
            const item = document.createElement("li");
            item.className = "list-group-item d-flex justify-content-between align-items-center px-0";

            const label = document.createElement("span");
            label.className = "fw-semibold";
            label.textContent = change.label;

            const values = document.createElement("span");
            const from = document.createElement("span");
            from.className = "text-muted text-decoration-line-through";
            from.textContent = change.from;
            const arrow = document.createElement("i");
            arrow.className = "bi bi-arrow-right mx-2";
            arrow.setAttribute("aria-hidden", "true");
            const to = document.createElement("span");
            to.className = "fw-semibold";
            to.textContent = change.to;

            values.appendChild(from);
            values.appendChild(arrow);
            values.appendChild(to);
            item.appendChild(label);
            item.appendChild(values);
            changeList.appendChild(item);
        });
    }

    dateInput.addEventListener("input", refreshSaveState);
    numInput.addEventListener("input", refreshSaveState);

    form.addEventListener("submit", function (e) {
        if (e.submitter && e.submitter.dataset.confirmDelete === "1") {
            return; // the delete button runs its own confirmation
        }
        if (confirmField.value === "1") {
            return; // already confirmed
        }
        e.preventDefault();

        const changes = currentChanges();
        if (changes.length === 0) {
            return;
        }
        renderChanges(changes);
        modal.show();
    });

    // Default focus to Cancel so the risky action is never the one-keypress path.
    modalEl.addEventListener("shown.bs.modal", function () {
        const cancelBtn = modalEl.querySelector("[data-bs-dismiss='modal'].btn-secondary");
        if (cancelBtn) {
            cancelBtn.focus();
        }
    });

    confirmBtn.addEventListener("click", function () {
        confirmField.value = "1";
        form.submit();
    });

    refreshSaveState();
}

/**
 * Normalize a typed date for comparison/display ("1/5" => "1/5/2026").
 */
function normalizeDateValue(value) {
    const trimmed = (value || "").trim();
    if (trimmed === "") return "";
    const cleaned = clean_date(trimmed);
    return cleaned && cleaned.indexOf("NaN") === -1 ? cleaned : trimmed;
}

/**
 * Normalize a check number for comparison ("0123" and "123" are the same).
 */
function normalizeCheckNumber(value) {
    const trimmed = (value || "").trim();
    if (trimmed === "") return "";
    return /^\d+$/.test(trimmed) ? String(parseInt(trimmed, 10)) : trimmed;
}

/**
 * Wires up the delete confirmation on the edit transaction page.
 *
 * The delete button is a submit button pointing at the delete handler via
 * formaction, so without JavaScript it posts straight through and the server
 * renders an equivalent confirmation page. With JavaScript we intercept it and
 * confirm in a modal first.
 *
 * @param {Object} config - ids of the form, delete button, modal, and confirm field
 */
function initDeleteTransaction(config) {
    const form = document.getElementById(config.formId);
    const deleteBtn = document.getElementById(config.deleteButtonId);
    const confirmField = document.getElementById(config.confirmFieldId);
    const modalEl = document.getElementById(config.modalId);
    const confirmBtn = document.getElementById(config.confirmButtonId);

    if (!form || !deleteBtn || !confirmField || !modalEl || !confirmBtn) {
        return;
    }

    // Without Bootstrap there is no modal; let the post reach the server, which
    // renders its own confirmation page.
    if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    deleteBtn.addEventListener("click", function (e) {
        if (confirmField.value === "1") {
            return; // already confirmed, let the submit through
        }
        e.preventDefault();
        modal.show();
    });

    // Default focus to Cancel so the destructive action is never one keypress away.
    modalEl.addEventListener("shown.bs.modal", function () {
        const cancelBtn = modalEl.querySelector("[data-bs-dismiss='modal'].btn-secondary");
        if (cancelBtn) {
            cancelBtn.focus();
        }
    });

    confirmBtn.addEventListener("click", function () {
        confirmField.value = "1";
        form.action = config.action;
        form.submit();
    });
}
