<?php

/** @var string $defaultDatePreset */
/** @var string $defaultDateFrom */
/** @var string $defaultDateTo */
/** @var list<string> $defaultGroupBy */
/** @var array<string, string> $availableGroups */
/** @var string $apiEndpoint */

$rangePresets = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'last7' => 'Last 7 days',
    'this_week' => 'This week',
    'last_week' => 'Last week',
    'this_month' => 'This month',
    'last30' => 'Last 30 days',
    'last_month' => 'Last month',
    'this_year' => 'This year',
    'last_year' => 'Last year',
    'all_time' => 'All time',
    'custom' => 'Custom range',
];

$metricColumns = [
    'clicks' => 'Clicks',
    'total_conversions' => 'Total conv.',
    'open_conversions' => 'Open #',
    'open_conversions_sum' => 'Open $',
    'confirmed_conversions' => 'Confirmed #',
    'confirmed_conversions_sum' => 'Confirmed $',
    'rejected_conversions' => 'Rejected #',
    'rejected_conversions_sum' => 'Rejected $',
    'paid_conversions' => 'Paid #',
    'paid_conversions_sum' => 'Paid $',
    'revenue' => 'Revenue',
    'spent' => 'Spent',
    'profit' => 'Profit',
    'cr' => 'CR',
    'roi' => 'ROI',
    'avg_payout' => 'Avg payout',
];

$groupLabelsJson = htmlspecialchars(json_encode($availableGroups, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$metricKeysJson = htmlspecialchars(json_encode(array_keys($metricColumns), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$defaultGroupByJson = htmlspecialchars(json_encode($defaultGroupBy, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<link rel="stylesheet" href="./src/assets/css/reporting.css">

<div
    class="reporting-page"
    id="reporting-root"
    data-api-endpoint="<?= htmlspecialchars($apiEndpoint, ENT_QUOTES, 'UTF-8') ?>"
    data-default-date-preset="<?= htmlspecialchars($defaultDatePreset, ENT_QUOTES, 'UTF-8') ?>"
    data-default-date-from="<?= htmlspecialchars($defaultDateFrom, ENT_QUOTES, 'UTF-8') ?>"
    data-default-date-to="<?= htmlspecialchars($defaultDateTo, ENT_QUOTES, 'UTF-8') ?>"
    data-default-sort-by="clicks"
    data-default-sort-dir="desc"
    data-default-group-by="<?= $defaultGroupByJson ?>"
    data-group-labels="<?= $groupLabelsJson ?>"
    data-metric-keys="<?= $metricKeysJson ?>"
>
    <header class="reporting-header">
        <div class="reporting-header__titles">
            <h1 class="reporting-title">Reporting</h1>
            <p class="reporting-subtitle">
                Apply filters to load level 1 only. Expand rows to fetch deeper levels lazily.
            </p>
        </div>
    </header>

    <section class="rep-section rep-section--filters" aria-labelledby="rep-filters-heading">
        <h2 id="rep-filters-heading" class="rep-section__title">Filters</h2>
        <p class="rep-section__lede">
            Filters do not auto-reload the report. Click <strong>Apply report</strong> to fetch data.
        </p>

        <div class="rep-filters">
            <div class="rep-filters__toolbar">
                <div class="rep-field rep-field--range">
                    <label class="rep-field__label" for="rep-date-preset">Date range</label>
                    <select id="rep-date-preset" class="rep-field__select rep-field__select--single">
                        <?php foreach ($rangePresets as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $defaultDatePreset === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rep-field rep-field--custom-range" id="rep-custom-range-wrap">
                    <label class="rep-field__label" for="rep-date-from">Custom dates</label>
                    <div class="range-custom">
                        <input type="date" id="rep-date-from" class="range-custom__input" value="<?= htmlspecialchars($defaultDateFrom, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="date" id="rep-date-to" class="range-custom__input" value="<?= htmlspecialchars($defaultDateTo, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="rep-field rep-field--apply">
                    <span class="rep-field__label">&nbsp;</span>
                    <button type="button" id="rep-apply-btn" class="range-custom__btn rep-apply-wide">Apply report</button>
                </div>
            </div>

            <div class="rep-filters__grid rep-filters__grid--dims">
                <div class="rep-field">
                    <label class="rep-field__label" for="rep-group-select">Grouping selection</label>
                    <select id="rep-group-select" class="rep-field__select" multiple size="6">
                        <?php foreach ($availableGroups as $groupKey => $groupLabel): ?>
                            <option value="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($groupKey, $defaultGroupBy, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rep-field">
                    <span class="rep-field__label" id="rep-group-order-label">Grouping order (drag to reorder)</span>
                    <ul id="rep-group-order" class="group-order" aria-labelledby="rep-group-order-label">
                        <?php foreach ($defaultGroupBy as $groupKey): ?>
                            <li data-value="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($availableGroups[$groupKey] ?? $groupKey, ENT_QUOTES, 'UTF-8') ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <nav class="range-pills" aria-label="Quick range presets">
                <?php foreach (array_diff_key($rangePresets, ['custom' => true]) as $key => $label): ?>
                    <button
                        type="button"
                        class="range-pill range-pill--btn<?= $defaultDatePreset === $key ? ' is-active' : '' ?>"
                        data-range-preset="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                    ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <section class="rep-section" aria-labelledby="rep-table-heading">
        <div class="rep-section__head">
            <h2 id="rep-table-heading" class="rep-section__title">Results</h2>
            <p class="rep-range-line" id="rep-active-summary">No report loaded.</p>
        </div>

        <div id="rep-global-progress" class="rep-progress is-hidden" aria-live="polite" aria-hidden="true">
            <div class="rep-progress__track" aria-hidden="true">
                <span class="rep-progress__bar"></span>
            </div>
            <span id="rep-global-progress-text" class="rep-progress__text">Loading report...</span>
        </div>

        <div class="rep-table-scroll">
            <table class="rep-table">
                <thead>
                    <tr>
                        <th class="rep-th-marker" scope="col">±</th>
                        <th class="rep-th-group" scope="col" aria-sort="none">
                            <button type="button" class="rep-sort-btn" data-sort-key="group_name" aria-pressed="false">
                                Group
                                <span class="rep-sort-ind" aria-hidden="true">↕</span>
                            </button>
                        </th>
                        <?php foreach ($metricColumns as $key => $label): ?>
                            <th class="rep-th-num" scope="col" aria-sort="none">
                                <button type="button" class="rep-sort-btn" data-sort-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false">
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                    <span class="rep-sort-ind" aria-hidden="true">↕</span>
                                </button>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="rep-tbody">
                    <tr>
                        <td class="rep-empty" colspan="<?= count($metricColumns) + 2 ?>">
                            Configure filters, then click <strong>Apply report</strong>.
                        </td>
                    </tr>
                </tbody>
                <tfoot id="rep-tfoot" class="rep-tfoot is-hidden">
                    <tr class="rep-foot">
                        <td class="rep-td-marker rep-foot__marker">Σ</td>
                        <td class="rep-group-cell rep-foot__label">Total</td>
                        <?php foreach ($metricColumns as $key => $_label): ?>
                            <td class="rep-num" data-total-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">—</td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="./src/assets/js/reporting.js" defer></script>
