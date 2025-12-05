<?php
require_once __DIR__ . "/../../../src/bootstrap.php";

$website_id = $_GET['website_id'];
$website = getWebsite($website_id);

if (!$website) {
    die("Website not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $buffer_url = $_POST['buffer_url'];

    if (addWebsiteBuffer($website_id, $type, $buffer_url)) {
        header("Location: list.php?id=$website_id");
        exit;
    }
}
?>

<h2>Add Buffer for <?= htmlspecialchars($website['domain']) ?></h2>

<form method="POST">
    <label>Type:</label><br>
    <input type="text" name="type" required><br><br>

    <label>Buffer URL:</label><br>
    <textarea name="buffer_url" required></textarea><br><br>

    <button type="submit">Add Buffer</button>
</form>

<br>
<a href="list.php?id=<?= $website_id ?>">← Back</a>
