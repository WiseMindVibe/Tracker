<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$campaign_id = $_GET['cid'];
$campaign = getCampaign($campaign_id);

if (!$campaign) die("Campaign not found.");

$traffic_sources = getTrafficSources();
$external_campaigns = getExternalCampaignIds($campaign_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $external_campaign_id = $_POST['external_campaign_id'];
    $country = $_POST['country'];
    $traffic_source_id = $_POST['traffic_source_id'];
    $is_tester = isset($_POST['is_tester']) ? 1 : 0;

    if (updateCampaign($campaign_id, $name, $country, $external_campaign_id, $traffic_source_id, $is_tester)) {
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

<?php
$external_campaigns = getExternalCampaignIds($campaign['id']);
$ext_ids = [];
foreach ($external_campaigns as $ec) {
    $ext_ids[] = $ec['external_campaign_id'];
}
$external_ids_text = implode("\n", $ext_ids);
?>
<textarea name="external_campaign_id" rows="3" required><?= htmlspecialchars($external_ids_text) ?></textarea>

<br><br>

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

       <label>
        <input type="checkbox" name="is_tester" value="1" checked>
        Tester
    </label>
    <br><br>

    
    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
