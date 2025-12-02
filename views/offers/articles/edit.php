<?php
require "../../../src/bootstrap.php";

$id = $_GET['id'] ?? null;
if (!$id) die("Article ID missing.");

$db = db();
$stmt = $db->prepare("SELECT * FROM offers_artlcle WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) die("Article not found.");

$offer = getOffer($article['offer_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $article_url = $_POST['article_url'];

    if (updateOfferArticle($id, $article_url)) {
        header("Location: edit.php?offer_id=" . $article['offer_id']);
        exit;
    }
}
?>

<h2>Edit Article for Offer: <?= htmlspecialchars($offer['name']) ?></h2>

<form method="POST">
    <label>Article URL:</label><br>
    <input type="url" name="article_url" value="<?= htmlspecialchars($article['article_url']) ?>" required><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php?offer_id=<?= $article['id'] ?>">← Back</a>
