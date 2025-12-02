<?php
require_once __DIR__ . "/../../src/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $api_key = $_POST['api_key'];

    if (addTrafficSource($name, $api_key)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Add Traffic Source</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>API Key:</label><br>
    <input type="text" name="api_key"><br><br>

    <button type="submit">Save</button>
</form>

<br>
<a href="list.php">← Back to list</a>
