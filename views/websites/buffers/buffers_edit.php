<?php
require_once __DIR__ . "/../../../src/bootstrap.php";

$id = $_GET['id'];
$website_id = $_GET['website_id'];

$buffer = getWebsiteBuffer($id);
$website = getWebsite($website_id);

if (!$buffer) {
    die("Buffer not found.");
}
?>

<h2>Edit Buffer for <?= htmlspecialchars($website['domain']) ?></h2>

<form method="POST">
    <label>Type:</label><br>
    <input type="text" name="type" value="<?= htmlspecialchars($buffer['type']) ?>" required><br><br>

    <label>Buffer URL:</label><br>
    <textarea name="buffer_url" required><?= htmlspecialchars($buffer['buffer_url']) ?></textarea><br><br>

    <button type="submit">Update Buffer</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $buffer_url = $_POST['buffer_url'];

    if (updateWebsiteBuffer($id, $type, $buffer_url)) {
        header("Location: buffers_list.php?id=$website_id");
        exit;
    }
}
?>

<br>
<a href="buffers_list.php?id=<?= $website_id ?>">← Back</a>
