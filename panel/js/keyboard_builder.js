document.addEventListener("DOMContentLoaded", async () => {
    const activeKeyboardContainer = document.getElementById("active-keyboard");
    const unusedKeysContainer = document.getElementById("unused-keys");
    const addRowBtn = document.getElementById("add-row-btn");
    const saveBtn = document.getElementById("save-keyboard-btn");
    
    let textDict = {};
    
    // Fetch current keyboard data
    try {
        const response = await fetch("../api/keyboard.php");
        const data = await response.json();
        textDict = data.text;
        
        renderUnusedKeys(data.keylist);
        renderActiveKeyboard(data.userlist);
        initSortables();
        checkEmptyRows();
    } catch (error) {
        console.error("Error fetching keyboard data:", error);
        alert("خطا در دریافت اطلاعات دکمه‌ها");
    }

    function createButtonElement(keyName) {
        const btn = document.createElement("div");
        btn.className = "kb-btn";
        btn.dataset.key = keyName;
        btn.innerHTML = `<span>${textDict[keyName] || keyName}</span>`;
        return btn;
    }

    function createRowElement() {
        const rowContainer = document.createElement("div");
        rowContainer.className = "kb-row-container";
        
        const handle = document.createElement("div");
        handle.className = "row-handle";
        handle.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>`;
        
        const row = document.createElement("div");
        row.className = "kb-row";
        
        const deleteBtn = document.createElement("div");
        deleteBtn.className = "row-delete";
        deleteBtn.title = "حذف ردیف";
        deleteBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>`;
        deleteBtn.addEventListener("click", () => {
            // Move buttons back to unused keys before deleting
            Array.from(row.children).forEach(btn => unusedKeysContainer.appendChild(btn));
            rowContainer.remove();
        });

        rowContainer.appendChild(handle);
        rowContainer.appendChild(row);
        rowContainer.appendChild(deleteBtn);
        
        return rowContainer;
    }

    function renderUnusedKeys(keylist) {
        unusedKeysContainer.innerHTML = "";
        keylist.forEach(item => {
            if (item && item.length > 0 && item[0].text) {
                unusedKeysContainer.appendChild(createButtonElement(item[0].text));
            }
        });
    }

    function renderActiveKeyboard(userlist) {
        activeKeyboardContainer.innerHTML = "";
        userlist.forEach(rowArr => {
            const rowEl = createRowElement();
            const innerRow = rowEl.querySelector('.kb-row');
            rowArr.forEach(item => {
                if (item.text) {
                    innerRow.appendChild(createButtonElement(item.text));
                }
            });
            activeKeyboardContainer.appendChild(rowEl);
        });
    }

    let rowSortables = [];
    
    function initSortables() {
        // Sortable for rearranging rows
        new Sortable(activeKeyboardContainer, {
            animation: 150,
            handle: ".row-handle",
            ghostClass: "sortable-ghost",
            onEnd: checkEmptyRows
        });

        // Sortable for unused keys area
        new Sortable(unusedKeysContainer, {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            onEnd: checkEmptyRows
        });

        // Initialize sortable for existing rows
        document.querySelectorAll(".kb-row").forEach(initRowSortable);
    }

    function initRowSortable(rowElement) {
        const sortable = new Sortable(rowElement, {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            onEnd: checkEmptyRows
        });
        rowSortables.push(sortable);
    }

    addRowBtn.addEventListener("click", () => {
        const newRow = createRowElement();
        activeKeyboardContainer.appendChild(newRow);
        initRowSortable(newRow.querySelector('.kb-row'));
        checkEmptyRows();
    });

    function checkEmptyRows() {
        document.querySelectorAll(".kb-row").forEach(row => {
            if (row.children.length === 0) {
                row.classList.add("empty-row");
            } else {
                row.classList.remove("empty-row");
            }
        });
    }

    saveBtn.addEventListener("click", async () => {
        saveBtn.innerText = "در حال ذخیره...";
        saveBtn.disabled = true;

        const keyboardData = [];
        
        document.querySelectorAll(".kb-row-container").forEach(rowContainer => {
            const rowData = [];
            const row = rowContainer.querySelector('.kb-row');
            Array.from(row.children).forEach(btn => {
                if (btn.dataset.key) {
                    rowData.push({ text: btn.dataset.key });
                }
            });
            // Telegram allows empty rows? Usually not, so let's only add non-empty rows.
            if (rowData.length > 0) {
                keyboardData.push(rowData);
            }
        });

        try {
            const response = await fetch("", { // Post to same page (keyboard.php)
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(keyboardData)
            });
            
            if (response.ok) {
                // Flash success
                saveBtn.style.background = "#059669";
                saveBtn.innerText = "ذخیره شد!";
                setTimeout(() => {
                    saveBtn.style.background = "";
                    saveBtn.innerText = "ذخیره تغییرات";
                    saveBtn.disabled = false;
                }, 2000);
            } else {
                throw new Error("Server response not OK");
            }
        } catch (error) {
            console.error(error);
            alert("خطا در ذخیره‌سازی");
            saveBtn.innerText = "ذخیره تغییرات";
            saveBtn.disabled = false;
        }
    });
});
