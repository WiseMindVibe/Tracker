<?php
require_once __DIR__ . "/../../../src/bootstrap.php";

$website_id = $_GET['id'];
$website = getWebsite($website_id);

if (!$website) {
    die("Website not found.");
}

$buffers = getWebsiteBuffers($website_id);
?>

<h2>Buffers for: <?= htmlspecialchars($website['domain']) ?></h2>

<a href="buffers_add.php?website_id=<?= $website_id ?>">+ Add Buffer</a>
<br><br>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Type</th>
        <th>Buffer URL</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($buffers as $b): ?>
    <tr>
        <td><?= $b['id'] ?></td>
        <td><?= htmlspecialchars($b['type']) ?></td>
        <td><?= htmlspecialchars($b['buffer_url']) ?></td>
        <td>
            <a href="buffers_edit.php?id=<?= $b['id'] ?>&website_id=<?= $website_id ?>">Edit</a> |
            <a href="buffers_delete.php?id=<?= $b['id'] ?>&website_id=<?= $website_id ?>" onclick="return confirm('Delete buffer?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../website_list.php">← Back to Websites</a>
