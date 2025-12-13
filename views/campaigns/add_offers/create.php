<?php
require "../../../src/bootstrap.php";

$campaign_id = $_GET['cid'] ?? null;
if (!$campaign_id) die("Campaign ID missing.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $offer_id = $_POST['offer_name'];
    $cap = $_POST['cap'];

    addCampaignOffer($campaign_id, $offer_id, $cap);
    header("Location: list.php?cid=" . $campaign_id);
    exit;
}

$offers = getOffers()
?>

<h2>Add Offer to Campaign</h2>

<form method="POST">
    <label>Offer name:</label><br>
    
<input list="offers" name="offer_name" required>

<datalist id="offers">
    <?php foreach($offers as $o): ?>
        <option value="<?= htmlspecialchars($o['name']) ?>">
        </option>
    <?php endforeach; ?>
</datalist><br><br>


    <label>Cap (max views):</label><br>
    <input type="number" name="cap" required><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php?cid=<?= $campaign_id ?>">← Back</a>
