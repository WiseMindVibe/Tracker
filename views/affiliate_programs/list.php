<?php
require_once __DIR__ . "/../../src/bootstrap.php";
include __DIR__ . "/../../includes/topbar.php";

$programs = getAffiliatePrograms();
?>

<h2>Affiliate Programs</h2>

<a href="create.php">+ Add Affiliate Program</a>

<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>API Key</th>
        <th>API Secret</th>
        <th>Actions</th>
        <th>Created At</th>
        <th>Updated At</th>
    </tr>

    <?php foreach ($programs as $p): ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td>
            <input type="password" value="<?= htmlspecialchars($p['api_key']) ?>" readonly>
            <button onclick="toggleApiKey(this)">Show</button>
        </td>
        <td>
            <input type="password" value="<?= htmlspecialchars($p['api_secret']) ?>" readonly>
            <button onclick="toggleApiKey(this)">Show</button>
        </td>
        <td>
            <a href="edit.php?id=<?= $p['id'] ?>">Edit</a> |
            <a href="delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete this program?')">Delete</a>
        </td>
        <td><?= htmlspecialchars($p['created_at']) ?></td>
        <td><?= htmlspecialchars($p['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<script>
function toggleApiKey(btn) {
    const input = btn.previousElementSibling;

    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "Hide";
    } else {
        input.type = "password";
        btn.textContent = "Show";
    }
}
</script>

<br>
<a href="../../views">← Back to Root</a>
