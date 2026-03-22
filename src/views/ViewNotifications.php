<?php
/**
 * @var list<array<string, mixed>> $notifications
 * @var int $total
 * @var int $page
 * @var int $perPage
 * @var int $totalPages
 * @var string $q
 * @var list<int> $allowedPerPage
 */

/**
 * @param array<string, scalar|null> $overrides
 */
$notificationsUrl = static function (string $q, int $perPage, int $page, array $overrides = []): string {
    $query = array_merge([
        'page' => 'notifications',
        'action' => 'index',
        'q' => $q,
        'per' => $perPage,
        'p' => $page,
    ], $overrides);
    if (($query['q'] ?? '') === '') {
        unset($query['q']);
    }

    return 'index.php?' . http_build_query($query);
};

$fromRow = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$toRow = $total > 0 ? min($total, $page * $perPage) : 0;

$statusClassMap = [
    'open' => 'notif-status--open',
    'confirmed' => 'notif-status--confirmed',
    'paid' => 'notif-status--paid',
    'rejected' => 'notif-status--rejected',
    'delayed' => 'notif-status--delayed',
];
?>

<link rel="stylesheet" href="./src/assets/css/notifications.css">

<div class="notifications-page">
    <header class="notifications-header">
        <div class="notifications-header__titles">
            <h1 class="notifications-title">Notifications</h1>
            <p class="notifications-subtitle">
                Conversion feed from postbacks — search by click ID, commission id, offer, campaign, or affiliate. Newest first.
            </p>
        </div>

        <form class="notifications-toolbar" method="get" action="index.php" role="search">
            <input type="hidden" name="page" value="notifications">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="per" value="<?= (int) $perPage ?>">
            <input type="hidden" name="p" value="1">

            <label class="sr-only" for="notif-search">Search conversions</label>
            <input
                type="search"
                id="notif-search"
                name="q"
                class="notifications-search"
                placeholder="Search click ID, commission id, offer, campaign…"
                value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="off"
                maxlength="120"
            >
            <button type="submit" class="notifications-search-btn">Search</button>
        </form>
    </header>

    <section class="notif-section" aria-labelledby="notif-table-heading">
        <div class="notif-section__head">
            <h2 id="notif-table-heading" class="notif-section__title">Conversions</h2>
            <div class="notif-section__meta">
                <?php if ($total > 0): ?>
                    <span class="notif-count"><?= number_format($fromRow) ?>–<?= number_format($toRow) ?> of <?= number_format($total) ?></span>
                <?php else: ?>
                    <span class="notif-count">No rows</span>
                <?php endif; ?>

                <nav class="notif-per-page" aria-label="Rows per page">
                    <?php foreach ($allowedPerPage as $n): ?>
                        <?php $active = $perPage === $n; ?>
                        <a
                            class="notif-per-page__link<?= $active ? ' is-active' : '' ?>"
                            href="<?= htmlspecialchars($notificationsUrl($q, $perPage, $page, ['per' => $n, 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>"
                        ><?= (int) $n ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <div class="notif-table-scroll">
            <table class="notif-table">
                <thead>
                    <tr>
                        <th scope="col">Click ID</th>
                        <th scope="col">Event</th>
                        <th scope="col">Offer</th>
                        <th scope="col">Campaign</th>
                        <th scope="col">Affiliate</th>
                        <th scope="col">Status</th>
                        <th scope="col">Commission id</th>
                        <th scope="col" class="notif-table__num">Commission</th>
                        <th scope="col" class="notif-table__num">Sale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($notifications) === 0): ?>
                        <tr>
                            <td class="notif-table__empty" colspan="9">
                                <?php if ($q !== ''): ?>
                                    No conversions match your search.
                                <?php else: ?>
                                    No conversions yet.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <?php
                            $status = strtolower((string) ($n['status'] ?? ''));
                            $statusClass = $statusClassMap[$status] ?? 'notif-status--unknown';
                            ?>
                            <tr class="<?= !empty($n['is_read']) ? '' : 'notif-row--unread' ?>">
                                <td class="notif-cell--mono"><?= htmlspecialchars((string) ($n['public_click_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($n['event_type'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($n['offer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($n['campaign_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($n['affiliate_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="notif-status <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars(strtoupper($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="notif-cell--mono"><?= htmlspecialchars((string) ($n['commission_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="notif-table__num notif-revenue"><?= number_format((float) ($n['revenue'] ?? 0), 2) ?> <?= htmlspecialchars((string) ($n['currency'] ?? 'EUR'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="notif-table__num"><?php
                                    $sa = $n['sales_amount'] ?? null;
                                    echo ($sa !== null && $sa !== '') ? number_format((float) $sa, 2) . ' ' . htmlspecialchars((string) ($n['currency'] ?? 'EUR'), ENT_QUOTES, 'UTF-8') : '—';
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
            <nav class="notif-pagination" aria-label="Pagination">
                <?php
                $prevDisabled = $page <= 1;
                $nextDisabled = $page >= $totalPages;
                ?>
                <a
                    class="notif-page-btn<?= $prevDisabled ? ' is-disabled' : '' ?>"
                    href="<?= $prevDisabled ? '#' : htmlspecialchars($notificationsUrl($q, $perPage, $page, ['p' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
                    <?= $prevDisabled ? ' aria-disabled="true" tabindex="-1"' : '' ?>
                >Previous</a>
                <span class="notif-page-indicator">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>
                <a
                    class="notif-page-btn<?= $nextDisabled ? ' is-disabled' : '' ?>"
                    href="<?= $nextDisabled ? '#' : htmlspecialchars($notificationsUrl($q, $perPage, $page, ['p' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>"
                    <?= $nextDisabled ? ' aria-disabled="true" tabindex="-1"' : '' ?>
                >Next</a>
            </nav>
        <?php endif; ?>
    </section>
</div>

<script src="./src/assets/js/notifications.js" defer></script>
