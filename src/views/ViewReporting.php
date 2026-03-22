<?php

/** @var array<string, mixed> $tree */
/** @var list<string> $groups */
/** @var string $startDate */
/** @var string $endDate */
/** @var string $rangePreset */
/** @var string $sort */
/** @var string $direction */
/** @var array<string, float|int|null>|null $footerTotals */
/** @var array<string, float|int|null>|null $footerAvgs */

$reportBase = 'index.php?page=reporting&action=index';

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

$groupLabels = [
    'offer' => 'Offer',
    'campaign' => 'Campaign',
    'external_campaign' => 'External campaign ID',
    'affiliate' => 'Affiliate program',
    'device' => 'Device',
    'os' => 'OS',
    'os_version' => 'OS version',
    'browser' => 'Browser',
    'browser_version' => 'Browser version',
    'country' => 'Country',
    'region' => 'Region',
    'language' => 'Language',
    'connection_type' => 'Connection type',
    'isp' => 'ISP',
    'carrier' => 'Carrier',
    'zone' => 'Zone',
    'subzone' => 'Subzone',
    'banner' => 'Banner',
];

$preferredGroupOrder = [
    'campaign', 'offer', 'external_campaign', 'affiliate',
    'device', 'os', 'os_version', 'browser', 'browser_version',
    'country', 'region', 'language', 'connection_type', 'isp', 'carrier',
    'zone', 'subzone', 'banner',
];
$availableGroups = [];
foreach ($preferredGroupOrder as $g) {
    if (isset(ReportingModel::$groupsMap[$g])) {
        $availableGroups[] = $g;
    }
}
foreach (array_keys(ReportingModel::$groupsMap) as $g) {
    if (!in_array($g, $availableGroups, true)) {
        $availableGroups[] = $g;
    }
}

$columns = [
    'total_clicks' => ['label' => 'Clicks', 'hint' => 'Tracked clicks in range'],
    'conversions_count' => ['label' => 'Conv.', 'hint' => 'Conversions (open + confirmed + paid + rejected)'],
    'conversions_sum' => ['label' => 'Conv. sum', 'hint' => 'Payout sum for all conversion statuses'],
    'open_amount' => ['label' => 'Open #', 'hint' => 'Open conversions count'],
    'open_sum' => ['label' => 'Open $', 'hint' => 'Open payout sum'],
    'confirm_amount' => ['label' => 'Confirmed #', 'hint' => 'Confirmed count'],
    'confirm_sum' => ['label' => 'Confirmed $', 'hint' => 'Confirmed payout sum'],
    'paid_amount' => ['label' => 'Paid #', 'hint' => 'Paid count'],
    'paid_sum' => ['label' => 'Paid $', 'hint' => 'Paid payout sum'],
    'reject_amount' => ['label' => 'Rejected #', 'hint' => 'Rejected count'],
    'reject_sum' => ['label' => 'Rejected $', 'hint' => 'Rejected payout sum'],
    'spent' => ['label' => 'Spent', 'hint' => 'Click cost'],
    'revenue' => ['label' => 'Revenue', 'hint' => 'Open + confirmed + paid payouts (excl. rejected)'],
    'profit' => ['label' => 'Profit', 'hint' => 'Revenue − spent'],
    'cr' => ['label' => 'CR', 'hint' => 'Conversions ÷ clicks'],
    'roi' => ['label' => 'ROI', 'hint' => '(Profit ÷ spent) × 100'],
    'avg_pay' => ['label' => 'Avg pay', 'hint' => 'Mean payout on open + confirmed + paid'],
    'rejection_rate' => ['label' => 'Reject %', 'hint' => 'Open+rejected funnel, or confirmed+paid+rejected once any confirm/paid exists'],
];

/**
 * @param array<string, mixed> $columns
 */
function reporting_sort_url(
    string $column,
    string $currentSort,
    string $currentDir,
    string $startDate,
    string $endDate,
    string $rangePreset,
    array $groups
): string {
    $nextDir = (strcasecmp($currentSort, $column) === 0 && strtoupper($currentDir) === 'DESC') ? 'asc' : 'desc';
    $query = [
        'page' => 'reporting',
        'action' => 'index',
        'range' => $rangePreset,
        'start' => $startDate,
        'end' => $endDate,
        'groups' => implode(',', $groups),
        'sort' => $column,
        'direction' => $nextDir,
    ];
    return 'index.php?' . http_build_query($query);
}

