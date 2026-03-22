<?php
$navPage = strtolower(trim((string) ($_GET['page'] ?? '')));
$nav = [
    ['page' => 'dashboard', 'label' => 'Dashboard'],
    ['page' => 'inspect', 'label' => 'Inspect'],
    ['page' => 'reporting', 'label' => 'Reporting'],
    ['page' => 'offers', 'label' => 'Offers'],
    ['page' => 'campaigns', 'label' => 'Campaigns'],
    ['page' => 'notifications', 'label' => 'Notifications'],
    ['page' => 'affiliates', 'label' => 'Affiliates'],
    ['page' => 'traffics', 'label' => 'Traffic'],
    ['page' => 'websites', 'label' => 'Websites'],
];
?>
<header class="topbar" role="banner">
    <div class="topbar__inner">
        <a class="topbar__brand" href="index.php?page=dashboard&action=index">
            <span class="topbar__brand-mark" aria-hidden="true"></span>
            <span class="topbar__brand-text">MyTracker</span>
        </a>

        <nav class="topbar__nav" id="topbar-main-nav" aria-label="Main navigation">
            <ul class="topbar__links">
                <?php foreach ($nav as $item): ?>
                    <?php
                    $active = $navPage === $item['page'];
                    $href = 'index.php?page=' . rawurlencode($item['page']) . '&action=index';
                    ?>
                    <li>
                        <a class="topbar__link<?= $active ? ' is-active' : '' ?>"
                            href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $active ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="topbar__actions">
            <button type="button" class="topbar__theme-btn" id="topbar-theme-btn" aria-pressed="false" aria-label="Switch theme">
                <svg class="topbar__theme-icon topbar__theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg class="topbar__theme-icon topbar__theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
            <button type="button" class="topbar__menu-btn" id="topbar-menu-btn" onclick="toggleTopbarMenu()" aria-expanded="false" aria-controls="topbar-main-nav" aria-label="Menu">
                <span class="topbar__menu-icon" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</header>
