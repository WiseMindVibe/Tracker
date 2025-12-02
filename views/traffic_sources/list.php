<?php
require_once __DIR__ . "/../../src/bootstrap.php";

$traffic_sources = getTrafficSources();
?>

<h2>Traffic Sources</h2>

<a href="create.php">+ Add Traffic Source</a>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>API Key</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($traffic_sources as $t): ?>
    <tr>
        <td><?= $t['id'] ?></td>
        <td><?= htmlspecialchars($t['name']) ?></td>
        <td>
            <input type="password" value="<?= htmlspecialchars($t['api_key']) ?>" readonly>
            <button onclick="this.previousElementSibling.type='text'">Show</button>
        </td>
        <td>
            <a href="edit.php?id=<?= $t['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $t['id'] ?>" onclick="return confirm('Delete this traffic source?')">Delete</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<a href="../../views">← Back to Root</a>
