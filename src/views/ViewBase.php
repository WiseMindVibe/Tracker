<?php
/**
 * Generic CRUD shell for entity hub pages (offers, affiliates, websites, traffics, campaigns).
 *
 * @var object $this Controller instance
 * @var array $config
 * @var array $rows
 * @var int $total
 * @var string $formView
 */

$emSlug = strtolower($this->getPageName());
$emHubSlugs = ['offers', 'affiliates', 'websites', 'traffics', 'campaigns'];
$emInHub = in_array($emSlug, $emHubSlugs, true);

$emHubLinks = [
    'offers' => 'Offers',
    'affiliates' => 'Affiliates',
    'websites' => 'Websites',
    'traffics' => 'Traffic sources',
    'campaigns' => 'Campaigns',
];

$emSubtitles = [
    'offers' => 'Link affiliates and websites, set caps, and attach article URLs used in rotation.',
    'affiliates' => 'Affiliate accounts and credentials referenced when you create or edit offers.',
    'websites' => 'Sites and country targets that offers can point traffic to.',
    'traffics' => 'Traffic sources and API keys used to pause or resume campaigns remotely.',
    'campaigns' => 'Bundle offers with caps, external source IDs, and live tracking URLs.',
];

$emDisplayTitles = [
    'offers' => 'Offers',
    'affiliates' => 'Affiliate programs',
    'websites' => 'Websites',
    'traffics' => 'Traffic sources',
    'campaigns' => 'Campaigns',
];

$emSubtitle = $emSubtitles[$emSlug] ?? 'Manage records for your tracker.';
$emDisplayTitle = $emDisplayTitles[$emSlug] ?? ucfirst($emSlug);

$emLimit = max(1, (int) ($_GET['limit'] ?? 500));
$emPage = max(1, (int) ($_GET['p'] ?? 1));
$emFrom = $total > 0 ? (($emPage - 1) * $emLimit) + 1 : 0;
$emTo = $total > 0 ? min($total, $emPage * $emLimit) : 0;

$emModalWide = in_array($emSlug, ['campaigns', 'offers'], true);

$funnelIcon = '<svg class="entity-th-filter__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>';
?>

<link rel="stylesheet" href="./src/assets/css/entity-manager.css">

<div class="entity-page">
    <?php if ($emInHub): ?>
        <nav class="entity-hub" aria-label="Tracker setup resources">
            <span class="entity-hub__label">Setup</span>
            <?php foreach ($emHubLinks as $slug => $label): ?>
                <?php
                $hubHref = 'index.php?page=' . rawurlencode($slug) . '&action=index';
                $hubActive = $slug === $emSlug;
                ?>
                <a class="entity-hub__link<?= $hubActive ? ' is-active' : '' ?>"
                    href="<?= htmlspecialchars($hubHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <header class="entity-header">
        <div class="entity-header__titles">
            <h1 class="entity-title"><?= htmlspecialchars($emDisplayTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="entity-subtitle"><?= htmlspecialchars($emSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="entity-toolbar">
            <button type="button" onclick="modal.create()" class="entity-btn entity-btn--primary">
                Add <?= htmlspecialchars($emDisplayTitle, ENT_QUOTES, 'UTF-8') ?>
            </button>
            <span class="entity-meta" aria-live="polite">
                <?php if ($total > 0): ?>
                    <?= number_format($emFrom) ?>–<?= number_format($emTo) ?> of <?= number_format((int) $total) ?>
                <?php else: ?>
                    No rows
                <?php endif; ?>
            </span>
        </div>
    </header>

    <section class="entity-section" aria-labelledby="entity-table-heading">
        <div class="entity-section__head">
            <h2 id="entity-table-heading" class="entity-section__title">All records</h2>
            <span class="entity-section__hint">Sort by column header · <?= (int) $emLimit ?> rows per page</span>
        </div>

        <div class="entity-table-scroll">
            <table class="entity-table">
                <thead>
                    <tr>
                        <th scope="col" class="entity-th-actions">Actions</th>
                        <?php foreach ($config as $key => $meta): ?>
                            <?php if (($meta['showInList'] ?? true) === false) {
                                continue;
                            } ?>
                            <th scope="col">
                                <a href="<?= htmlspecialchars($meta['sortURL'] ?? '#', ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($meta['th_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    <?= htmlspecialchars($meta['arrow'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php if (($meta['filter'] ?? '') !== ''): ?>
                                    <?php
                                    $emFilterParam = (string) $meta['filter'];
                                    $emFilterRaw = $_GET[$emFilterParam] ?? null;
                                    $emFilterActive = $emFilterRaw !== null && trim((string) $emFilterRaw) !== '';
                                    ?>
                                    <button type="button"
                                        class="entity-th-filter<?= $emFilterActive ? ' entity-th-filter--active' : '' ?>"
                                        onclick="OpenFilters(event)"
                                        data-column="<?= htmlspecialchars($emFilterParam, ENT_QUOTES, 'UTF-8') ?>"
                                        title="Filter this column"
                                        aria-label="Filter <?= htmlspecialchars($meta['th_title'] ?? 'column', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $emFilterActive ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>>
                                        <?= $funnelIcon ?>
                                    </button>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <button type="button" onclick="modal.view(<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)" class="btn-secondary">
                                    Edit
                                </button>
                            </td>
                            <?php foreach ($config as $key => $cfg): ?>
                                <?php if (($cfg['showInList'] ?? true) === false) {
                                    continue;
                                } ?>
                                <td><?= htmlspecialchars((string) ($row[$cfg['value']] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="entity-footer">
            <span>Page <?= (int) $emPage ?> · adjust <code style="font-size:0.85em;">?limit=</code> in the URL to change page size</span>
            <span>Filters apply from column funnel icons</span>
        </div>
    </section>
</div>

<div id="filter-popup">
    <div id="filter-content"></div>
</div>

<div id="overlay">
    <div id="modal" class="<?= $emModalWide ? 'entity-modal--wide' : '' ?>">
        <div class="entity-modal__head">
            <div id="modal-h"></div>
            <button type="button" class="entity-modal__close" onclick="CloseModal()" aria-label="Close dialog">×</button>
        </div>
        <div id="modal-content"></div>
    </div>
</div>

<div id="add" style="display: none">
    <div id="add-h">Add <?= htmlspecialchars($emDisplayTitle, ENT_QUOTES, 'UTF-8') ?></div>
    <div id="add-c">
        <?php require $formView; ?>
    </div>
</div>

<div id="edit" style="display: none">
    <div id="edit-h">Edit <?= htmlspecialchars($emDisplayTitle, ENT_QUOTES, 'UTF-8') ?></div>
</div>

<script src="./src/assets/js/ViewBase.js"></script>
