<?php
require "../../../src/bootstrap.php";

$campaign_id = $_GET['cid'] ?? null;
if (!$campaign_id) die("Campaign ID missing.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $offer_id = $_POST['offer_id'];
    $cap = $_POST['cap'];

    addCampaignOffer($campaign_id, $offer_id, $cap);
    header("Location: list.php?cid=" . $campaign_id);
    exit;
}
?>

<h2>Add Offer to Campaign</h2>

<form method="POST">
    <label>Offer ID:</label><br>
    <input type="number" name="offer_id" required><br><br>

    <label>Views:</label><br>
    <input type="number" name="views" value="0"><br><br>

    <label>Cap (max views):</label><br>
    <input type="number" name="cap" required><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php?cid=<?= $campaign_id ?>">← Back</a>
