<link rel="stylesheet" href="./src/assets/css/dashboard.css">
<!-- Litepicker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css"/>
<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>

<?php
$dashBase = 'index.php?page=dashboard&action=index';
$rangeLinks = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This week',
    'last7' => 'Last 7 days',
    'this_month' => 'This month',
    'last30' => 'Last 30 days',
    'previous_month' => 'Previous month',
    'this_year' => 'This year',
    'last_year' => 'Last year',
    'all_time' => 'All time',
];

$fmtMoney = static function (float $n): string {
    return '$' . number_format($n, 2);
};
$fmtPct = static function (?float $n, int $decimals = 1): string {
    if ($n === null) {
        return '—';
    }
    return number_format($n, $decimals) . '%';
};
$fmtInt = static function (int $n): string {
    return number_format($n);
};
?>

<div class="dashboard-page">
    <header class="dashboard-header">
        <div class="dashboard-header__titles">
            <h1 class="dashboard-title">Dashboard</h1>
            <p class="dashboard-subtitle">
                Performance for
                <time datetime="<?= htmlspecialchars($startDate) ?>"><?= htmlspecialchars($startDate) ?></time>
                <?php if ($startDate !== $endDate): ?>
                    –
                    <time datetime="<?= htmlspecialchars($endDate) ?>"><?= htmlspecialchars($endDate) ?></time>
                <?php endif; ?>
            </p>
        </div>

        <div class="dashboard-toolbar">
            <nav class="range-pills" aria-label="Date range presets">
                <?php foreach ($rangeLinks as $key => $label): ?>
                    <?php
                    $active = $rangePreset === $key;
                    $href = $dashBase . '&range=' . rawurlencode($key);
                    ?>
                    <a class="range-pill<?= $active ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="range-custom" method="get" action="index.php">
                <input type="hidden" name="page" value="dashboard">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="range" value="custom">
                <label class="sr-only" for="dash-start">Start date</label>
                <input 
    id="dash-range" 
    class="range-custom__input range-custom__input--picker"
    type="text" 
    placeholder="Select date range"
    readonly
>

