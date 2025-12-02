<?php
require_once __DIR__ . "/../../src/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $api_key = $_POST['api_key'] ?? null;
    $api_secret = $_POST['api_secret'] ?? null;

    if (addAffiliateProgram($name, $api_key, $api_secret)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Affiliate Program</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>API Key (optional):</label><br>
    <input type="text" name="api_key"><br><br>

    <label>API Secret (optional):</label><br>
    <input type="text" name="api_secret"><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
