<form method="POST" action="index.php?page=affiliates&action=create">

    <br>
    Affiliate Name
    <br>
    <input name="name" type="text" class="field-input" required>
    <br>
    <br>
    Active
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1">
    <br>


    <button type="submit" class="btn-success">Save Affiliate</button>
</form>