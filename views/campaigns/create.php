<?php
require_once __DIR__ . "/../../src/bootstrap.php";


// Load data for dropdowns
$traffic_sources = getTrafficSources();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $external_ids_raw = trim($_POST['external_campaign_id']);
    $country = $_POST['country'];
    $traffic_source_id = $_POST['traffic_source_id'];
    $is_tester = isset($_POST['is_tester']) ? 1 : 0;


    // Create the internal campaign
    if (addCampaign($name, $country, $traffic_source_id, $is_tester )) {
        
        $campaign_id = db()->lastInsertId();

        // Split external IDs by line
        $external_ids = array_filter(array_map('trim', explode("\n", $external_ids_raw)));

        foreach ($external_ids as $id) {
    addExternalCampaignId($campaign_id, $id, $traffic_source_id);
}


        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Campaign</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>External Campaign IDs (one per line):</label><br>
    <textarea name="external_campaign_id" rows="3" required></textarea><br><br>

    
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

        <!-- ✅ Tester checkbox added -->
    <label>
        <input type="checkbox" name="is_tester" value="0" checked>
        Tester
    </label>
    <br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