function reporting_roi_marker(?float $roi): string
{
    $good = $roi !== null && $roi >= 100.0;
    $cls = $good ? 'rep-marker rep-marker--good' : 'rep-marker rep-marker--bad';
    $sym = $good ? '+' : '−';
    $title = $roi === null ? 'ROI —' : 'ROI ' . number_format($roi, 2) . '%';

    return '<span class="' . $cls . '" title="' . htmlspecialchars($title) . '">' . $sym . '</span>';
}

function reporting_td_classes(string $key, mixed $raw): string
{
    $base = 'rep-num';
    switch ($key) {
        case 'spent':
        case 'reject_sum':
            return $base . ' rep-val--cost';
        case 'revenue':
            if ($raw === null) {
                return $base;
            }
            $f = (float) $raw;
            if ($f > 0) {
                return $base . ' rep-val--pos';
            }
            if ($f < 0) {
                return $base . ' rep-val--neg';
            }

            return $base . ' rep-val--muted';
        case 'profit':
            if ($raw === null) {
                return $base;
            }
            $f = (float) $raw;
            if ($f > 0) {
                return $base . ' rep-val--pos';
            }
            if ($f < 0) {
                return $base . ' rep-val--neg';
            }

            return $base . ' rep-val--muted';
        case 'cr':
            if ($raw === null) {
                return $base;
            }
            $x = (float) $raw;
            if ($x < 0.05) {
                return $base . ' rep-val--neg';
            }
            if ($x <= 0.2) {
                return $base . ' rep-val--warn';
            }

            return $base . ' rep-val--pos';
        case 'roi':
            if ($raw === null) {
                return $base . ' rep-val--neg';
            }
            $x = (float) $raw;
            if ($x < 100.0) {
                return $base . ' rep-val--neg';
            }
            if ($x <= 350.0) {
                return $base . ' rep-val--warn';
            }

            return $base . ' rep-val--pos';
        default:
            return $base;
    }
}

function reporting_format_cell(string $key, mixed $value): string
{
    $moneyKeys = [
        'conversions_sum', 'open_sum', 'confirm_sum', 'paid_sum',
        'spent', 'revenue', 'profit', 'avg_pay',
    ];
    $pctKeys = ['cr', 'roi', 'rejection_rate'];
    $intKeys = [
        'total_clicks', 'conversions_count', 'open_amount', 'confirm_amount',
        'paid_amount', 'reject_amount',
    ];

    if ($key === 'reject_sum') {
        if ($value === null) {
            return '—';
        }
        $v = -(float) abs((float) $value);

        return '$' . number_format($v, 2);
    }

    if (in_array($key, $pctKeys, true)) {
        if ($value === null) {
            return '—';
        }
        return number_format((float) $value, 2) . '%';
    }
    if (in_array($key, $moneyKeys, true)) {
        if ($value === null) {
            return '—';
        }
        return '$' . number_format((float) $value, 2);
    }
    if (in_array($key, $intKeys, true)) {
        return number_format((int) $value);
    }

    return htmlspecialchars((string) $value);
}

/**
 * @param array<string, float|int|null> $stats
 * @param array<string, array{label: string, hint?: string}> $columns
 */
function reporting_render_footer_row(string $label, array $stats, array $columns, bool $showMarkerRoi): void
{
    echo '<tr class="rep-foot">';
    echo '<td class="rep-td-marker rep-foot__marker">';
    if ($showMarkerRoi) {
        $roi = isset($stats['roi']) && $stats['roi'] !== null ? (float) $stats['roi'] : null;
        echo reporting_roi_marker($roi);
    }
    echo '</td>';
    echo '<td class="rep-group-cell rep-foot__label">' . htmlspecialchars($label) . '</td>';
    foreach ($columns as $key => $_meta) {
        $raw = $stats[$key] ?? null;
        $cls = reporting_td_classes($key, $raw);
        echo '<td class="' . $cls . '" data-metric="' . htmlspecialchars($key) . '">';
        echo reporting_format_cell($key, $raw);
        echo '</td>';
    }
    echo '</tr>';
}

