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
    </tr>

    <?php foreach ($campaigns as $c): ?>
    <tr>
        <td><?= $c['id'] ?></td>
        <td><?= htmlspecialchars($c['name']) ?></td>
        <td><?= htmlspecialchars($c['external_campaign_id']) ?></td>
        <td>

            <?= $views = getOfferViewsInCampaign($c['id']) ?? 0 ?>
            /
            <?= $cap = getOfferCapInCampaign($c['id']) ?? 0 ?>
            - 
            <?= number_format((($views / $cap) * 100), 2) ?? 0 ?>%

        </td>
        <td><?= htmlspecialchars($c['country']) ?></td>
        <td><?= htmlspecialchars($c['traffic_source_name']) ?></td>
        
        <td><?= htmlspecialchars(generateTrackingUrl($base_url, $c)) ?></td>

        <td>
            <a href="add_offers/list.php?cid=<?= $c['id'] ?>">Add Offers</a>
            <a href="edit.php?id=<?= $c['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Delete this campaign?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../../views">← Back to Root</a>