<input type="hidden" name="start" id="dash-start-hidden">
<input type="hidden" name="end" id="dash-end-hidden">

                <button type="submit" class="range-custom__btn">Apply</button>
            </form>
        </div>
    </header>

    <section class="dash-section" aria-labelledby="dash-kpi-heading">
        <h2 id="dash-kpi-heading" class="dash-section__title">Overview</h2>
        <div class="kpi-grid">
            <article class="kpi-card kpi-card--accent">
                <span class="kpi-card__label">Clicks</span>
                <span class="kpi-card__value"><?= $fmtInt((int) $stats['total_clicks']) ?></span>
            </article>
            <article class="kpi-card">
                <span class="kpi-card__label">Conversions</span>
                <span class="kpi-card__value"><?= $fmtInt((int) $stats['total_conversions']) ?></span>
                <span class="kpi-card__hint">Open + confirmed + paid + rejected</span>
            </article>
            <article class="kpi-card kpi-card--profit">
                <span class="kpi-card__label">ROI</span>
                <span class="kpi-card__value"><?= $fmtPct($stats['roi'] !== null ? (float) $stats['roi'] : null) ?></span>
                <span class="kpi-card__hint">All statuses included in revenue</span>
            </article>
            <article class="kpi-card">
                <span class="kpi-card__label">Cost</span>
                <span class="kpi-card__value"><?= $fmtMoney((float) $stats['cost']) ?></span>
            </article>
            <article class="kpi-card">
                <span class="kpi-card__label">Revenue</span>
                <span class="kpi-card__value"><?= $fmtMoney((float) $stats['revenue']) ?></span>
            </article>
            <article class="kpi-card kpi-card--profit">
                <span class="kpi-card__label">Profit</span>
                <span class="kpi-card__value"><?= $fmtMoney((float) $stats['profit']) ?></span>
            </article>
        </div>
    </section>

    <section class="dash-section" aria-labelledby="dash-status-heading">
        <h2 id="dash-status-heading" class="dash-section__title">Conversions by status</h2>
        <p class="dash-section__lede">Count and payout sum per status in the selected range.</p>
        <div class="status-grid">
            <article class="status-card">
                <h3 class="status-card__title">Open</h3>
                <dl class="status-card__stats">
                    <div><dt>Count</dt><dd><?= $fmtInt((int) $stats['open_count']) ?></dd></div>
                    <div><dt>Sum</dt><dd><?= $fmtMoney((float) $stats['open_sum']) ?></dd></div>
                </dl>
            </article>
            <article class="status-card">
                <h3 class="status-card__title">Confirmed</h3>
                <dl class="status-card__stats">
                    <div><dt>Count</dt><dd><?= $fmtInt((int) $stats['confirmed_count']) ?></dd></div>
                    <div><dt>Sum</dt><dd><?= $fmtMoney((float) $stats['confirmed_sum']) ?></dd></div>
                </dl>
            </article>
            <article class="status-card">
                <h3 class="status-card__title">Paid</h3>
                <dl class="status-card__stats">
                    <div><dt>Count</dt><dd><?= $fmtInt((int) $stats['paid_count']) ?></dd></div>
                    <div><dt>Sum</dt><dd><?= $fmtMoney((float) $stats['paid_sum']) ?></dd></div>
                </dl>
            </article>
            <article class="status-card status-card--muted">
                <h3 class="status-card__title">Rejected</h3>
                <dl class="status-card__stats">
                    <div><dt>Count</dt><dd><?= $fmtInt((int) $stats['rejected_count']) ?></dd></div>
                    <div><dt>Sum</dt><dd><?= $fmtMoney((float) $stats['rejected_sum']) ?></dd></div>
                </dl>
            </article>
        </div>
    </section>

    <section class="dash-section" aria-labelledby="dash-aff-heading">
        <h2 id="dash-aff-heading" class="dash-section__title">By affiliate program</h2>
        <p class="dash-section__lede">Cost, profit, conversion rate, and ROI for each program in the selected range.</p>

        <?php if (count($affiliates) === 0): ?>
            <p class="dash-empty">No click data in this range.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th scope="col">Affiliate</th>
                            <th scope="col" class="num">Clicks</th>
                            <th scope="col" class="num">Conv.</th>
                            <th scope="col" class="num">CR</th>
                            <th scope="col" class="num">Cost</th>
                            <th scope="col" class="num">Profit</th>
                            <th scope="col" class="num">ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($affiliates as $row): ?>
                            <tr>
                                <th scope="row"><?= htmlspecialchars((string) $row['affiliate_name']) ?></th>
                                <td class="num"><?= $fmtInt((int) $row['clicks']) ?></td>
                                <td class="num"><?= $fmtInt((int) $row['conversions']) ?></td>
                                <td class="num"><?= $fmtPct((float) $row['cr'], 2) ?></td>
                                <td class="num"><?= $fmtMoney((float) $row['cost']) ?></td>
                                <td class="num <?= ((float) $row['profit'] >= 0) ? 'pos' : 'neg' ?>"><?= $fmtMoney((float) $row['profit']) ?></td>
                                <td class="num"><?= $fmtPct($row['roi'] !== null ? (float) $row['roi'] : null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dash-section" aria-labelledby="dash-top-heading">
        <h2 id="dash-top-heading" class="dash-section__title">Top offers</h2>
        <p class="dash-section__lede">
            Always uses the last 7 days (rolling): <strong><?= htmlspecialchars($topOffersRangeLabel) ?></strong>.
            Top 5 by ROI, CR, and profit.
        </p>

        <div class="top-offers-grid">
            <div class="top-offers-col">
                <h3 class="top-offers-col__title">By ROI</h3>
                <ol class="top-offers-list">
                    <?php foreach ($topOffers['by_roi'] as $i => $o): ?>
                        <li>
                            <span class="top-offers-list__rank"><?= (int) $i + 1 ?></span>
                            <div class="top-offers-list__body">
                                <span class="top-offers-list__name"><?= htmlspecialchars((string) $o['offer_name']) ?></span>
                                <span class="top-offers-list__meta">
                                    ROI <?= $fmtPct($o['roi'] !== null ? (float) $o['roi'] : null) ?>
                                    · <?= $fmtMoney((float) $o['profit']) ?> profit
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($topOffers['by_roi']) === 0): ?>
                    <p class="dash-empty dash-empty--inline">No data.</p>
                <?php endif; ?>
            </div>

            <div class="top-offers-col">
                <h3 class="top-offers-col__title">By CR</h3>
                <ol class="top-offers-list">
                    <?php foreach ($topOffers['by_cr'] as $i => $o): ?>
                        <li>
                            <span class="top-offers-list__rank"><?= (int) $i + 1 ?></span>
                            <div class="top-offers-list__body">
                                <span class="top-offers-list__name"><?= htmlspecialchars((string) $o['offer_name']) ?></span>
                                <span class="top-offers-list__meta">
                                    CR <?= $fmtPct((float) $o['cr'], 2) ?>
                                    · <?= $fmtInt((int) $o['clicks']) ?> clicks
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($topOffers['by_cr']) === 0): ?>
                    <p class="dash-empty dash-empty--inline">No data.</p>
                <?php endif; ?>
            </div>

            <div class="top-offers-col">
                <h3 class="top-offers-col__title">By profit</h3>
                <ol class="top-offers-list">
                    <?php foreach ($topOffers['by_profit'] as $i => $o): ?>
                        <li>
                            <span class="top-offers-list__rank"><?= (int) $i + 1 ?></span>
                            <div class="top-offers-list__body">
                                <span class="top-offers-list__name"><?= htmlspecialchars((string) $o['offer_name']) ?></span>
                                <span class="top-offers-list__meta">
                                    <?= $fmtMoney((float) $o['profit']) ?>
                                    · ROI <?= $fmtPct($o['roi'] !== null ? (float) $o['roi'] : null) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($topOffers['by_profit']) === 0): ?>
                    <p class="dash-empty dash-empty--inline">No data.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>



<script src="./src/assets/js/dashboard.js" defer></script>
