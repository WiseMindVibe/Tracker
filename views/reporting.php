<?php
require_once "../src/bootstrap.php";
include "../includes/topbar.php";

$start = $_GET['start'] ?? date("Y-m-d");
$end   = $_GET['end']   ?? date("Y-m-d");

// Define possible grouping options
$availableGroups = ["offer", "campaign", "os", "browser"];

// Get selected groups from GET, default: offer + campaign
$selectedGroups = $_GET['groups'] ?? ["offer","campaign"];

?>

<form method="GET" style="margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <label>Start Date:</label>
    <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" required>
    <label>End Date:</label>
    <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" required>

    <label>Group By:</label>
    <?php foreach ($availableGroups as $group): ?>
        <label style="margin-right:10px;">
            <input type="checkbox" name="groups[]" value="<?= $group ?>" 
                <?= in_array($group, $selectedGroups) ? "checked" : "" ?>>
            <?= ucfirst($group) ?>
        </label>
    <?php endforeach; ?>

    <button type="submit" style="padding:5px 15px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer;">
        Apply
    </button>
</form>


    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reporting</title>
    <style>
        body {
            background: #111;
            color: #eee;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .card {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.4);
            margin-bottom: 20px;
        }

        h1 {
            margin-top: 0;
        }

        .filters {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-block {
            display: flex;
            flex-direction: column;
        }

        select, input[type=date] {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #444;
            background: #222;
            color: #ddd;
        }

        .table-container {
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #333;
        }

        th {
            background: #222;
            font-weight: bold;
            text-align: left;
        }

        tr.group-row {
            background: #181818;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        tr.group-row:hover {
            background: #222;
        }

        .arrow {
            display: inline-block;
            transition: transform 0.2s ease;
            margin-right: 8px;
        }

        .arrow.expanded {
            transform: rotate(90deg);
        }

        tr.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="card table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Clicks</th>
                    <th>Conversions</th>
                    <th>Spent</th>
                    <th>Revenue</th>
                    <th>Profit</th>
                    <th>ROI</th>
                    <th>CR</th>
                    <th>Reject Rate</th>
                </tr>
            </thead>

<tbody id="reportBody">
<?php
$groupedClicks = getGroupedClicks($start, $end, $selectedGroups);

$counter = 1; // ensure unique IDs across recursion



renderGroupedRows($groupedClicks, null, 0, $counter);
?>
</tbody>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('tr.group-row, tr.child-row').forEach(row => {
        row.addEventListener('click', function(e) {
            const arrow = row.querySelector('.arrow');
            if (!arrow) return; // skip rows without children

            const id = row.dataset.id;
            const children = document.querySelectorAll(`tr[data-parent='${id}']`);
            arrow.classList.toggle('expanded');
            children.forEach(c => c.classList.toggle('hidden'));
        });
    });
});
</script>

