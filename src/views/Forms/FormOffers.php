<form method="POST" action="index.php?page=offers&action=create">

    <br>
    Offer Name
    <br>
    <input name="name" type="text" class="field-input" required placeholder="Name - Affiliate Country">
    <br>
    <br>
    Affiliate Program
    <br>
    <select name="affiliate_id" class="field-input" required>
        <?php foreach ($affiliates as $aa): ?>

            <option value="<?= $aa['id'] ?>">
                <?= htmlspecialchars($aa['affiliate_program']) ?>
            </option>

        <?php endforeach; ?>

    </select>
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
    <br>
    <br>
    Affiliate Link
    <br>
    <input name="affiliate_link" type="url" class="field-input" required placeholder="https://...">
    <br>
    <br>
    Website
    <br>
    <select name="website_id" class="field-input" required>
        <?php foreach ($websites as $w): ?>
            <option value="<?= htmlspecialchars($w['id']) ?>">
                <?= htmlspecialchars($w['domain']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br>
    <br>
    Article URL (optional; multi-article UI later)
    <br>
    <input type="url" name="articles[]" class="field-input" placeholder="https://...">
    <br><br>

    <button type="submit" class="btn-success">Save Offer</button>
</form>