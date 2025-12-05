<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$id = $_GET['id'];
$website = getWebsite($id);

if (!$website) {
    die("Website not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = $_POST['domain'];
    $country = $_POST['country'];

    if (updateWebsite($id, $domain, $country)) {
        header("Location: list.php");
        exit;
    }
}
?>

<h2>Edit Website</h2>

<form method="POST">
    <label>Domain:</label><br>
    <input type="text" name="domain" value="<?= htmlspecialchars($website['domain']) ?>" required><br><br>

    <label>Country:</label><br>
    <input type="text" name="country" value="<?= htmlspecialchars($website['country']) ?>" required><br><br>

    <button type="submit">Update</button>
</form>

<br>
<a href="list.php">← Back to list</a>
