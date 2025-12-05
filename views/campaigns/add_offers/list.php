<?php
require_once __DIR__ . "/../../../src/bootstrap.php";
include __DIR__ . "/../../../includes/topbar.php";

$campaign_id = $_GET['cid'] ?? null;

if (!$campaign_id) die("Campaign ID missing.");

$campaign = getCampaign($campaign_id);
$campaignOffers = getCampaignOffers($campaign_id);
?>

<h2>Offers for Campaign: <?= htmlspecialchars($campaign['name']) ?></h2>

<a href="create.php?cid=<?= $campaign_id ?>">+ Add Offers</a>
<br><br>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Offer ID</th>
        <th>Current Views</th>
        <th>Max Views (Cap)</th>
        <th>Actions</th>
    </tr>

<?php foreach ($campaignOffers as $co): ?>
<tr>
    <td><?= $co['id'] ?></td>
    <td><?= htmlspecialchars($co['offer_id']) ?></td>
    <td><?= htmlspecialchars($co['current_views'] ?? 0) ?></td>
    <td><?= htmlspecialchars($co['cap']) ?></td>
    <td>
        <a href="edit.php?id=<?= $co['id'] ?>&cid=<?= $campaign_id ?>">Edit</a> |
        <a href="delete.php?id=<?= $co['id'] ?>&cid=<?= $campaign_id ?>"
           onclick="return confirm('Delete this offer mapping?')">
           Delete
        </a>
    </td>
</tr>

<?php endforeach; ?>

</table>


<br>
<a href="../list.php">← Back to offers</a>
