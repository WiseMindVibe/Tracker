<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
}

/* Top bar container */
.topbar {
    width: 100%;
    background: #0f0f0f;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 999;
}

/* Logo */
.logo {
    font-size: 20px;
    font-weight: bold;
}

/* Nav links */
.nav-links {
    list-style: none;
    display: flex;
    gap: 20px;
    margin: 0;
    margin-right: auto;
}

.nav-links li a {
    text-decoration: none;
    color: #ddd;
    font-size: 15px;
    transition: 0.2s;
}

.nav-links li a:hover {
    color: #fff;
}

/* Mobile — hide links, show menu button */
.menu-btn {
    display: none;
    font-size: 26px;
    cursor: pointer;
}

/* Responsive view */
@media (max-width: 768px) {
    .nav-links {
        position: absolute;
        top: 60px;
        right: 0;
        background: #111;
        width: 200px;
        flex-direction: column;
        padding: 15px;
        display: none;
    }

    .nav-links.show {
        display: flex;
    }

    .menu-btn {
        display: block;
    }
}
</style>

<!-- REMOVE /track FROM HREFS LINKS -->
<nav class="topbar">
    <div class="logo">MyTracker</div>
    <ul class="nav-links">
        <li><a href="#">Dashboard</a></li>
        <li><a href="/views/reporting.php">Reporting</a></li>
        <li><a href="/views/offers/list.php">Offers</a></li>
        <li><a href="/views/campaigns/list.php">Campaigns</a></li>
        <li><a href="/views/notifications.php">Notification</a></li>
        <li><a href="/views/affiliate_programs/list.php">Affiliate Programs</a></li>
        <li><a href="/views/traffic_sources/list.php">Traffic Sources</a></li>
        <li><a href="/views/websites/list.php">Websites</a></li>
    </ul>

    <div class="menu-btn" onclick="toggleMenu()">☰</div>
</nav>



<script>
function toggleMenu() {
    document.querySelector('.nav-links').classList.toggle('show');
}
</script>
