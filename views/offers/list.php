<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$offers = getOffers();
?>

<h2>Offers</h2>

<a href="create.php">+ Add Offer</a>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Affiliate</th>
        <th>Affiliate Link</th>
        <th>Country</th>
        <th>Website</th>
        <th>Articles</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($offers as $o): ?>
    <tr>
        <td><?= $o['id'] ?></td>
        <td><?= htmlspecialchars($o['name']) ?></td>
        <td><?= htmlspecialchars($o['affiliate_name']) ?></td>
        <td><a href="<?= htmlspecialchars($o['affiliate_link']) ?>" target="_blank">Link</a></td>
        <td><?= htmlspecialchars($o['country']) ?></td>
        <td><?= htmlspecialchars($o['website_domain']) ?></td>
        <td>
            <a href="articles/list.php?offer_id=<?= $o['id'] ?>">Articles</a>
        </td>
        <td>
            <a href="edit.php?id=<?= $o['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $o['id'] ?>" onclick="return confirm('Delete this offer?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../../views">← Back to Root</a>
