<?php
require_once __DIR__ . "/../../src/bootstrap.php";

// Load data for dropdowns
$affiliates = getAffiliatePrograms();
$websites = getWebsites();
$countries = json_decode(file_get_contents(__DIR__ . '/../data/countries.json'), true); //Add countries & decode JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $affiliate_id = $_POST['affiliate_id'];
    $affiliate_link = $_POST['affiliate_link'];
    $country = $_POST['country'];
    $website_id = $_POST['website_id'];

    if (addOffer($name, $affiliate_id, $affiliate_link, $country, $website_id)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Offer</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>Affiliate:</label><br>
    <select name="affiliate_id" required>
        <?php foreach($affiliates as $a): ?>
            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Affiliate Link:</label><br>
    <input type="url" name="affiliate_link" required><br><br>

    <label>Country:</label><br>
    <select name="country" >
        <?php foreach($countries as $c): ?>
            <option value="<?= $c['code'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Website:</label><br>
    <select name="website_id" required>
        <?php foreach($websites as $w): ?>
            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['domain']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
