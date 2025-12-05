<?php
require_once __DIR__ . "/../../src/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = $_POST['domain'];
    $country = $_POST['country'];

    if (addWebsite($domain, $country)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Website</h2>

<form method="POST">
    <label>Domain:</label><br>
    <input type="text" name="domain" required><br><br>

    <label>Country:</label><br>
    <input type="text" name="country" required><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
