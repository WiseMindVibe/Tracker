<?php
require "../../../src/bootstrap.php";

$campaign_id = $_GET['cid'] ?? null;
$campaign_offer_id = $_GET['id'] ?? null;

if (!$campaign_id || !$campaign_offer_id) die("Missing parameters.");

// Get specific row
$db = db();
$stmt = $db->prepare("
    SELECT *
    FROM campaign_offers
    WHERE id = :id AND campaign_id = :cid
");
$stmt->execute([
    ':id' => $campaign_offer_id,
    ':cid' => $campaign_id
]);
$offer = $stmt->fetch();

if (!$offer) die("Offer mapping not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cap = $_POST['cap'];
    $current_views = $_POST['current_views'];

    updateCampaignOffer($campaign_offer_id, $cap, $current_views);

    header("Location: list.php?cid=" . $campaign_id);
    exit;
}
?>


<h2>Edit Campaign Offer</h2>
<form method="POST">
    <label>Offer Name:</label><br>
    <input type="text" name="name" value="<?= $offer['name'] ?>" disabled><br><br>

    <label>Views: ( DO NOT EDIT )</label><br>
    <input type="number" name="current_views" value="<?= $offer['current_views'] ?>"><br><br>

    <label>Cap (max views):</label><br>
    <input type="number" name="cap" value="<?= $offer['cap'] ?>" required><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php?cid=<?= $campaign_id ?>">← Back</a>
