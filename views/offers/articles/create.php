<?php
require "../../../src/bootstrap.php";

$offer_id = $_GET['offer_id'] ?? null;
if (!$offer_id) die("Offer ID missing.");

$offer = getOffer($offer_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $article_url = $_POST['article_url'];

    if (addOfferArticle($offer_id, $article_url)) {
        header("Location: list.php?offer_id=$offer_id");
        exit;
    }
}
?>

<h2>Add Article for Offer: <?= htmlspecialchars($offer['name']) ?></h2>

<form method="POST">
    <label>Article URL:</label><br>
    <input type="url" name="article_url" required><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php?offer_id=<?= $offer_id ?>">← Back</a>
