function toggleTopbarMenu() {
    var nav = document.getElementById("topbar-main-nav");
    var btn = document.getElementById("topbar-menu-btn");
    if (!nav || !btn) return;
    var open = nav.classList.toggle("is-open");
    btn.setAttribute("aria-expanded", open ? "true" : "false");
}

/** Legacy name if anything still calls it */
function toggleMenu() {
    toggleTopbarMenu();
}
