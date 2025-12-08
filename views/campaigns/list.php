<?php
require_once __DIR__ . "/../../src/bootstrap.php";
include __DIR__ . "/../../includes/topbar.php";

$campaigns = getCampaigns();


?>

<h2>Campaigns</h2>
<a href="create.php">+ Add Campaign</a>



<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>External Campaign ID</th>
        <th>Views</th>
        <th>Country</th>
        <th>Traffic Source</th>
        <th>Tracking URL</th>
        <th>Actions</th>
        <th>Created At</th>
        <th>Updated At</th>
    </tr>

    <?php foreach ($campaigns as $c): 
        $external_campaigns = getExternalCampaignIds($c['id']);
        ?>
    <tr>
        <td><?= $c['id'] ?></td>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td>
            <?= htmlspecialchars(implode(", ", $external_campaigns)) ?>
        </td>
        <td>

            <?= $views = getOfferViewsInCampaign($c['id']) ?? 0 ?>
            /
            <?= $cap = getOfferCapInCampaign($c['id']) ?? 0 ?>
            - 
            <?= ($cap > 0) ? number_format(($views / $cap) * 100, 2) : '0.00' ?>%


        </td>
        <td><?= htmlspecialchars($c['country']) ?></td>
        <td><?= htmlspecialchars($c['traffic_source_name']) ?></td>
        
        <td><?= htmlspecialchars(generateTrackingUrl($base_url, $c)) ?></td>

        <td>
            <a href="add_offers/list.php?cid=<?= $c['id'] ?>">Add Offers</a>
            <a href="edit.php?cid=<?= $c['id'] ?>">Edit</a> |
            <a href="delete.php?cid=<?= $c['id'] ?>" onclick="return confirm('Delete this campaign?')">Delete</a>
            <input type="checkbox" disabled <?= $c['tester'] ? 'checked' : '' ?>>
        </td>
        <td><?= htmlspecialchars($c['created_at']) ?></td>
        <td><?= htmlspecialchars($c['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../../views">← Back to Root</a>