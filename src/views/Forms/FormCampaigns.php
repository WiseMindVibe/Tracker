<?php
$catalogJson = json_encode($offers_list ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<script type="application/json" id="campaign-offers-catalog"><?= $catalogJson ?></script>

<form method="POST" action="index.php?page=campaigns&action=create">

    <p>
        <label>Tester</label><br>
        <input type="hidden" name="tester" value="0">
        <input type="checkbox" name="tester" value="1">
        <span class="form-hint">When enabled, uses ROI rules in the daily job and skips zone-blackout logic in the tracker (see redirect).</span>
    </p>

    <p>
        <label>Campaign name</label><br>
        <input name="name" type="text" class="field-input" required
            placeholder="Name[Affiliate] - Country - Mobile - 3G - Includes">
    </p>

    <p>
        <label>Traffic source</label><br>
        <select name="traffic_source_id" id="traffic_source" class="field-input" required>
            <?php foreach ($traffics as $ts): ?>
                <option value="<?= htmlspecialchars((string) $ts['id']) ?>"
                    data-name="<?= htmlspecialchars((string) $ts['name']) ?>">
                    <?= htmlspecialchars((string) $ts['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label>External campaign IDs (Propeller / source IDs)</label><br>
        <input type="text" name="external_campaign_id[]" class="field-input" placeholder="External ID">
        <button type="button" class="btn-secondary" id="add-external-id-row">+ Add ID</button>
    </p>

    <p>
        <label>Country</label><br>
        <input name="country" type="text" class="field-input" list="country-list" required placeholder="Country code">
        <datalist id="country-list">
            <?php foreach ($countries as $country): ?>
                <option value="<?= htmlspecialchars((string) $country['code']) ?>">
                    <?= htmlspecialchars((string) $country['name']) ?>
                </option>
            <?php endforeach; ?>
        </datalist>
    </p>

    <input type="hidden" name="uuid" id="uuid">

    <p>
        <label>Tracking URL (live)</label><br>
        <input name="tracking_url" type="url" id="tracking_url" class="field-input" readonly
            placeholder="Pick a traffic source to generate the link"
            data-base_url="https://<?= htmlspecialchars(rtrim((string) (getenv('BASE_URL') ?: ''), '/') . '/public/redirect.php?') ?>">
    </p>

    <p>
        <label>Offers in this campaign</label><br>
        <span class="form-hint">Search by typing an offer name. Same offer cannot be added twice. Set a daily cap per offer.</span>
    </p>
    <div id="campaign-offers-create">
        <div class="campaign-offer-row">
            <div class="offer-combobox">
                <input type="text" class="offer-combobox__search field-input" autocomplete="off"
                    placeholder="Type to search offers…">
                <input type="hidden" name="campaign_offer_id[]" class="offer-combobox__id" value="">
                <ul class="offer-combobox__list" role="listbox" hidden></ul>
            </div>
            <input type="number" name="campaign_offer_cap[]" class="field-input edit_campaign_offers_cap" min="0" step="1" value="1000"
                title="Cap">
            <button type="button" class="btn-danger" onclick="this.closest('.campaign-offer-row').remove()">×</button>
        </div>
    </div>
    <p>
        <button type="button" class="btn-secondary" id="add-campaign-offer-row">+ Add offer</button>
    </p>

    <p>
        <button type="submit" class="btn-success">Save campaign</button>
    </p>
</form>

<script>
(function () {
    var addExt = document.getElementById('add-external-id-row');
    if (addExt) {
        addExt.addEventListener('click', function () {
            var p = addExt.previousElementSibling;
            if (!p || p.tagName !== 'INPUT') return;
            var clone = p.cloneNode(true);
            clone.value = '';
            addExt.parentNode.insertBefore(clone, addExt);
        });
    }
})();
</script>
