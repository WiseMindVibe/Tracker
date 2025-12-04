<?php
require "../../../src/bootstrap.php";

$id = $_GET['id'] ?? null;
$campaign_id = $_GET['cid'] ?? null;

if (!$id || !$campaign_id) die("Missing parameters.");

$offer = getCampaignOffers($id);
if (!$offer) die("Offer mapping not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $offer_id = $_POST['offer_id'];
    $cap = $_POST['cap'];
    $views = $_POST['views'];

    updateCampaignOffer($id, $offer_id, $cap, $views);
    header("Location: list.php?cid=" . $campaign_id);
    exit;
}
?>

<h2>Edit Campaign Offer</h2>

<form method="POST">
    <label>Offer ID:</label><br>
    <input type="number" name="offer_id" value="<?= $offer['offer_id'] ?>" required><br><br>

    <label>Views:</label><br>
    <input type="number" name="views" value="<?= $offer['views'] ?>"><br><br>

    <label>Cap (max views):</label><br>
    <input type="number" name="cap" value="<?= $offer['cap'] ?>" required><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php?cid=<?= $campaign_id ?>">← Back</a>
