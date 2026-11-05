/* MedStock Tracker - shared UI behaviour
   (theme, mobile menu, active nav link, table labels for card layout).
   Presentation only: no data or form logic lives here. */
document.addEventListener("DOMContentLoaded", () => {
    const htmlElement = document.documentElement;

    /* 1. THEME (light by default, dark optional) */
    let savedTheme = "light";
    try { savedTheme = localStorage.getItem("medstock_theme_v2") || "light"; } catch (e) {}
    htmlElement.setAttribute("data-theme", savedTheme);

    const themeToggleBtn = document.getElementById("themeToggleBtn");
    function updateButtonIcon(theme) {
        if (!themeToggleBtn) return;
        const dark = theme === "dark";
        themeToggleBtn.innerHTML = dark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        const label = dark ? "Switch to light mode" : "Switch to dark mode";
        themeToggleBtn.title = label;
        themeToggleBtn.setAttribute("aria-label", label);
    }
    updateButtonIcon(savedTheme);
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener("click", () => {
            const next = htmlElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
            htmlElement.setAttribute("data-theme", next);
            try { localStorage.setItem("medstock_theme_v2", next); } catch (e) {}
            updateButtonIcon(next);
        });
    }

    /* 2. ACTIVE NAV LINK (matches current page file name) */
    const here = location.pathname.split("/").pop() || "dashboard.php";
    document.querySelectorAll(".nav-links li").forEach(li => {
        const a = li.querySelector("a");
        if (!a) return;
        const target = (a.getAttribute("href") || "").split("?")[0].split("/").pop();
        const isHere = target === here;
        li.classList.toggle("active", isHere);
        if (isHere) a.setAttribute("aria-current", "page");
        else a.removeAttribute("aria-current");
    });

    /* 3. MOBILE MENU BUTTON */
    const sidebar = document.querySelector(".sidebar");
    const brand = document.querySelector(".brand-header");
    if (sidebar && brand) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "menu-toggle";
        btn.setAttribute("aria-expanded", "false");
        btn.innerHTML = '<i class="fas fa-bars"></i> <span>Menu</span>';
        btn.addEventListener("click", () => {
            const open = sidebar.classList.toggle("menu-open");
            btn.setAttribute("aria-expanded", String(open));
            btn.innerHTML = open
                ? '<i class="fas fa-times"></i> <span>Close</span>'
                : '<i class="fas fa-bars"></i> <span>Menu</span>';
        });
        brand.appendChild(btn);
    }

    /* 4. TABLE -> CARD LABELS (each cell remembers its column title) */
    document.querySelectorAll("table.data-table").forEach(table => {
        const heads = Array.from(table.querySelectorAll("thead th")).map(th => th.textContent.trim());
        if (heads.length >= 7) table.classList.add("wide");
        else if (heads.length === 6) table.classList.add("mid");
        table.querySelectorAll("tbody tr").forEach(tr => {
            Array.from(tr.children).forEach((td, i) => {
                if (td.tagName !== "TD") return;
                if (heads[i]) td.setAttribute("data-label", heads[i]);
                if (td.querySelector("button, a.btn-action, .btn-approve, .btn-reject")
                    && !td.textContent.replace(/\s+/g, "").match(/[A-Za-z0-9]{40,}/)) {
                    const onlyActions = Array.from(td.children).every(c =>
                        c.matches("button, a, form, div.action-buttons-group"));
                    if (onlyActions) td.classList.add("td-actions");
                }
            });
        });
    });

    /* 5. LIVE CLOCK (Real-time ticking clock across all pages) */
    const clockDisplays = document.querySelectorAll(".date-time-display");
    if (clockDisplays.length > 0) {
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        function tickClock() {
            const now = new Date();
            const day = String(now.getDate()).padStart(2, "0");
            const mon = months[now.getMonth()];
            const year = now.getFullYear();
            let hours = now.getHours();
            const ampm = hours >= 12 ? "PM" : "AM";
            hours = hours % 12;
            hours = hours ? String(hours).padStart(2, "0") : "12";
            const mins = String(now.getMinutes()).padStart(2, "0");
            const secs = String(now.getSeconds()).padStart(2, "0");
            const formatted = `${day} ${mon} ${year}, ${hours}:${mins}:${secs} ${ampm}`;
            clockDisplays.forEach(el => {
                const span = el.querySelector("span");
                if (span) {
                    span.textContent = formatted;
                } else {
                    el.textContent = formatted;
                }
            });
        }
        tickClock();
        setInterval(tickClock, 1000);
    }
});
