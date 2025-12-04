<h2>Reporting</h2>

<label>Start:</label>
<input type="datetime-local" id="start" value="<?= date('Y-m-d\T00:00'); ?>">

<label>End:</label>
<input type="datetime-local" id="end" value="<?= date('Y-m-d\T23:59'); ?>">

<button onclick="loadOffers()">Load Report</button>

<table border="1" width="100%" id="reportTable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Clicks</th>
            <th>Conv</th>
            <th>Revenue</th>
            <th>Cost</th>
            <th>Profit</th>
        </tr>
    </thead>
    <tbody id="reportBody"></tbody>
</table>

<script>
function formatRow(item) {
    return `
        <td>${item.name}</td>
        <td>${item.clicks}</td>
        <td>${item.conversions}</td>
        <td>${item.revenue}</td>
        <td>${item.cost}</td>
        <td>${item.profit}</td>
    `;
}

function loadOffers() {
    let start = document.getElementById('start').value.replace('T',' ');
    let end = document.getElementById('end').value.replace('T',' ');

    fetch(`/reporting.php?mode=offers&start=${start}&end=${end}`)
    .then(r => r.json())
    .then(data => {
        let tbody = document.getElementById('reportBody');
        tbody.innerHTML = '';

        data.forEach(offer => {
            let row = document.createElement('tr');
            row.classList.add('offer-row');
            row.dataset.offerId = offer.offer_id;

            row.innerHTML = `
                <td><b>${offer.offer_name}</b> (click to expand)</td>
                <td>${offer.clicks}</td>
                <td>${offer.conversions}</td>
                <td>${offer.revenue}</td>
                <td>${offer.cost}</td>
                <td>${offer.profit}</td>
            `;

            row.onclick = () => loadCampaigns(row, offer.offer_id, start, end);
            tbody.appendChild(row);
        });
    });
}

function loadCampaigns(row, offerId, start, end) {
    if (row.dataset.loaded === "1") {
        row.dataset.loaded = "0";
        document.querySelectorAll(`.campaign-of-${offerId}`).forEach(e => e.remove());
        return;
    }

    row.dataset.loaded = "1";

    fetch(`/reporting.php?mode=campaigns&offer_id=${offerId}&start=${start}&end=${end}`)
    .then(r => r.json())
    .then(data => {
        let tbody = document.getElementById('reportBody');

        data.forEach(c => {
            let tr = document.createElement('tr');
            tr.classList.add(`campaign-of-${offerId}`);
            tr.dataset.campaignId = c.campaign_id;

            tr.innerHTML = `
                <td style="padding-left: 30px;">↳ Campaign: <b>${c.campaign_name}</b></td>
                <td>${c.clicks}</td>
                <td>${c.conversions}</td>
                <td>${c.revenue}</td>
                <td>${c.cost}</td>
                <td>${c.profit}</td>
            `;

            tr.onclick = () => loadOS(tr, offerId, c.campaign_id, start, end);

            row.insertAdjacentElement("afterend", tr);
        });
    });
}

function loadOS(row, offerId, campaignId, start, end) {
    if (row.dataset.loaded === "1") {
        row.dataset.loaded = "0";
        document.querySelectorAll(`.os-of-${offerId}-${campaignId}`).forEach(e => e.remove());
        return;
    }

    row.dataset.loaded = "1";

    fetch(`/reporting.php?mode=os&offer_id=${offerId}&campaign_id=${campaignId}&start=${start}&end=${end}`)
    .then(r => r.json())
    .then(data => {
        data.forEach(os => {
            let tr = document.createElement('tr');
            tr.classList.add(`os-of-${offerId}-${campaignId}`);

            tr.innerHTML = `
                <td style="padding-left: 60px;">↳ OS: <b>${os.OS}</b></td>
                <td>${os.clicks}</td>
                <td>${os.conversions}</td>
                <td>${os.revenue}</td>
                <td>${os.cost}</td>
                <td>${os.profit}</td>
            `;

            tr.onclick = () => loadBrowser(tr, offerId, campaignId, os.OS, start, end);

            row.insertAdjacentElement("afterend", tr);
        });
    });
}

function loadBrowser(row, offerId, campaignId, os, start, end) {
    if (row.dataset.loaded === "1") {
        row.dataset.loaded = "0";
        document.querySelectorAll(`.browser-of-${offerId}-${campaignId}-${os}`).forEach(e => e.remove());
        return;
    }

    row.dataset.loaded = "1";

    fetch(`/reporting.php?mode=browser&offer_id=${offerId}&campaign_id=${campaignId}&os=${os}&start=${start}&end=${end}`)
    .then(r => r.json())
    .then(data => {
        data.forEach(b => {
            let tr = document.createElement('tr');
            tr.classList.add(`browser-of-${offerId}-${campaignId}-${os}`);

            tr.innerHTML = `
                <td style="padding-left: 90px;">↳ Browser: <b>${b.browser}</b></td>
                <td>${b.clicks}</td>
                <td>${b.conversions}</td>
                <td>${b.revenue}</td>
                <td>${b.cost}</td>
                <td>${b.profit}</td>
            `;

            row.insertAdjacentElement("afterend", tr);
        });
    });
}
</script>
