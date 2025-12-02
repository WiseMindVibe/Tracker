<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];
$program = getAffiliateProgram($id);

if (!$program) {
    die("Affiliate Program not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $api_key = $_POST['api_key'] ?? null;
    $api_secret = $_POST['api_secret'] ?? null;

    if (updateAffiliateProgram($id, $name, $api_key, $api_secret)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Edit Affiliate Program</h2>

<form method="POST">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($program['name']) ?>" required><br><br>

    <label>API Key:</label><br>
    <input type="text" name="api_key" value="<?= htmlspecialchars($program['api_key']) ?>"><br><br>

    <label>API Secret:</label><br>
    <input type="text" name="api_secret" value="<?= htmlspecialchars($program['api_secret']) ?>"><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