/**
 * @param array<string, mixed> $tree
 * @param array<string, array{label: string, hint?: string}> $columns
 */
function reporting_render_rows(array $tree, array $columns, int $level = 0): void
{
    foreach ($tree as $name => $node) {
        $hasChildren = !empty($node['children']);
        $levelClass = 'rep-row--level-' . $level;
        $childClass = $hasChildren ? ' rep-row--parent' : '';

        echo '<tr class="rep-row ' . $levelClass . $childClass . '" data-level="' . (int) $level . '"';

        if ($level > 0) {
            echo ' hidden';
        }
        echo '>';

        $roiVal = $node['_stats']['roi'] ?? null;
        $roiF = $roiVal !== null ? (float) $roiVal : null;
        echo '<td class="rep-td-marker">';
        echo reporting_roi_marker($roiF);
        echo '</td>';

        echo '<td class="rep-group-cell">';
        echo '<span class="rep-indent" style="width:' . (1.1 + $level * 1.1) . 'rem"></span>';
        if ($hasChildren) {
            echo '<button type="button" class="rep-toggle" aria-expanded="false" aria-label="Expand nested groups">▸</button>';
        } else {
            echo '<span class="rep-toggle-spacer"></span>';
        }
        echo '<span class="rep-group-name">' . htmlspecialchars((string) $name) . '</span>';
        echo '</td>';

        foreach ($columns as $key => $_meta) {
            $raw = $node['_stats'][$key] ?? null;
            $cls = reporting_td_classes($key, $raw);
            echo '<td class="' . $cls . '" data-metric="' . htmlspecialchars($key) . '">';
            echo reporting_format_cell($key, $raw);
            echo '</td>';
        }

        echo '</tr>';

        if ($hasChildren) {
            reporting_render_rows($node['children'], $columns, $level + 1);
        }
    }
}

?>

<link rel="stylesheet" href="./src/assets/css/reporting.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css"/>

<div
    class="reporting-page"
    id="reporting-root"
    data-start="<?= htmlspecialchars($startDate) ?>"
    data-end="<?= htmlspecialchars($endDate) ?>"
