<link rel="stylesheet" href="./src/assets/css/dashboard.css">
<link rel="stylesheet" href="./src/assets/css/inspect.css">

<?php
$inspectBase = 'index.php?page=inspect&action=index';
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
$fmtDt = static function (?string $sqlDt): string {
    if ($sqlDt === null || $sqlDt === '') {
        return '—';
    }
    $t = strtotime($sqlDt);

    return $t ? date('M j, Y g:i A', $t) : '—';
};
?>

<div class="dashboard-page inspect-page">
    <header class="dashboard-header">
        <div class="dashboard-header__titles">
            <h1 class="dashboard-title">Inspect offer</h1>
            <p class="dashboard-subtitle">
                Deep dive on clicks, conversions, and campaign assignments for one offer.
            </p>
        </div>
    </header>

    <section class="dash-section inspect-select-section" aria-labelledby="inspect-select-heading">
        <h2 id="inspect-select-heading" class="dash-section__title">Choose an offer</h2>
        <form class="inspect-offer-form" method="get" action="index.php">
            <input type="hidden" name="page" value="inspect">
            <input type="hidden" name="action" value="index">
            <label class="inspect-offer-form__label" for="inspect-offer-id">Offer</label>
            <select class="inspect-offer-form__select" id="inspect-offer-id" name="offer_id">
                <option value="">— Select —</option>
                <?php foreach ($offers as $o): ?>
                    <?php $oid = (int) ($o['id'] ?? 0); ?>
                    <option value="<?= $oid ?>" <?= $offerId === $oid ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) ($o['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        (<?= $fmtInt($oid) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="inspect-offer-form__btn">Load</button>
        </form>
    </section>

    <?php if ($offerId > 0 && $offer === null): ?>
        <p class="dash-empty" role="alert">Offer not found.</p>
    <?php elseif ($offer !== null && is_array($offer)): ?>

        <section class="inspect-offer-card dash-section" aria-labelledby="inspect-offer-meta-heading">
            <h2 id="inspect-offer-meta-heading" class="dash-section__title">
                <?= htmlspecialchars((string) ($offer['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="dash-section__lede">
                ID <?= $fmtInt((int) ($offer['id'] ?? 0)) ?>
                <?php if (!empty($offer['country'])): ?>
                    · Country <?= htmlspecialchars((string) $offer['country'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <?php if (!empty($offer['affiliate_program'])): ?>
                    · <?= htmlspecialchars((string) $offer['affiliate_program'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <?php if (!empty($offer['domain'])): ?>
                    · Site <?= htmlspecialchars((string) $offer['domain'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>
            <div class="inspect-offer-actions">
                <a class="inspect-link" href="index.php?page=offers&action=index&filter_id=<?= (int) ($offer['id'] ?? 0) ?>">Open in Offers list</a>
                <a class="inspect-link" href="index.php?page=reporting&action=index">Reporting</a>
            </div>
        </section>

        <?php if ($clickBounds !== null): ?>
            <section class="dash-section" aria-labelledby="inspect-bounds-heading">
                <h2 id="inspect-bounds-heading" class="dash-section__title">Click timeline</h2>
                <div class="kpi-grid inspect-mini-kpis">
                    <article class="kpi-card kpi-card--accent">
                        <span class="kpi-card__label">First click</span>
                        <span class="kpi-card__value inspect-kpi--sm"><?= htmlspecialchars($fmtDt($clickBounds['first_click']), ENT_QUOTES, 'UTF-8') ?></span>
                    </article>
                    <article class="kpi-card kpi-card--accent">
                        <span class="kpi-card__label">Last click</span>
                        <span class="kpi-card__value inspect-kpi--sm"><?= htmlspecialchars($fmtDt($clickBounds['last_click']), ENT_QUOTES, 'UTF-8') ?></span>
                    </article>
                    <article class="kpi-card">
                        <span class="kpi-card__label">Clicks (all time in DB)</span>
                        <span class="kpi-card__value"><?= $fmtInt((int) ($clickBounds['total_clicks'] ?? 0)) ?></span>
                    </article>
                </div>
            </section>
        <?php endif; ?>

        <section class="dash-section" aria-labelledby="inspect-periods-heading">
            <h2 id="inspect-periods-heading" class="dash-section__title">Performance by period</h2>
            <p class="dash-section__lede">
                Lifetime uses <?= htmlspecialchars((string) ($rangeLifetime['start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars((string) ($rangeLifetime['end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
                Last 7 / 30 days align with the dashboard presets (including today).
            </p>

            <div class="inspect-period-grid">
                <?php
                $periods = [
                    ['label' => 'Lifetime', 'stats' => $statsLifetime, 'key' => 'life'],
                    ['label' => 'Last 7 days', 'stats' => $stats7, 'key' => '7d'],
                    ['label' => 'Last 30 days', 'stats' => $stats30, 'key' => '30d'],
                ];
                ?>
                <?php foreach ($periods as $block): ?>
                    <?php $s = $block['stats']; ?>
                    <div class="inspect-period-card" data-period="<?= htmlspecialchars($block['key'], ENT_QUOTES, 'UTF-8') ?>">
                        <h3 class="inspect-period-card__title"><?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="kpi-grid">
                            <article class="kpi-card kpi-card--accent">
                                <span class="kpi-card__label">Clicks</span>
                                <span class="kpi-card__value"><?= $fmtInt((int) ($s['total_clicks'] ?? 0)) ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">Conversions</span>
                                <span class="kpi-card__value"><?= $fmtInt((int) ($s['total_conversions'] ?? 0)) ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">Conv. rate</span>
                                <span class="kpi-card__value"><?= $fmtPct($s['cr'] !== null ? (float) $s['cr'] : null) ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">Rejection rate</span>
                                <span class="kpi-card__value"><?= $fmtPct($s['rejection_rate'] !== null ? (float) $s['rejection_rate'] : null) ?></span>
                                <span class="kpi-card__hint">Rejected ÷ all conversion events</span>
                            </article>
                            <article class="kpi-card kpi-card--profit">
                                <span class="kpi-card__label">ROI</span>
                                <span class="kpi-card__value"><?= $fmtPct($s['roi'] !== null ? (float) $s['roi'] : null) ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">EPC (payout / click)</span>
                                <span class="kpi-card__value"><?= $s['epc'] !== null ? $fmtMoney((float) $s['epc']) : '—' ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">Cost</span>
                                <span class="kpi-card__value"><?= $fmtMoney((float) ($s['cost'] ?? 0)) ?></span>
                            </article>
                            <article class="kpi-card">
                                <span class="kpi-card__label">Revenue (all statuses)</span>
                                <span class="kpi-card__value"><?= $fmtMoney((float) ($s['revenue'] ?? 0)) ?></span>
                            </article>
                            <article class="kpi-card kpi-card--profit">
                                <span class="kpi-card__label">Profit</span>
                                <span class="kpi-card__value"><?= $fmtMoney((float) ($s['profit'] ?? 0)) ?></span>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($statsLifetime !== null): ?>
            <section class="dash-section" aria-labelledby="inspect-lifetime-status-heading">
                <h2 id="inspect-lifetime-status-heading" class="dash-section__title">Lifetime conversion breakdown</h2>
                <p class="dash-section__lede">Counts and payout sums for this offer (same window as lifetime KPIs above).</p>
                <div class="status-grid">
                    <article class="status-card">
                        <h3 class="status-card__title">Open</h3>
                        <dl class="status-card__stats">
                            <div><dt>Count</dt><dd><?= $fmtInt((int) ($statsLifetime['open_count'] ?? 0)) ?></dd></div>
                            <div><dt>Sum</dt><dd><?= $fmtMoney((float) ($statsLifetime['open_sum'] ?? 0)) ?></dd></div>
                        </dl>
                    </article>
                    <article class="status-card">
                        <h3 class="status-card__title">Confirmed</h3>
                        <dl class="status-card__stats">
                            <div><dt>Count</dt><dd><?= $fmtInt((int) ($statsLifetime['confirmed_count'] ?? 0)) ?></dd></div>
                            <div><dt>Sum</dt><dd><?= $fmtMoney((float) ($statsLifetime['confirmed_sum'] ?? 0)) ?></dd></div>
                        </dl>
                    </article>
                    <article class="status-card">
                        <h3 class="status-card__title">Paid</h3>
                        <dl class="status-card__stats">
                            <div><dt>Count</dt><dd><?= $fmtInt((int) ($statsLifetime['paid_count'] ?? 0)) ?></dd></div>
                            <div><dt>Sum</dt><dd><?= $fmtMoney((float) ($statsLifetime['paid_sum'] ?? 0)) ?></dd></div>
                        </dl>
                    </article>
                    <article class="status-card status-card--muted">
                        <h3 class="status-card__title">Rejected</h3>
                        <dl class="status-card__stats">
                            <div><dt>Count</dt><dd><?= $fmtInt((int) ($statsLifetime['rejected_count'] ?? 0)) ?></dd></div>
                            <div><dt>Sum</dt><dd><?= $fmtMoney((float) ($statsLifetime['rejected_sum'] ?? 0)) ?></dd></div>
                        </dl>
                    </article>
                </div>
            </section>
        <?php endif; ?>

        <section class="dash-section" aria-labelledby="inspect-geo-heading">
            <h2 id="inspect-geo-heading" class="dash-section__title">Top countries by clicks</h2>
            <div class="inspect-geo-grid">
                <div class="inspect-geo-col">
                    <h3 class="inspect-geo-col__title">Lifetime</h3>
                    <?php if (count($topCountriesLifetime) === 0): ?>
                        <p class="dash-empty dash-empty--inline">No clicks in range.</p>
                    <?php else: ?>
                        <ol class="inspect-geo-list">
                            <?php foreach ($topCountriesLifetime as $i => $g): ?>
                                <li>
                                    <span class="inspect-geo-list__rank"><?= (int) $i + 1 ?></span>
                                    <span class="inspect-geo-list__name"><?= htmlspecialchars((string) ($g['country'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="inspect-geo-list__n"><?= $fmtInt((int) ($g['clicks'] ?? 0)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div class="inspect-geo-col">
                    <h3 class="inspect-geo-col__title">Last 30 days</h3>
                    <?php if (count($topCountries30) === 0): ?>
                        <p class="dash-empty dash-empty--inline">No clicks in range.</p>
                    <?php else: ?>
                        <ol class="inspect-geo-list">
                            <?php foreach ($topCountries30 as $i => $g): ?>
                                <li>
                                    <span class="inspect-geo-list__rank"><?= (int) $i + 1 ?></span>
                                    <span class="inspect-geo-list__name"><?= htmlspecialchars((string) ($g['country'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="inspect-geo-list__n"><?= $fmtInt((int) ($g['clicks'] ?? 0)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="dash-section" aria-labelledby="inspect-campaigns-heading">
            <h2 id="inspect-campaigns-heading" class="dash-section__title">Campaigns</h2>
            <p class="dash-section__lede">
                <strong>Assignment active</strong> means this offer is on the campaign with <code>cap &gt; 0</code> in <code>campaign_offers</code>.
                <strong>Capacity left</strong> means views are still below cap. External IDs come from <code>campaign_external_ids</code> (internal campaign id).
            </p>

            <div class="table-scroll">
                <table class="dash-table inspect-campaign-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Traffic</th>
                            <th class="num">Clicks (life)</th>
                            <th class="num">7d</th>
                            <th class="num">30d</th>
                            <th>Assignment</th>
                            <th class="num">Cap / views</th>
                            <th>External IDs</th>
                            <th>Last click</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaignRows as $row): ?>
                            <?php
                            $cid = (int) ($row['campaign_id'] ?? 0);
                            $cname = (string) ($row['campaign_name'] ?? '');
                            if ($cname === '') {
                                $cname = $cid > 0 ? 'Campaign #' . $cid : (string) ($row['click_campaign_ref'] ?? 'Unknown ref');
                            }
                            $uuid = trim((string) ($row['campaign_uuid'] ?? ''));
                            $campHref = '';
                            if ($cid > 0) {
                                $campHref = 'index.php?page=campaigns&action=index&filter_id=' . $cid;
                            } elseif ($uuid !== '') {
                                $campHref = 'index.php?page=campaigns&action=index&filter_uuid=' . rawurlencode($uuid);
                            }
                            $active = (int) ($row['assignment_active'] ?? 0) === 1;
                            $hasCap = (int) ($row['has_remaining_cap'] ?? 0) === 1;
                            $cap = $row['cap'];
                            $cv = $row['current_views'];
                            ?>
                            <tr>
                                <td>
                                    <?php if ($campHref !== ''): ?>
                                        <a class="inspect-table-link" href="<?= htmlspecialchars($campHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                    <?php if (!empty($row['tester'])): ?>
                                        <span class="inspect-pill inspect-pill--tester">Tester</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['traffic_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= $fmtInt((int) ($row['clicks_lifetime'] ?? 0)) ?></td>
                                <td class="num"><?= $fmtInt((int) ($row['clicks_7d'] ?? 0)) ?></td>
                                <td class="num"><?= $fmtInt((int) ($row['clicks_30d'] ?? 0)) ?></td>
                                <td>
                                    <?php if ($cap !== null): ?>
                                        <?php if ($active): ?>
                                            <span class="inspect-badge inspect-badge--ok">Active</span>
                                        <?php else: ?>
                                            <span class="inspect-badge inspect-badge--off">No cap / paused</span>
                                        <?php endif; ?>
                                        <?php if ($active && !$hasCap): ?>
                                            <span class="inspect-badge inspect-badge--warn">At cap</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inspect-badge inspect-badge--na">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num">
                                    <?php if ($cap !== null): ?>
                                        <?= $fmtInt((int) $cv) ?> / <?= $fmtInt((int) $cap) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="inspect-ext-cell"><?= htmlspecialchars((string) ($row['external_ids'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="inspect-dt-cell"><?= htmlspecialchars($fmtDt(isset($row['last_click']) ? (string) $row['last_click'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($campaignRows) === 0): ?>
                    <p class="dash-empty">No campaign-level rows yet (no clicks and no assignments).</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if (count($orphanAssignments) > 0): ?>
            <section class="dash-section inspect-warn-section" aria-labelledby="inspect-orphan-heading">
                <h2 id="inspect-orphan-heading" class="dash-section__title">Data check</h2>
                <p class="dash-section__lede">
                    These <code>campaign_offers</code> rows do not match a campaign record (uuid/id). You may want to fix or remove them.
                </p>
                <ul class="inspect-orphan-list">
                    <?php foreach ($orphanAssignments as $oa): ?>
                        <li>
                            <code><?= htmlspecialchars((string) ($oa['raw_campaign_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                            · cap <?= $fmtInt((int) ($oa['cap'] ?? 0)) ?>
                            · views <?= $fmtInt((int) ($oa['current_views'] ?? 0)) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>
