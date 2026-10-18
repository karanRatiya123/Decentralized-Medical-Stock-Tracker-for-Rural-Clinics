document.addEventListener("DOMContentLoaded", () => {
    const themeToggleBtn = document.getElementById("themeToggleBtn");
    const htmlElement = document.documentElement;

    // Check for saved theme in localStorage
    const savedTheme = localStorage.getItem("medstock_theme") || "dark";
    htmlElement.setAttribute("data-theme", savedTheme);
    updateButtonIcon(savedTheme);

    themeToggleBtn.addEventListener("click", () => {
        let currentTheme = htmlElement.getAttribute("data-theme");
        let newTheme = currentTheme === "dark" ? "light" : "dark";

        htmlElement.setAttribute("data-theme", newTheme);
        localStorage.setItem("medstock_theme", newTheme);
        updateButtonIcon(newTheme);
    });

    function updateButtonIcon(theme) {
        if (theme === "dark") {
            themeToggleBtn.textContent = "☀️"; // Sun icon for switching to light mode
            themeToggleBtn.title = "Switch to Light Mode";
        } else {
            themeToggleBtn.textContent = "🌙"; // Moon icon for switching to dark mode
            themeToggleBtn.title = "Switch to Dark Mode";
        }
    }
});