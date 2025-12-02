<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$websites = getWebsites();
?>

<h2>Websites</h2>

<a href="website_create.php">+ Add Website</a>

<table border="5" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Domain</th>
        <th>Country</th>
        <th>Buffers</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($websites as $website): ?>
    <tr>
        <td><?= $website['id'] ?></td>
        <td><?= htmlspecialchars($website['domain']) ?></td>
        <td><?= htmlspecialchars($website['country']) ?></td>

        <td>
            <a href="buffers/buffers_list.php?id=<?= $website['id'] ?>">View Buffers</a>
        </td>

        <td>
            <a href="website_edit.php?id=<?= $website['id'] ?>">Edit</a> |
            <a href="website_delete.php?id=<?= $website['id'] ?>" onclick="return confirm('Delete this website?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>

</table>

<br>
<a href="../../views">← Back to Root</a>