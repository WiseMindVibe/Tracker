<?php
require_once __DIR__ . '/src/bootstrap.php';

// Fetch all affiliate programs
$stmt = db()->query("SELECT * FROM affiliate_programs ORDER BY id ASC");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Affiliate Programs Admin</title>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/colreorder/1.6.2/css/colReorder.dataTables.min.css">
<style>
/* Optional: some spacing for filters */
#programsTable thead input {
    width: 100%;
    box-sizing: border-box;
}
</style>
</head>
<body>
<div class="container">
    <h2>Affiliate Programs</h2>
    <a href="affiliate_programs_add.php">Add New Program</a>
    <table id="programsTable" class="display stripe hover" style="width:100%">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
            <tr id="filterRow">
                <th><input type="text" placeholder="Search ID"></th>
                <th><input type="text" placeholder="Search Name"></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($programs as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['id']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td>
                    <a href="affiliate_programs_edit.php?id=<?= $p['id'] ?>">Edit</a> |
                    <a href="affiliate_programs_delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Delete this program?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
<script src="https://cdn.datatables.net/colresize/1.6.1/js/dataTables.colResize.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#programsTable').DataTable({
        colReorder: true,        // draggable columns
        colResize: true,         // resizable columns
        orderCellsTop: true,
        fixedHeader: true,
        pageLength: 10,
    });

    // Column-specific filtering
    $('#programsTable thead tr#filterRow th').each(function(i){
        $('input', this).on('keyup change', function(){
            table.column(i).search(this.value).draw();
        });
    });
});
</script>
</body>
</html>
