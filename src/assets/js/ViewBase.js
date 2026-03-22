let currentConfig = null;

function escapeHtml(str) {
    return String(str ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

/** @type {WeakSet<Element>} */
const offerComboboxWired = new WeakSet();

function readOffersCatalog() {
    const el = document.getElementById("campaign-offers-catalog");
    if (!el) return [];
    try {
        const data = JSON.parse(el.textContent || "[]");
        return Array.isArray(data) ? data : [];
    } catch (e) {
        return [];
    }
}

/**
 * @param {Array<{id:number|string,name:string}>} catalog
 * @param {string|number} selectedId
 * @param {number} cap
 * @param {string} viewsLabel
 * @param {'create'|'modal'} mode
 */
function buildOfferRowMarkup(catalog, selectedId, cap, viewsLabel, mode) {
    const sel = catalog.find((o) => String(o.id) === String(selectedId));
    const label = sel ? escapeHtml(sel.name) : "";
    const hid = selectedId ? String(selectedId) : "";
    const capV = Number.isFinite(Number(cap)) ? Number(cap) : 0;
    const nameAttr = mode === "create" ? ' name="campaign_offer_id[]"' : "";
    const capName = mode === "create" ? ' name="campaign_offer_cap[]"' : "";
    const viewsBlock =
        mode === "modal"
            ? `<span class="campaign-offer-views">${escapeHtml(String(viewsLabel))}</span>`
            : "";
    const cls = mode === "modal" ? "offer-combobox offer-combobox--modal" : "offer-combobox";
    return `<div class="campaign-offer-row">
    <div class="${cls}">
      <input type="text" class="offer-combobox__search field-input" autocomplete="off" placeholder="Type to search offers…" value="${label}">
      <input type="hidden" class="offer-combobox__id"${nameAttr} value="${hid}">
      <ul class="offer-combobox__list" role="listbox" hidden></ul>
    </div>
    <input type="number" class="edit_campaign_offers_cap field-input" min="0" step="1" value="${capV}" title="Cap"${capName}>
    ${viewsBlock}
    <button type="button" class="btn-danger" onclick="this.closest('.campaign-offer-row').remove()">×</button>
  </div>`;
}

/**
 * @param {HTMLElement} root .offer-combobox
 * @param {Array<{id:number|string,name:string}>} catalog
 */
function wireOfferCombobox(root, catalog) {
    if (!root || !catalog || !catalog.length) return;
    if (offerComboboxWired.has(root)) return;
    offerComboboxWired.add(root);

    const search = root.querySelector(".offer-combobox__search");
    const hidden = root.querySelector(".offer-combobox__id");
    const list = root.querySelector(".offer-combobox__list");
    if (!search || !hidden || !list) return;

    function filterList(q) {
        const s = (q || "").trim().toLowerCase();
        if (!s) return catalog.slice(0, 40);
        return catalog.filter((o) => String(o.name).toLowerCase().includes(s)).slice(0, 40);
    }

    function render(items) {
        list.innerHTML = "";
        if (items.length === 0) {
            const li = document.createElement("li");
            li.className = "offer-combobox__empty";
            li.textContent = "No matching offers";
            list.appendChild(li);
            list.hidden = false;
            return;
        }
        items.forEach((o) => {
            const li = document.createElement("li");
            li.className = "offer-combobox__option";
            li.setAttribute("role", "option");
            li.textContent = o.name;
            li.addEventListener("mousedown", (e) => {
                e.preventDefault();
                hidden.value = String(o.id);
                search.value = o.name;
                list.innerHTML = "";
                list.hidden = true;
            });
            list.appendChild(li);
        });
        list.hidden = false;
    }

    function openDropdown() {
        render(filterList(search.value));
    }

    let blurTimer;
    search.addEventListener("focus", () => {
        clearTimeout(blurTimer);
        openDropdown();
    });
    search.addEventListener("input", () => {
        hidden.value = "";
        openDropdown();
    });
    search.addEventListener("blur", () => {
        blurTimer = setTimeout(() => {
            list.hidden = true;
        }, 180);
    });
    list.addEventListener("mousedown", (e) => e.preventDefault());
}

/**
 * @param {HTMLElement|null} container
 * @param {Array<{id:number|string,name:string}>} catalog
 */
function initOfferComboboxesIn(container, catalog) {
    if (!container || !catalog || !catalog.length) return;
    container.querySelectorAll(".offer-combobox").forEach((cb) => {
        wireOfferCombobox(cb, catalog);
    });
}

// ============================
// DOM Content Loaded
// ============================
document.addEventListener("DOMContentLoaded", function () {
    const overlay = document.getElementById("overlay");
    const filter = document.getElementById("filter-popup");

    if (overlay) {
        overlay.addEventListener("mousedown", function (e) {
            if (e.target === this) CloseModal();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && overlay.style.display === "flex") CloseModal();
        });
    }

    if (filter) {
        filter.addEventListener("mousedown", function (e) {
            if (e.target === this) CloseFilter();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && filter.style.display === "block") CloseFilter();
        });
    }

    initCampaignCreateTracking();
    initCampaignCreateOfferRows();
    const coc = document.getElementById("campaign-offers-create");
    if (coc) initOfferComboboxesIn(coc, readOffersCatalog());
});

