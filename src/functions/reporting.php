<?php 
function getOfferName($offer_id) {
    $stmt = db()->prepare("SELECT name FROM offers WHERE id = ?");
    $stmt->execute([$offer_id]);
    return $stmt->fetchColumn() ?: "Unknown Offer";
}

function getCampaignName($campaign_id) {
    $stmt = db()->prepare("SELECT name FROM campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    return $stmt->fetchColumn() ?: "Unknown Campaign";
}

function getGroupedClicks($start, $end, $groups = []) {

    $sql = "SELECT * FROM clicks WHERE created_at >= :start AND created_at <= :end";
    $stmt = db()->prepare($sql);

    $start_dt = $start . " 00:00:00";
    $end_dt   = $end . " 23:59:59";

    $stmt->execute([
        ":start" => $start_dt,
        ":end"   => $end_dt
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $final = [];

    foreach ($rows as $row) {

        // Fix: Replace IDs with names
        $row['offer_name']    = getOfferName($row['offer_id']);
        $row['campaign_name'] = getCampaignName($row['campaign_id']);

        // Determine group keys
        $keys = [];
        foreach ($groups as $g) {
            $keys[] = match($g) {
                "offer"    => $row['offer_name'],
                "campaign" => $row['campaign_name'],
                "os"       => $row['OS'],
                "browser"  => $row['browser'],
                default    => $row[$g] ?? "Unknown",
            };
        }

        // Insert into nested structure
        $ref =& $final;
        foreach ($keys as $k) {
            if (!isset($ref[$k])) {
                $ref[$k] = [
                    "_stats" => [
                        "clicks" => 0,
                        "conversions" => 0,
                        "revenue" => 0,
                        "cost" => 0,
                        "profit" => 0,
                        "cr" => 0,
                        "roi" => 0,
                        "reject_rate" => 0,
                        "approved_statuses" => 0,
                        "rejected" => 0,
                        "total_statuses" => 0,
                    ]
                ];
            }
            $ref =& $ref[$k];
        }

        // Update stats
        $ref["_stats"]["clicks"]++;

        $status = strtolower($row["status"]);

        // Count total statuses (open, confirmed, paid, rejected)
        if (in_array($status, ["open", "confirmed", "paid", "rejected"])) {
            $ref["_stats"]["total_statuses"]++;
        }

        // Count approved statuses (open + confirmed + paid)
        if (in_array($status, ["open", "confirmed", "paid"])) {
            $ref["_stats"]["approved_statuses"]++;
        }

        // Count rejected
        if ($status === "rejected") {
            $ref["_stats"]["rejected"]++;
        }

        // Actual conversions: only confirmed & paid
        if (in_array($status, ["confirmed", "paid"])) {
            $ref["_stats"]["conversions"]++;
            $ref["_stats"]["revenue"] += $row["payout"];
        }

    }
    // After all rows → compute CR, ROI, reject rate
    computeStatsRecursive($final);

    return $final;
}



function computeStatsRecursive(&$arr) {
    foreach ($arr as $k => &$v) {
        if ($k === "_stats") continue;

        computeStatsRecursive($v);

        $s = &$v["_stats"];

        $s["cr"] = $s["clicks"] > 0 ? round(($s["conversions"] / $s["clicks"]) * 100, 2) : 0;

        $s["roi"] = $s["cost"] > 0 
            ? round(($s["profit"] / $s["cost"]) * 100, 2)
            : 0;

        // Reject rate → percentage of non-conversions
        $s["reject_rate"] = ($s["total_statuses"] > 0)
            ? round(($s["rejected"] / $s["total_statuses"]) * 100, 2)
            : 0;



    }
}

// Assume $groupedClicks = getGroupedClicks($start, $end, $selectedGroups);
function renderGroupedRows($data, $parentId = null, $level = 0, &$counter = 1) {
    foreach ($data as $key => $value) {
        if ($key === "_stats") continue;

        $id = $counter++;
        $childKeys = array_filter(array_keys($value), fn($k) => $k !== "_stats");
        $hasChildren = !empty($childKeys);

        // Compute aggregated stats only for display
        $displayStats = $value["_stats"];
        if ($hasChildren) {
    $agg = computeFullAggregate($value);

    // compute CR, ROI, Reject rate
    $agg["cr"] = $agg["clicks"] > 0 ? round(($agg["conversions"] / $agg["clicks"]) * 100, 2) : 0;
    $agg["roi"] = $agg["cost"] > 0 ? round(($agg["profit"] / $agg["cost"]) * 100, 2) : 0;
    $agg["reject_rate"] = ($agg["total_statuses"] > 0)
    ? round(($agg["rejected"] / $agg["total_statuses"]) * 100, 2)
    : ($agg['rejected'] > 0 ? 100 : 0);


    $displayStats = $agg;
}


        // Render parent/child row
        echo '<tr class="' . ($level === 0 ? 'group-row' : 'child-row hidden') . '" '
            . ($parentId ? "data-parent='$parentId'" : '')
            . " data-level='$level' data-id='$id'>";

        echo "<td style='padding-left: " . ($level*30) . "px;'>";
        if ($hasChildren) echo '<span class="arrow">▶</span> ';
        echo htmlspecialchars($key) . "</td>";

        echo "<td>{$displayStats['clicks']}</td>";
        echo "<td>{$displayStats['conversions']}</td>";
        echo "<td>$" . number_format($displayStats['cost'],2) . "</td>";
        echo "<td>$" . number_format($displayStats['revenue'],2) . "</td>";
        echo "<td>$" . number_format($displayStats['profit'],2) . "</td>";
        echo "<td>{$displayStats['roi']}%</td>";
        echo "<td>{$displayStats['cr']}%</td>";
        echo "<td>{$displayStats['reject_rate']}%</td>";
        echo "</tr>";

        // Recursive render for children
        renderGroupedRows($value, $id, $level+1, $counter);
    }
}
function computeFullAggregate($node) {
    $total = [
    "clicks" => 0,
    "conversions" => 0,
    "revenue" => 0,
    "cost" => 0,
    "profit" => 0,
"approved_statuses" => 0,
"rejected" => 0,
"total_statuses" => 0,

];


    foreach ($node as $key => $value) {
        if ($key === "_stats") {
            // add this node's own stats
            foreach ($total as $k => $v) {
                $total[$k] += $value[$k];
            }
        } else {
            // recursively aggregate children
            $childAgg = computeFullAggregate($value);
            foreach ($total as $k => $v) {
                $total[$k] += $childAgg[$k];
            }
        }
    }

    
    return $total;
}


?>


