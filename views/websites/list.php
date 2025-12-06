<?php
require_once __DIR__ . "/../../src/bootstrap.php";
include __DIR__ . "/../../includes/topbar.php";

$websites = getWebsites();
?>

<h2>Websites</h2>

<a href="create.php">+ Add Website</a>

<table border="5" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Domain</th>
        <th>Country</th>
        <th>Buffers</th>
        <th>Actions</th>
        <th>Created At</th>
        <th>Updated At</th>
    </tr>

    <?php foreach ($websites as $website): ?>
    <tr>
        <td><?= $website['id'] ?></td>
        <td><?= htmlspecialchars($website['domain']) ?></td>
        <td><?= htmlspecialchars($website['country']) ?></td>

        <td>
            <a href="buffers/list.php?id=<?= $website['id'] ?>">View Buffers</a>
        </td>

        <td>
            <a href="edit.php?id=<?= $website['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $website['id'] ?>" onclick="return confirm('Delete this website?')">Delete</a>
        </td>
        <td><?= htmlspecialchars($website['created_at']) ?></td>
        <td><?= htmlspecialchars($website['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>

</table>

<br>
<a href="../../views">← Back to Root</a>