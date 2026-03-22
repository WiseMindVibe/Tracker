<?php

require_once __DIR__ . '/../models/ModelReporting.php';
require_once __DIR__ . '/../models/ModelDashboard.php';

class ControllerReporting
{
    public function index(): void
    {
        $rangeParam = isset($_GET['range']) ? (string) $_GET['range'] : 'last7';
        $customStart = isset($_GET['start']) ? (string) $_GET['start'] : null;
        $customEnd = isset($_GET['end']) ? (string) $_GET['end'] : null;

        $resolved = ModelDashboard::resolveDateRange($rangeParam, $customStart, $customEnd);
        $startDate = $resolved['start'];
        $endDate = $resolved['end'];
        $rangePreset = $resolved['preset'];

        $rawGroups = $_GET['groups'] ?? 'campaign,offer';
        if (!is_array($rawGroups)) {
            $rawGroups = explode(',', (string) $rawGroups);
        }

        $groups = [];
        foreach ($rawGroups as $g) {
            $g = trim((string) $g);
            if ($g !== '' && isset(ReportingModel::$groupsMap[$g])) {
                $groups[] = $g;
            }
        }
        $groups = array_values(array_unique($groups));
        if ($groups === []) {
            $groups = ['offer'];
        }

        $sort = isset($_GET['sort']) ? (string) $_GET['sort'] : 'total_clicks';
        $direction = strtoupper((string) ($_GET['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSort = [
            'total_clicks',
            'conversions_count',
            'conversions_sum',
            'open_amount',
            'open_sum',
            'confirm_amount',
            'confirm_sum',
            'paid_amount',
            'paid_sum',
            'reject_amount',
            'reject_sum',
            'spent',
            'revenue',
            'profit',
            'cr',
            'roi',
            'avg_pay',
            'rejection_rate',
        ];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'total_clicks';
        }

        $rows = ReportingModel::getReport($groups, $startDate, $endDate);
        $tree = $this->buildTree($rows, $groups);
        $this->sortTree($tree, $sort, $direction);

        $footerTotals = $tree !== [] ? $this->aggregateRootTotals($tree) : null;
        $footerAvgs = $tree !== [] ? $this->aggregateRootAvgs($tree) : null;

        require __DIR__ . '/../views/ViewReporting.php';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $groups
     * @return array<string, mixed>
     */
    private function buildTree(array $rows, array $groups): array
    {
        $tree = [];

        if ($groups === []) {
            return $tree;
        }

        foreach ($rows as $row) {
            $ref =& $tree;

            foreach ($groups as $g) {
                $raw = $row[$g] ?? null;
                if ($raw === null || $raw === '') {
                    $key = '(none)';
                } else {
                    $key = (string) $raw;
                }

                if (!isset($ref[$key])) {
                    $ref[$key] = [
                        '_stats' => $this->emptyStats(),
                        'children' => [],
                    ];
                }

                $stats =& $ref[$key]['_stats'];

                $stats['total_clicks'] += (int) ($row['total_clicks'] ?? 0);
                $stats['conversions_count'] += (int) ($row['conversions_count'] ?? 0);
                $stats['conversions_sum'] += (float) ($row['conversions_sum'] ?? 0);
                $stats['open_amount'] += (int) ($row['open_amount'] ?? 0);
                $stats['open_sum'] += (float) ($row['open_sum'] ?? 0);
                $stats['confirm_amount'] += (int) ($row['confirm_amount'] ?? 0);
                $stats['confirm_sum'] += (float) ($row['confirm_sum'] ?? 0);
                $stats['paid_amount'] += (int) ($row['paid_amount'] ?? 0);
                $stats['paid_sum'] += (float) ($row['paid_sum'] ?? 0);
                $stats['reject_amount'] += (int) ($row['reject_amount'] ?? 0);
                $stats['reject_sum'] += (float) ($row['reject_sum'] ?? 0);
                $stats['spent'] += (float) ($row['spent'] ?? 0);
                $stats['revenue'] += (float) ($row['revenue'] ?? 0);

                $ref =& $ref[$key]['children'];
            }
        }

        $this->calculateRatios($tree);

        return $tree;
    }

    /**
     * @return array<string, float|int|null>
     */
    private function emptyStats(): array
    {
        return [
            'total_clicks' => 0,
            'conversions_count' => 0,
            'conversions_sum' => 0.0,
            'open_amount' => 0,
            'open_sum' => 0.0,
            'confirm_amount' => 0,
            'confirm_sum' => 0.0,
            'paid_amount' => 0,
            'paid_sum' => 0.0,
            'reject_amount' => 0,
            'reject_sum' => 0.0,
            'spent' => 0.0,
            'revenue' => 0.0,
            'profit' => 0.0,
            'roi' => null,
            'cr' => 0.0,
            'avg_pay' => null,
            'rejection_rate' => null,
        ];
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function calculateRatios(array &$tree): void
    {
        foreach ($tree as &$node) {
            $this->finalizeStats($node['_stats']);
            if (!empty($node['children'])) {
                $this->calculateRatios($node['children']);
            }
        }
        unset($node);
    }

    /**
     * @param array<string, float|int|null> $stats
     */
    private function finalizeStats(array &$stats): void
    {
        $stats['profit'] = (float) $stats['revenue'] - (float) $stats['spent'];
        $spent = (float) $stats['spent'];
        $stats['roi'] = $spent > 0 ? ($stats['profit'] / $spent) * 100.0 : null;

        $clicks = (int) $stats['total_clicks'];
        $conv = (int) $stats['conversions_count'];
        $stats['cr'] = $clicks > 0 ? ($conv / $clicks) * 100.0 : 0.0;

        $openC = (int) $stats['open_amount'];
        $confC = (int) $stats['confirm_amount'];
        $paidC = (int) $stats['paid_amount'];
        $rejC = (int) $stats['reject_amount'];
        $nonRejectedPayouts = $openC + $confC + $paidC;
        $nonRejectedSum = (float) $stats['open_sum'] + (float) $stats['confirm_sum'] + (float) $stats['paid_sum'];

        if ($nonRejectedPayouts > 0) {
            $stats['avg_pay'] = $nonRejectedSum / $nonRejectedPayouts;
        } else {
            $stats['avg_pay'] = null;
        }

        if ($confC + $paidC === 0) {
            $den = $openC + $rejC;
            $stats['rejection_rate'] = $den > 0 ? ($rejC / $den) * 100.0 : null;
        } else {
            $den = $confC + $paidC + $rejC;
            $stats['rejection_rate'] = $den > 0 ? ($rejC / $den) * 100.0 : null;
        }
    }

    /**
     * Grand totals: sum additive metrics from top-level groups, then derive CR/ROI etc.
     *
     * @param array<string, mixed> $tree
     * @return array<string, float|int|null>
     */
    private function aggregateRootTotals(array $tree): array
    {
        $acc = $this->emptyStats();
        foreach ($tree as $node) {
            $s = $node['_stats'];
            $acc['total_clicks'] += (int) ($s['total_clicks'] ?? 0);
            $acc['conversions_count'] += (int) ($s['conversions_count'] ?? 0);
            $acc['conversions_sum'] += (float) ($s['conversions_sum'] ?? 0);
            $acc['open_amount'] += (int) ($s['open_amount'] ?? 0);
            $acc['open_sum'] += (float) ($s['open_sum'] ?? 0);
            $acc['confirm_amount'] += (int) ($s['confirm_amount'] ?? 0);
            $acc['confirm_sum'] += (float) ($s['confirm_sum'] ?? 0);
            $acc['paid_amount'] += (int) ($s['paid_amount'] ?? 0);
            $acc['paid_sum'] += (float) ($s['paid_sum'] ?? 0);
            $acc['reject_amount'] += (int) ($s['reject_amount'] ?? 0);
            $acc['reject_sum'] += (float) ($s['reject_sum'] ?? 0);
            $acc['spent'] += (float) ($s['spent'] ?? 0);
            $acc['revenue'] += (float) ($s['revenue'] ?? 0);
        }
        $this->finalizeStats($acc);

        return $acc;
    }

    /**
     * Simple arithmetic mean of each column across top-level groups (unweighted).
     *
     * @param array<string, mixed> $tree
     * @return array<string, float|int|null>
     */
    private function aggregateRootAvgs(array $tree): array
    {
        $keys = [
            'total_clicks', 'conversions_count', 'conversions_sum', 'open_amount', 'open_sum',
            'confirm_amount', 'confirm_sum', 'paid_amount', 'paid_sum', 'reject_amount', 'reject_sum',
            'spent', 'revenue', 'profit', 'cr', 'roi', 'avg_pay', 'rejection_rate',
        ];
        $nRoots = count($tree);
        if ($nRoots === 0) {
            return array_fill_keys($keys, null);
        }

        $nullable = ['roi', 'avg_pay', 'rejection_rate'];
        $out = [];
        foreach ($keys as $key) {
            $sum = 0.0;
            $cnt = 0;
            foreach ($tree as $node) {
                $v = $node['_stats'][$key] ?? null;
                if ($v === null && in_array($key, $nullable, true)) {
                    continue;
                }
                if ($v === null) {
                    continue;
                }
                $sum += (float) $v;
                $cnt++;
            }
            if ($cnt === 0) {
                $out[$key] = null;
            } else {
                $avg = $sum / $cnt;
                $out[$key] = in_array($key, [
                    'total_clicks', 'conversions_count', 'open_amount', 'confirm_amount',
                    'paid_amount', 'reject_amount',
                ], true) ? (int) round($avg) : $avg;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $tree
     */
    private function sortTree(array &$tree, string $column, string $direction): void
    {
        $nullableSort = ['roi', 'avg_pay', 'rejection_rate'];

        uasort($tree, function (array $a, array $b) use ($column, $direction, $nullableSort): int {
            $va = $a['_stats'][$column] ?? null;
            $vb = $b['_stats'][$column] ?? null;

            if (in_array($column, $nullableSort, true)) {
                if ($va === null && $vb === null) {
                    return 0;
                }
                if ($va === null) {
                    return 1;
                }
                if ($vb === null) {
                    return -1;
                }
            } else {
                $va = (float) ($va ?? 0);
                $vb = (float) ($vb ?? 0);
            }

            $desc = $direction === 'DESC';
            if ($desc) {
                return $vb <=> $va;
            }

            return $va <=> $vb;
        });

        foreach ($tree as &$node) {
            if (!empty($node['children'])) {
                $this->sortTree($node['children'], $column, $direction);
            }
        }
        unset($node);
    }
}
