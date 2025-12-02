<?php
require "../../../src/bootstrap.php";

$offer_id = $_GET['offer_id'] ?? null;

if (!$offer_id) die("Offer ID missing.");

$offer = getOffer($offer_id);
$articles = getOfferArticles($offer_id);
?>

<h2>Articles for Offer: <?= htmlspecialchars($offer['name']) ?></h2>

<a href="create.php?offer_id=<?= $offer_id ?>">+ Add Article</a>
<br><br>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Article URL</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($articles as $a): ?>
    <tr>
        <td><?= $a['id'] ?></td>
        <td><a href="<?= htmlspecialchars($a['article_url']) ?>" target="_blank">
            <?= htmlspecialchars($a['article_url']) ?>
        </a></td>
        <td>
            <a href="edit.php?offer_id=<?= $offer_id ?>&id=<?= $a['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $a['id'] ?>&offer_id=<?= $offer_id ?>" 
               onclick="return confirm('Delete this article?')">
               Delete
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../list.php">← Back to offers</a>
