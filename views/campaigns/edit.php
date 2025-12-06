<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$campaign_id = $_GET['cid'];
$campaign = getCampaign($campaign_id);

if (!$campaign) die("Campaign not found.");

$traffic_sources = getTrafficSources();
$countries = json_decode(file_get_contents(__DIR__ . '../../data/countries.json'), true);
$external_campaigns = getExternalCampaignIds($campaign_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $external_campaign_id = $_POST['external_campaign_id'];
    $country = $_POST['country'];
    $traffic_source_id = $_POST['traffic_source_id'];

    if (updateCampaign($campaign_id, $name, $external_campaign_id, $country, $traffic_source_id)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Edit Campaign</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($campaign['name']) ?>" required><br><br>
    
    <label>External Campaign ID:</label><br>
    <input type="text" name="external_campaign_id" value="<?= htmlspecialchars(implode(", ", $external_campaigns)) ?>" required><br><br>

    <label>Country:</label><br>
    <select name="country" required>
        <?php foreach($countries as $c): ?>
            <option value="<?= $c['code'] ?>" <?= $campaign['country'] == $c['code'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>
            
    <label>Traffic Source:</label><br>
    <select name="traffic_source_id" required>
        <?php foreach($traffic_sources as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $campaign['traffic_source_id'] == $t['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['name']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
