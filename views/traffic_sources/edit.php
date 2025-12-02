<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];
$ts = getTrafficSource($id);

if (!$ts) {
    die("Traffic Source not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $api_key = $_POST['api_key'];

    if (updateTrafficSource($id, $name, $api_key)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Edit Traffic Source</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($ts['name']) ?>" required><br><br>

    <label>API Key:</label><br>
    <input type="text" name="api_key" value="<?= htmlspecialchars($ts['api_key']) ?>"><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
