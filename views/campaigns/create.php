<?php
require_once __DIR__ . "/../../src/bootstrap.php";

// Load data for dropdowns
$traffic_sources = getTrafficSources();
$countries = json_decode(file_get_contents(__DIR__ . '/../data/countries.json'), true); //Add countries & decode JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $external_campaign_id = $_POST['external_campaign_id'];
    $country = $_POST['country'];
    $traffic_source_id = $_POST['traffic_source_id'];

    if (addCampaign($name, $external_campaign_id, $traffic_source_id, $country)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Campaign</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>
    
    <label>External Campaign ID:</label><br>
    <input type="text" name="external_campaign_id" required><br><br>
    
    <label>Country:</label><br>
    <select name="country" >
        <?php foreach($countries as $c): ?>
            <option value="<?= $c['code'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Traffic Source:</label><br>
    <select name="traffic_source_id" required>
        <?php foreach($traffic_sources as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
    </select><br><br>


    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
