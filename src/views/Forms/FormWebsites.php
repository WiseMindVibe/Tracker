<form method="POST" action="index.php?page=websites&action=create">

    <br>
    Website domain
    <br>
    <input name="domain" type="text" class="field-input" required placeholder="NO HTTPS">
    <br>
    <br>
    Country
    <br>
    <input name="country" type="text" class="field-input" list="country-list" required placeholder="Country...">
    <datalist id="country-list">
        <?php foreach ($countries as $country): ?>
            <option value="<?= htmlspecialchars($country['code']) ?>">
                <?= htmlspecialchars($country['name']) ?>
            </option>
        <?php endforeach; ?>
    </datalist>


    <button type="submit" class="btn-success">Save Website</button>
</form>