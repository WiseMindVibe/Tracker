<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];
$offer = getOffer($id);

if (!$offer) die("Offer not found.");

$affiliates = getAffiliatePrograms();
$websites = getWebsites();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $affiliate_id = $_POST['affiliate_program_id'];
    $affiliate_link = $_POST['affiliate_link'];
    $country = $_POST['country'];
    $website_id = $_POST['website_id'];

    if (updateOffer($id, $name, $affiliate_id, $affiliate_link, $country, $website_id)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Edit Offer</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($offer['name']) ?>" required><br><br>

    <label>Affiliate:</label><br>
    <select name="affiliate_program_id" required>
        <?php foreach($affiliates as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $offer['affiliate_program_id'] == $a['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['name']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Affiliate Link:</label><br>
    <input type="url" name="affiliate_link" value="<?= htmlspecialchars($offer['affiliate_link']) ?>" required><br><br>

    <label>Country:</label><br>
    <select name="country" required>
        <?php foreach($countries as $c): ?>
            <option value="<?= $c['code'] ?>" <?= $offer['country'] == $c['code'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Website:</label><br>
    <select name="website_id" required>
        <?php foreach($websites as $w): ?>
            <option value="<?= $w['id'] ?>" <?= $offer['website_id'] == $w['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($w['domain']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