>
    <header class="reporting-header">
        <div class="reporting-header__titles">
            <h1 class="reporting-title">Reporting</h1>
            <p class="reporting-subtitle">
                Drill down by dimensions you choose — nested groups roll up clicks, conversions, and payouts for the selected range.
            </p>
        </div>

        <div class="reporting-toolbar">
            <nav class="range-pills" aria-label="Date range presets">
                <?php foreach ($rangeLinks as $key => $label): ?>
                    <?php
                    $active = $rangePreset === $key;
                    $href = $reportBase
                        . '&range=' . rawurlencode($key)
                        . '&groups=' . rawurlencode(implode(',', $groups))
                        . '&sort=' . rawurlencode($sort)
                        . '&direction=' . rawurlencode(strtolower($direction));
                    ?>
                    <a class="range-pill<?= $active ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </nav>

            <form class="range-custom reporting-range-form" method="get" action="index.php">
                <input type="hidden" name="page" value="reporting">
                <input type="hidden" name="action" value="index">
                <input type="hidden" name="range" value="custom">
                <input type="hidden" name="groups" id="reporting-groups-hidden" value="<?= htmlspecialchars(implode(',', $groups)) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="direction" value="<?= htmlspecialchars(strtolower($direction)) ?>">
                <label class="sr-only" for="rep-range">Date range</label>
                <input type="text" id="rep-range" class="range-custom__input range-custom__input--picker" placeholder="Custom range" readonly>
                <input type="hidden" name="start" id="rep-start-hidden" value="<?= htmlspecialchars($startDate) ?>">
                <input type="hidden" name="end" id="rep-end-hidden" value="<?= htmlspecialchars($endDate) ?>">
                <button type="submit" class="range-custom__btn">Apply</button>
            </form>
        </div>
    </header>

    <section class="rep-section" aria-labelledby="rep-filters-heading">
        <h2 id="rep-filters-heading" class="rep-section__title">Dimensions &amp; order</h2>
        <p class="rep-section__lede">
            Pick one or more breakdowns, then drag to set parent → child order (e.g. campaign, then offer). Submit with <strong>Apply report</strong> to refresh.
        </p>

        <form method="get" action="index.php" class="rep-filters" id="rep-main-form">
            <input type="hidden" name="page" value="reporting">
            <input type="hidden" name="action" value="index">
            <input type="hidden" name="range" value="<?= htmlspecialchars($rangePreset) ?>">
            <input type="hidden" name="start" value="<?= htmlspecialchars($startDate) ?>">
            <input type="hidden" name="end" value="<?= htmlspecialchars($endDate) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="direction" value="<?= htmlspecialchars(strtolower($direction)) ?>">
            <input type="hidden" name="groups" id="groupsInput" value="<?= htmlspecialchars(implode(',', $groups)) ?>">

            <div class="rep-filters__grid">
                <div class="rep-field">
                    <label class="rep-field__label" for="groupSelect">Breakdowns</label>
                    <select id="groupSelect" class="rep-field__select" multiple size="8">
                        <?php foreach ($availableGroups as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= in_array($g, $groups, true) ? ' selected' : '' ?>>
                                <?= htmlspecialchars($groupLabels[$g] ?? ucfirst(str_replace('_', ' ', $g))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rep-field">
                    <span class="rep-field__label" id="groupOrderLabel">Nesting order (drag)</span>
                    <ul id="groupOrder" class="group-order" aria-labelledby="groupOrderLabel">
                        <?php foreach ($groups as $g): ?>
                            <li data-value="<?= htmlspecialchars($g) ?>">
                                <?= htmlspecialchars($groupLabels[$g] ?? ucfirst(str_replace('_', ' ', $g))) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="rep-field rep-field--actions">
                    <span class="rep-field__label">&nbsp;</span>
                    <button type="submit" class="range-custom__btn rep-apply-wide">Apply report</button>
                </div>
            </div>
        </form>
    </section>

    <section class="rep-section" aria-labelledby="rep-table-heading">
        <div class="rep-section__head">
            <h2 id="rep-table-heading" class="rep-section__title">Results</h2>
            <p class="rep-range-line">
                <time datetime="<?= htmlspecialchars($startDate) ?>"><?= htmlspecialchars($startDate) ?></time>
                <?php if ($startDate !== $endDate): ?>
                    –
                    <time datetime="<?= htmlspecialchars($endDate) ?>"><?= htmlspecialchars($endDate) ?></time>
                <?php endif; ?>
                <span class="rep-dim-chip"><?= htmlspecialchars(implode(' → ', array_map(
                    static fn(string $g): string => $groupLabels[$g] ?? $g,
                    $groups
                ))) ?></span>
            </p>
        </div>

        <div class="rep-table-scroll">
            <table class="rep-table">
                <thead>
                    <tr>
                        <th class="rep-th-marker" scope="col" title="ROI vs 100%: + green, − red">±</th>
                        <th class="rep-th-group" scope="col">Group</th>
                        <?php foreach ($columns as $key => $col): ?>
                            <th class="rep-th-num" scope="col">
                                <a
                                    class="rep-sort"
                                    href="<?= htmlspecialchars(reporting_sort_url(
                                        $key,
                                        $sort,
                                        $direction,
                                        $startDate,
                                        $endDate,
                                        $rangePreset,
                                        $groups
                                    )) ?>"
                                    title="<?= htmlspecialchars($col['hint'] ?? '') ?>"
                                >
                                    <?= htmlspecialchars($col['label']) ?>
                                    <?php if (strcasecmp($sort, $key) === 0): ?>
                                        <span class="rep-sort-dir"><?= strtoupper($direction) === 'DESC' ? '↓' : '↑' ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="rep-tbody">
                    <?php if ($tree === []): ?>
                        <tr>
                            <td class="rep-empty" colspan="<?= count($columns) + 2 ?>">No rows for this range.</td>
                        </tr>
                    <?php else: ?>
                        <?php reporting_render_rows($tree, $columns, 0); ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($tree !== [] && $footerTotals !== null && $footerAvgs !== null): ?>
                    <tfoot class="rep-tfoot">
                        <?php reporting_render_footer_row('Σ Total', $footerTotals, $columns, true); ?>
                        <?php reporting_render_footer_row('x̄ Average (top level)', $footerAvgs, $columns, true); ?>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="./src/assets/js/reporting.js" defer></script>
