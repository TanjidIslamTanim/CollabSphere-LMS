"use strict";

const sidebar = document.getElementById("app-sidebar");
const menuButton = document.getElementById("mobile-menu-button");

if (sidebar && menuButton) {
    menuButton.addEventListener("click", () => {
        sidebar.classList.toggle("open");
    });

    document.addEventListener("click", (event) => {
        const clickedInsideSidebar = sidebar.contains(event.target);
        const clickedMenuButton = menuButton.contains(event.target);

        if (
            window.innerWidth <= 850 &&
            sidebar.classList.contains("open") &&
            !clickedInsideSidebar &&
            !clickedMenuButton
        ) {
            sidebar.classList.remove("open");
        }
    });
}