// ============================
// Modal Functions
// ============================
function CloseModal() {
    const overlay = document.getElementById('overlay');
    if (overlay) overlay.style.display = 'none';

    const slot = document.getElementById('modal-content');
    const home = document.getElementById('add-c');
    if (slot && home && slot.dataset.movedFromCreate === '1') {
        while (slot.firstChild) {
            home.appendChild(slot.firstChild);
        }
        delete slot.dataset.movedFromCreate;
    }
}

function CloseFilter() {
    document.getElementById('filter-popup').style.display = 'none';
}

const modal = {
    create() {
        const slot = document.getElementById('modal-content');
        const home = document.getElementById('add-c');
        const head = document.getElementById('add-h');
        const modalHead = document.getElementById('modal-h');
        if (!slot || !home || !head || !modalHead) return;

        slot.innerHTML = '';
        modalHead.innerText = head.innerText;

        while (home.firstChild) {
            slot.appendChild(home.firstChild);
        }
        slot.dataset.movedFromCreate = '1';

        document.getElementById('overlay').style.display = 'flex';
        initCampaignCreateTracking();
        initCampaignCreateOfferRows();
        initOfferComboboxesIn(slot, readOffersCatalog());
    },

    view(id) {

        fetch(`index.php?page=${this.getPage()}&action=view&id=${id}`)
            .then(res => res.json())
            .then(res => {
                const data = res.data;
                const extraData = res.extraData;
                const config = res.config;

                currentConfig = config;

                window.__emCampaignOffersCatalog = extraData.offers_list || [];

                document.getElementById('modal-h').innerText =
                    document.getElementById('edit-h').innerHTML;

                const slot = document.getElementById('modal-content');
                if (!slot) return;
                if (slot.dataset.movedFromCreate === '1') {
                    const home = document.getElementById('add-c');
                    if (home) {
                        while (slot.firstChild) {
                            home.appendChild(slot.firstChild);
                        }
                        delete slot.dataset.movedFromCreate;
                    }
                }

                slot.innerHTML = generateHTMLfields(data, config, extraData);

                initOfferComboboxesIn(slot, window.__emCampaignOffersCatalog);
                initEditTrackingGenerator();

                document.getElementById('overlay').style.display = 'flex';
            });


    },

    save() {
        const payload = collectFormData(currentConfig);
        fetch(`index.php?page=${this.getPage()}&action=save`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(res => res.json())
            .then(res => {
                if (res.success) {
                    CloseModal();
                    location.reload();
                } else {
                    alert("Error: " + res.error);
                }
            });
    },

    getPage() {
        const params = new URLSearchParams(window.location.search);
        return params.get("page") || "";
    },
};

// ============================
// Generate HTML Fields
// ============================
function generateHTMLfields(data, config, extraData) {
    let html = '';
    for (const key in config) {
        const f = config[key];
        if (!f.type) continue;
        const value = data[f.value] ?? "";

        switch (f.type) {
            case 'hidden':
                html += `<input type="hidden" id="edit_${key}" value="${value}">`;
                break;

            case 'checkbox':
                const checked = value == 1 ? "checked" : "";
                html += `<label>${f.th_title}</label><br>
                         <input type="checkbox" id="edit_${key}" ${checked}><br><br>`;
                break;

            case 'text':
            case 'url':
                html += `<label>${f.th_title}</label><br>
                         <input type="${f.type}" id="edit_${key}" class="field-input" value="${value}"><br><br>`;
                break;

            case 'url-locked':
                const baseUrl = value.includes('?') ? value.split('?')[0] + '?' : value;

                html += `<label>${f.th_title}</label><br>
             <input 
                type="url" 
                id="edit_${key}" 
                class="field-input" 
                value="${value}" 
                data-base_url="${baseUrl}" 
                disabled>
             <br><br>`;
                break;

            case 'datalist':
                const dataset = extraData[f.source] || [];
                const list = dataset.map(item => {
                    const val = item[f.valueKey];
                    const label = item[f.labelKey] ?? val;
                    const id = item['id'];
                    return `<option value="${val}" data-id="${id}">${label}</option>`;
                }).join('');

                html += `<label>${f.th_title}</label><br>
                         <input id="edit_${key}" list="${f.datalistId}" class="field-input" value="${value}">
                         <datalist id="${f.datalistId}">${list}</datalist><br><br>`;
                break;

            case 'campaign_offers': {
                const offers = extraData[f.source] || [];
                const rows = Array.isArray(data.campaign_offers) ? data.campaign_offers : [];
                const rowHtml = (row) => {
                    const r = row || { offer_id: "", cap: 0, current_views: 0 };
                    const oid = r.offer_id ?? "";
                    const cap = r.cap ?? 0;
                    const cv = r.current_views ?? 0;
                    return buildOfferRowMarkup(offers, oid, cap, `${cv} views`, "modal");
                };
                html += `<label>${escapeHtml(f.th_title)}</label>
                    <div id="campaign_offers_editor">`;
                if (rows.length === 0) {
                    html += rowHtml(null);
                } else {
                    rows.forEach((r) => {
                        html += rowHtml({
                            offer_id: r.offer_id,
                            cap: r.cap,
                            current_views: r.current_views,
                        });
                    });
                }
                html += `</div>
                    <button type="button" class="btn-secondary" onclick="addCampaignOfferRowModal()">+ Add offer</button><br><br>`;
                break;
            }

            case 'repeatable':
                // Add-row UI removed — rebuild when you reintroduce dynamic repeatables.
                html += `<label>${f.th_title}</label><br><div id="${key}_container">`;

                const values = Array.isArray(data[key]) ? data[key] : [];

                if (values.length === 0) {
                    // 👇 FORCE ONE EMPTY FIELD
                    html += `<div class="${key}-row">
                    <input type="${f.fieldType}" class="edit_${key}" placeholder="Enter ${key}">
                    <button type="button" onclick="this.parentElement.remove()" class="btn-danger">-</button>
                 </div>`;
                } else {
                    values.forEach(v => {
                        html += `<div class="${key}-row">
                        <input type="${f.fieldType}" class="edit_${key}" value="${v}">
                        <button type="button" onclick="this.parentElement.remove()" class="btn-danger">-</button>
                     </div>`;
                    });
                }

                html += `</div>
                    <button type="button" class="btn-secondary" onclick="addRepeatableRow('${key}', '${f.fieldType}')">+ Add</button><br><br>`;
                break;
        }
    }
    html += `<button onclick="modal.save()" class="btn-success">Save Changes</button>`;
    return html;
}

// ============================
// Collect Form Data
// ============================
function collectFormData(config) {
    const payload = {};
    for (const key in config) {
        const f = config[key];
        if (!f.type) continue;

        if (f.type === 'campaign_offers') {
            const rowEls = document.querySelectorAll('#modal-content .campaign-offer-row');
            const out = [];
            rowEls.forEach((row) => {
                const hid = row.querySelector('.offer-combobox__id');
                const capIn = row.querySelector('.edit_campaign_offers_cap');
                if (!hid || !capIn) return;
                const offer_id = parseInt(hid.value, 10);
                if (!offer_id) return;
                out.push({ offer_id, cap: parseInt(capIn.value, 10) || 0 });
            });
            payload.campaign_offers = out;
            continue;
        }

        if (f.type === 'repeatable') {
            const inputs = document.querySelectorAll(`#modal-content .edit_${key}`);
            const values = Array.from(inputs)
                .map(i => i.value.trim())
                .filter(Boolean);

            payload[key] = values;
            continue;
        }

        // normal single-input fields
        const input = document.getElementById(`edit_${key}`);
        if (!input) continue;

        let val;
        switch (f.type) {
            case 'checkbox':
                val = input.checked ? 1 : 0;
                break;
            case 'datalist':
                if (f.useValue) val = input.value;
                else val = getDatalistIdValue(input);
                payload[key + (f.useValue ? '' : '_id')] = val;
                continue;
            default:
                val = input.value;
        }

        payload[key + (f.type === 'datalist' ? '_id' : '')] = val;
    }

    return payload;
}

// ============================
// Datalist Helper
// ============================
function getDatalistIdValue(input) {
    const listId = input.getAttribute('list');
    if (!listId) return null;
    const option = Array.from(document.querySelectorAll(`#${listId} option`))
        .find(o => o.value === input.value);
    return option ? option.dataset.id : null;
}

// ============================
// Filters
// ============================
function OpenFilters(event) {
    const tableColumn = event.currentTarget.dataset.column;
    const existingValue = new URL(window.location.href).searchParams.get(tableColumn) || "";

    const overlay = document.getElementById("filter-popup");
    const popup = document.getElementById("filter-content");
    overlay.style.display = "block";

    const rect = event.currentTarget.getBoundingClientRect();
    let left = rect.left;
    let top = rect.bottom;

    popup.style.display = "block";
    const popupWidth = popup.offsetWidth;
    if (left + popupWidth > window.innerWidth) left = window.innerWidth - popupWidth - 10;
    if (left < 10) left = 10;

    popup.style.top = `${top}px`;
    popup.style.left = `${left}px`;

    document.getElementById("filter-content").innerHTML = `
        <div class="entity-filter-box">
            <input type="text" id="filterColumn" value="${tableColumn}" hidden>
            <input type="text" id="filterInput" placeholder="Filter value…" value="${existingValue}">
            <div class="entity-filter-buttons">
                <button type="button" onclick="ApplyFilter()" id="applybtn">Apply</button>
                <button type="button" onclick="ResetFilters()">Clear filters</button>
            </div>
        </div>
    `;
}

function ApplyFilter() {
    const filterColumn = document.getElementById("filterColumn").value;
    const filterValue = document.getElementById("filterInput").value;

    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set(filterColumn, filterValue);
    window.location.href = currentUrl.toString();
}

function ResetFilters() {
    const currentUrl = new URL(window.location.href);
    for (const key of [...currentUrl.searchParams.keys()]) {
        if (key.startsWith("filter_")) currentUrl.searchParams.delete(key);
    }
    window.location.href = currentUrl.toString();
}

// ============================
// UUID / Tracking
// ============================
function GenerateUUID() {
    return crypto.randomUUID();
}

function buildCampaignTrackingUrl(basePrefix, uuid, trafficName) {
    const t = (trafficName || "").toLowerCase().trim();
    let url = `${basePrefix}camid=${encodeURIComponent(uuid)}`;
    switch (t) {
        case "propellerads":
            url += "&clickid=${SUBID}&campaignid={campaign_id}&country={country}&zoneid={zoneid}&subzone_id={subzone_id}&region={region}&os={os}&osversion={osversion}&device={device}&browser={browser}&browser_version={browser_version}&carrier={carrier}&isp={isp}&connection_type={connection_type}&cost={cost}&useragent={useragent}&user_activity={user_activity}&bannerid={bannerid}&language={language}&payout={payout}&zone_type={zone_type}";
            break;
        case "hilltop":
            url += "&geo={{geo}}&zoneid={{zoneid}}&adid={{adid}}&campaignid={{campaignid}}&category={{category}}&cpmbid={{cpmbid}}&price={{price}}&browsername={{browsername}}&appname={{appname}}";
            break;
    }
    return url;
}

/** Create-campaign form (#traffic_source, #tracking_url, #uuid) — no-ops if absent. */
function initCampaignCreateTracking() {
    const trafficSelect = document.getElementById("traffic_source");
    const trackingField = document.getElementById("tracking_url");
    const uuidField = document.getElementById("uuid");
    if (!trafficSelect || !trackingField || !uuidField) return;

    if (!uuidField.value.trim()) {
        uuidField.value = GenerateUUID();
    }

    const basePrefix = trackingField.dataset.base_url || "";

    function sync() {
        const opt = trafficSelect.selectedOptions[0];
        const name = (opt && (opt.dataset.name || opt.textContent || "")).trim();
        trackingField.value = buildCampaignTrackingUrl(basePrefix, uuidField.value, name);
    }

    if (trafficSelect.dataset.trackingInit !== "1") {
        trafficSelect.dataset.trackingInit = "1";
        trafficSelect.addEventListener("change", sync);
    }
    sync();
}

function addCampaignOfferRowModal() {
    const editor = document.getElementById("campaign_offers_editor");
    if (!editor) return;
    const catalog =
        window.__emCampaignOffersCatalog && window.__emCampaignOffersCatalog.length
            ? window.__emCampaignOffersCatalog
            : readOffersCatalog();
    if (!catalog.length) return;
    const html = buildOfferRowMarkup(catalog, "", 0, "0 views", "modal");
    editor.insertAdjacentHTML("beforeend", html);
    const row = editor.lastElementChild;
    const cb = row && row.querySelector(".offer-combobox");
    if (cb) wireOfferCombobox(cb, catalog);
}

function addRepeatableRow(fieldKey, fieldType) {
    const container = document.getElementById(`${fieldKey}_container`);
    if (!container) return;
    const div = document.createElement("div");
    div.className = `${fieldKey}-row`;
    div.innerHTML = `<input type="${fieldType}" class="edit_${fieldKey}" placeholder="Enter ${fieldKey}">
        <button type="button" onclick="this.parentElement.remove()" class="btn-danger">-</button>`;
    container.appendChild(div);
}

function initCampaignCreateOfferRows() {
    const addBtn = document.getElementById("add-campaign-offer-row");
    if (!addBtn || addBtn.dataset.bound === "1") return;
    addBtn.dataset.bound = "1";
    addBtn.addEventListener("click", () => {
        const wrap = document.getElementById("campaign-offers-create");
        if (!wrap) return;
        const catalog = readOffersCatalog();
        if (!catalog.length) return;
        const html = buildOfferRowMarkup(catalog, "", 0, "", "create");
        wrap.insertAdjacentHTML("beforeend", html);
        const row = wrap.lastElementChild;
        const cb = row && row.querySelector(".offer-combobox");
        if (cb) wireOfferCombobox(cb, catalog);
    });
}

function initEditTrackingGenerator() {
    const trafficInput = document.getElementById("edit_traffic_source");
    const trackingField = document.getElementById("edit_tracking_url");
    const uuidField = document.getElementById("edit_uuid");

    if (!trafficInput || !trackingField || !uuidField) return;

    const baseUrl = trackingField.dataset.base_url || trackingField.value.split('?')[0] + '?';

    trafficInput.addEventListener("input", function () {
        const listId = trafficInput.getAttribute("list");

        const option = Array.from(document.querySelectorAll(`#${listId} option`))
            .find(o => o.value === trafficInput.value);

        if (!option) return;

        const trafficName = trafficInput.value.toLowerCase();
        const uuid = uuidField.value;

        trackingField.value = buildCampaignTrackingUrl(baseUrl, uuid, trafficName);
    });
}