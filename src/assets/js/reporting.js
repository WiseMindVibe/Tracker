document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('reporting-root');
    if (!root) {
        return;
    }

    const apiEndpoint = root.dataset.apiEndpoint || './api/report/v2/index.php';
    const groupLabels = parseJson(root.dataset.groupLabels || '{}', {});
    const metricKeys = parseJson(root.dataset.metricKeys || '[]', []);
    const defaultSortBy = String(root.dataset.defaultSortBy || 'clicks');
    const defaultSortDir = String(root.dataset.defaultSortDir || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
    const sortButtons = Array.from(document.querySelectorAll('.rep-sort-btn[data-sort-key]'));
    const fieldLabels = {
        offer_id: 'Offer',
        campaign_id: 'Campaign',
        external_campaign_id: 'External Campaign ID',
        os: 'OS',
        browser: 'Browser',
        country: 'Country',
        region: 'Region',
        language: 'Language',
        device: 'Device',
        os_version: 'OS Version',
        browser_version: 'Browser Version',
        connection_type: 'Connection Type',
        carrier: 'Carrier',
        isp: 'ISP',
        zoneid: 'Zone ID',
        subzone_id: 'Subzone ID',
    };

    const presetSelect = document.getElementById('rep-date-preset');
    const dateFromInput = document.getElementById('rep-date-from');
    const dateToInput = document.getElementById('rep-date-to');
    const customRangeWrap = document.getElementById('rep-custom-range-wrap');
    const groupSelect = document.getElementById('rep-group-select');
    const groupOrder = document.getElementById('rep-group-order');
    const applyButton = document.getElementById('rep-apply-btn');
    const summary = document.getElementById('rep-active-summary');
    const tbody = document.getElementById('rep-tbody');
    const tfoot = document.getElementById('rep-tfoot');
    const globalProgress = document.getElementById('rep-global-progress');
    const globalProgressText = document.getElementById('rep-global-progress-text');

    if (
        !presetSelect
        || !dateFromInput
        || !dateToInput
        || !customRangeWrap
        || !groupSelect
        || !groupOrder
        || !applyButton
        || !summary
        || !tbody
        || !tfoot
    ) {
        return;
    }

    const quickPresetButtons = Array.from(document.querySelectorAll('[data-range-preset]'));
    const sliceCache = new Map();
    let currentRequestSerial = 0;
    let sortable = null;
    let activeNetworkRequests = 0;
    let rowRequestTokenCounter = 0;
    const rowRequestTokens = new WeakMap();
    /** @type {{date_preset: string, date_from: string, date_to: string, group_by: string[]}|null} */
    let appliedContext = null;
    let sortBy = defaultSortBy;
    let sortDir = defaultSortDir;

    function parseJson(raw, fallback) {
        try {
            const parsed = JSON.parse(raw);
            return parsed ?? fallback;
        } catch (_error) {
            return fallback;
        }
    }

    function cloneContext(context) {
        return {
            date_preset: context.date_preset,
            date_from: context.date_from,
            date_to: context.date_to,
            group_by: Array.isArray(context.group_by) ? [...context.group_by] : [],
        };
    }

    function setEmptyState(message) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.className = 'rep-empty';
        td.colSpan = metricKeys.length + 2;
        td.textContent = message;
        tr.appendChild(td);
        tbody.innerHTML = '';
        tbody.appendChild(tr);
    }

    function updateGlobalLoadingVisibility() {
        if (!globalProgress) {
            return;
        }

        const isVisible = activeNetworkRequests > 0;
        globalProgress.classList.toggle('is-hidden', !isVisible);
        globalProgress.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
    }

    function beginGlobalLoading(message) {
        activeNetworkRequests += 1;
        if (globalProgressText && message) {
            globalProgressText.textContent = message;
        }
        updateGlobalLoadingVisibility();
    }

    function endGlobalLoading() {
        activeNetworkRequests = Math.max(0, activeNetworkRequests - 1);
        updateGlobalLoadingVisibility();
    }

    function setTopLevelBusy(busy) {
        applyButton.disabled = busy;
        sortButtons.forEach((button) => {
            button.disabled = busy;
        });
    }

    function toggleCustomRangeVisibility() {
        const isCustom = presetSelect.value === 'custom';
        customRangeWrap.classList.toggle('is-hidden', !isCustom);
        customRangeWrap.setAttribute('aria-hidden', isCustom ? 'false' : 'true');
    }

    function selectedGroupsFromOrder() {
        return Array.from(groupOrder.children)
            .map((li) => String(li.dataset.value || '').trim())
            .filter((value) => value !== '');
    }

    function syncGroupOrderFromSelection() {
        const selectedValues = Array.from(groupSelect.selectedOptions).map((option) => option.value);
        const existingOrder = Array.from(groupOrder.children).map((li) => li.dataset.value);
        const ordered = [];

        for (const value of existingOrder) {
            if (selectedValues.includes(value)) {
                ordered.push(value);
            }
        }
        for (const value of selectedValues) {
            if (!ordered.includes(value)) {
                ordered.push(value);
            }
        }

        groupOrder.innerHTML = '';
        for (const value of ordered) {
            const li = document.createElement('li');
            li.dataset.value = value;
            li.textContent = groupLabels[value] || value;
            groupOrder.appendChild(li);
        }
    }

    function initSortable() {
        if (typeof Sortable === 'undefined') {
            return;
        }
        if (sortable) {
            sortable.destroy();
            sortable = null;
        }
        sortable = Sortable.create(groupOrder, {
            animation: 150,
        });
    }

    function setQuickPresetActive(presetKey) {
        quickPresetButtons.forEach((button) => {
            button.classList.toggle('is-active', button.getAttribute('data-range-preset') === presetKey);
        });
    }

    function refreshSortButtonsUi() {
        sortButtons.forEach((button) => {
            const key = button.getAttribute('data-sort-key');
            const isActive = key === sortBy;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            const indicator = button.querySelector('.rep-sort-ind');
            if (indicator) {
                indicator.textContent = isActive ? (sortDir === 'asc' ? '↑' : '↓') : '↕';
            }

            const th = button.closest('th');
            if (th) {
                th.setAttribute('aria-sort', isActive ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none');
            }
        });
    }

    function collectApplyContext() {
        const groupBy = selectedGroupsFromOrder();
        if (groupBy.length === 0) {
            throw new Error('Select at least one grouping dimension.');
        }

        const preset = String(presetSelect.value || 'today');
        const context = {
            date_preset: preset,
            date_from: '',
            date_to: '',
            group_by: groupBy,
        };

        if (preset === 'custom') {
            const from = String(dateFromInput.value || '').trim();
            const to = String(dateToInput.value || '').trim();
            if (!from || !to) {
                throw new Error('Custom date range requires both start and end dates.');
            }
            if (from > to) {
                throw new Error('Custom start date cannot be greater than end date.');
            }
            context.date_from = from;
            context.date_to = to;
        }

        return context;
    }

    function payloadForLevel(context, level, parentFilters, includeTotals = true) {
        const payload = {
            date_preset: context.date_preset,
            group_by: context.group_by,
            level,
            parent_filters: parentFilters,
            sort_by: sortBy,
            sort_dir: sortDir,
            include_totals: includeTotals,
        };
        if (context.date_preset === 'custom') {
            payload.date_from = context.date_from;
            payload.date_to = context.date_to;
        }
        return payload;
    }

    async function requestLevel(context, level, parentFilters, includeTotals = true) {
        const payload = payloadForLevel(context, level, parentFilters, includeTotals);
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });

        let data = null;
        try {
            data = await response.json();
        } catch (_error) {
            throw new Error('Invalid response from reporting API.');
        }

        if (!response.ok) {
            throw new Error(data && data.error ? data.error : 'Request failed.');
        }

        if (!data || !Array.isArray(data.rows)) {
            throw new Error('Reporting API response is missing rows.');
        }

        return data;
    }

    function formatCell(metricKey, rawValue) {
        const moneyKeys = new Set([
            'open_conversions_sum',
            'confirmed_conversions_sum',
            'rejected_conversions_sum',
            'paid_conversions_sum',
            'revenue',
            'spent',
            'profit',
            'avg_payout',
        ]);
        const percentageKeys = new Set(['cr', 'roi']);
        const integerKeys = new Set([
            'clicks',
            'total_conversions',
            'open_conversions',
            'confirmed_conversions',
            'rejected_conversions',
            'paid_conversions',
        ]);

        if (rawValue === null || rawValue === undefined) {
            return '—';
        }

        if (moneyKeys.has(metricKey)) {
            return `$${Number(rawValue).toFixed(2)}`;
        }
        if (percentageKeys.has(metricKey)) {
            return `${Number(rawValue).toFixed(2)}%`;
        }
        if (integerKeys.has(metricKey)) {
            return Number(rawValue).toLocaleString();
        }

        return String(rawValue);
    }

    function cellClass(metricKey, rawValue) {
        const base = 'rep-num';
        if (metricKey === 'spent' || metricKey === 'rejected_conversions_sum') {
            return `${base} rep-val--cost`;
        }
        if (metricKey === 'profit' || metricKey === 'revenue') {
            const value = Number(rawValue || 0);
            if (value > 0) {
                return `${base} rep-val--pos`;
            }
            if (value < 0) {
                return `${base} rep-val--neg`;
            }
            return `${base} rep-val--muted`;
        }
        if (metricKey === 'roi') {
            if (rawValue === null || rawValue === undefined) {
                return `${base} rep-val--neg`;
            }
            const value = Number(rawValue);
            if (value < 0) {
                return `${base} rep-val--neg`;
            }
            if (value <= 300) {
                return `${base} rep-val--warn`;
            }
            return `${base} rep-val--pos`;
        }
        if (metricKey === 'cr') {
            const value = Number(rawValue || 0);
            if (value < 0.05) {
                return `${base} rep-val--neg`;
            }
            if (value < 0.25) {
                return `${base} rep-val--warn`;
            }
            return `${base} rep-val--pos`;
        }
        return base;
    }

    function markerNode(roiValue) {
        const marker = document.createElement('span');
        const roi = roiValue === null || roiValue === undefined ? null : Number(roiValue);
        const isPositive = roi !== null && roi >= 100;
        marker.className = `rep-marker ${isPositive ? 'rep-marker--good' : 'rep-marker--bad'}`;
        marker.textContent = isPositive ? '+' : '-';
        marker.title = roi === null ? 'ROI —' : `ROI ${roi.toFixed(2)}%`;
        return marker;
    }

    function removeChildRows(parentRow) {
        const parentDepth = Number(parentRow.dataset.depth || 0);
        let next = parentRow.nextElementSibling;
        while (next && next.classList.contains('rep-row')) {
            const nextDepth = Number(next.dataset.depth || 0);
            if (nextDepth <= parentDepth) {
                break;
            }
            const toRemove = next;
            next = next.nextElementSibling;
            toRemove.remove();
        }
    }

    function setExpanded(row, expanded) {
        row.classList.toggle('rep-row--expanded', expanded);
        const toggle = row.querySelector('.rep-toggle');
        if (!toggle) {
            return;
        }
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded ? '▾' : '▸';
    }

    function setLoading(row, loading) {
        row.classList.toggle('rep-row--loading', loading);
        const toggle = row.querySelector('.rep-toggle');
        if (!toggle) {
            return;
        }
        toggle.disabled = loading;
        if (loading) {
            toggle.textContent = '…';
        } else if (!row.classList.contains('rep-row--expanded')) {
            toggle.textContent = '▸';
        } else {
            toggle.textContent = '▾';
        }
    }

    function clearRowLoadingStates() {
        tbody.querySelectorAll('tr.rep-row--loading').forEach((row) => {
            setLoading(row, false);
        });
    }

    function createDataRow(rowData, depth) {
        const tr = document.createElement('tr');
        tr.className = `rep-row rep-row--depth-${depth}${rowData.can_expand ? ' rep-row--parent' : ''}`;
        tr.dataset.depth = String(depth);
        tr.dataset.parentFilters = JSON.stringify(rowData.parent_filters || {});
        tr.dataset.canExpand = rowData.can_expand ? '1' : '0';
        tr.dataset.loaded = '0';

        const markerCell = document.createElement('td');
        markerCell.className = 'rep-td-marker';
        markerCell.appendChild(markerNode(rowData.roi));
        tr.appendChild(markerCell);

        const groupCell = document.createElement('td');
        groupCell.className = 'rep-group-cell';

        const indent = document.createElement('span');
        indent.className = 'rep-indent';
        indent.style.width = `${1.1 + (Math.max(0, depth - 1) * 1.1)}rem`;
        groupCell.appendChild(indent);

        if (rowData.can_expand) {
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'rep-toggle';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Expand nested groups');
            toggle.textContent = '▸';
            groupCell.appendChild(toggle);
        } else {
            const spacer = document.createElement('span');
            spacer.className = 'rep-toggle-spacer';
            groupCell.appendChild(spacer);
        }

        const name = document.createElement('span');
        name.className = 'rep-group-name';
        const tag = document.createElement('span');
        tag.className = 'rep-dim-chip';
        tag.textContent = fieldLabels[rowData.group_field] || rowData.group_field || 'Group';
        const nameText = document.createTextNode(` ${String(rowData.group_name || '(none)')}`);
        name.appendChild(tag);
        name.appendChild(nameText);
        groupCell.appendChild(name);
        tr.appendChild(groupCell);

        for (const metricKey of metricKeys) {
            const td = document.createElement('td');
            const rawValue = rowData[metricKey];
            td.className = cellClass(metricKey, rawValue);
            td.dataset.metric = metricKey;
            td.textContent = formatCell(metricKey, rawValue);
            tr.appendChild(td);
        }

        return tr;
    }

    function insertRowsAfterRow(parentRow, rows, depth) {
        let cursor = parentRow;
        if (!rows.length) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = `rep-row rep-row--depth-${depth}`;
            emptyRow.dataset.depth = String(depth);
            const emptyCell = document.createElement('td');
            emptyCell.className = 'rep-empty';
            emptyCell.colSpan = metricKeys.length + 2;
            emptyCell.textContent = 'No nested rows for this group.';
            emptyRow.appendChild(emptyCell);
            cursor.insertAdjacentElement('afterend', emptyRow);
            return;
        }
        for (const rowData of rows) {
            const node = createDataRow(rowData, depth);
            cursor.insertAdjacentElement('afterend', node);
            cursor = node;
        }
    }

    function renderTopLevelRows(rows) {
        tbody.innerHTML = '';
        if (!rows.length) {
            setEmptyState('No rows for this filter combination.');
            return;
        }
        rows.forEach((rowData) => {
            tbody.appendChild(createDataRow(rowData, 1));
        });
    }

    function renderTotals(totals) {
        if (!totals || typeof totals !== 'object') {
            tfoot.classList.add('is-hidden');
            return;
        }
        const cells = tfoot.querySelectorAll('[data-total-key]');
        cells.forEach((cell) => {
            const key = cell.getAttribute('data-total-key');
            if (!key) {
                return;
            }
            cell.textContent = formatCell(key, totals[key]);
            cell.className = cellClass(key, totals[key]);
        });
        tfoot.classList.remove('is-hidden');
    }

    function updateSummaryLine(data) {
        const labels = (appliedContext?.group_by || []).map((group) => groupLabels[group] || group);
        const from = data.date_from || '';
        const to = data.date_to || '';
        const rangeText = from && to && from !== to ? `${from} - ${to}` : (from || to || 'n/a');
        summary.textContent = `${rangeText} | ${labels.join(' -> ')}`;
    }

    async function loadTopLevelReport(context) {
        currentRequestSerial += 1;
        const serialAtStart = currentRequestSerial;
        appliedContext = cloneContext(context);
        sliceCache.clear();
        clearRowLoadingStates();

        setTopLevelBusy(true);
        beginGlobalLoading('Loading report...');
        setEmptyState('Loading report...');
        tfoot.classList.add('is-hidden');

        try {
            const data = await requestLevel(appliedContext, 0, {}, true);
            if (serialAtStart !== currentRequestSerial) {
                return;
            }
            appliedContext.date_from = data.date_from || appliedContext.date_from;
            appliedContext.date_to = data.date_to || appliedContext.date_to;
            renderTopLevelRows(data.rows);
            renderTotals(data.totals || null);
            updateSummaryLine(data);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to load report.';
            setEmptyState(message);
            tfoot.classList.add('is-hidden');
            summary.textContent = 'No report loaded.';
        } finally {
            endGlobalLoading();
            if (serialAtStart === currentRequestSerial) {
                setTopLevelBusy(false);
            }
        }
    }

    async function handleApplyClick() {
        let context;
        try {
            context = collectApplyContext();
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Invalid filters.');
            return;
        }

        await loadTopLevelReport(context);
    }

    async function expandRow(row) {
        if (!appliedContext) {
            return;
        }

        const depth = Number(row.dataset.depth || 1);
        const nextLevel = depth;
        const parentFiltersSerialized = row.dataset.parentFilters || '{}';
        const cacheKey = `${nextLevel}:${sortBy}:${sortDir}:${parentFiltersSerialized}`;
        const serialAtStart = currentRequestSerial;

        if (sliceCache.has(cacheKey)) {
            const cachedRows = sliceCache.get(cacheKey);
            insertRowsAfterRow(row, cachedRows, depth + 1);
            setExpanded(row, true);
            row.dataset.loaded = '1';
            return;
        }

        let parentFilters = {};
        try {
            parentFilters = parseJson(parentFiltersSerialized, {});
        } catch (_error) {
            parentFilters = {};
        }

        rowRequestTokenCounter += 1;
        const rowToken = rowRequestTokenCounter;
        rowRequestTokens.set(row, rowToken);

        setLoading(row, true);
        beginGlobalLoading('Loading drill-down...');
        try {
            const data = await requestLevel(appliedContext, nextLevel, parentFilters, false);
            if (serialAtStart !== currentRequestSerial) {
                return;
            }
            if (rowRequestTokens.get(row) !== rowToken) {
                return;
            }
            const rows = Array.isArray(data.rows) ? data.rows : [];
            sliceCache.set(cacheKey, rows);
            insertRowsAfterRow(row, rows, depth + 1);
            setExpanded(row, true);
            row.dataset.loaded = '1';
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Could not load nested rows.');
        } finally {
            endGlobalLoading();
            if (rowRequestTokens.get(row) === rowToken) {
                rowRequestTokens.delete(row);
            }
            if (row.isConnected) {
                setLoading(row, false);
            }
        }
    }

    tbody.addEventListener('click', async (event) => {
        const trigger = event.target.closest('.rep-toggle, .rep-group-cell');
        if (!trigger) {
            return;
        }
        const row = trigger.closest('tr.rep-row');
        if (!row || row.dataset.canExpand !== '1') {
            return;
        }
        if (row.classList.contains('rep-row--loading')) {
            return;
        }

        const isExpanded = row.classList.contains('rep-row--expanded');
        if (isExpanded) {
            removeChildRows(row);
            setExpanded(row, false);
            row.dataset.loaded = '0';
            return;
        }

        await expandRow(row);
    });

    groupSelect.addEventListener('change', syncGroupOrderFromSelection);

    quickPresetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.getAttribute('data-range-preset');
            if (!preset) {
                return;
            }
            presetSelect.value = preset;
            setQuickPresetActive(preset);
            toggleCustomRangeVisibility();
        });
    });

    presetSelect.addEventListener('change', () => {
        setQuickPresetActive(presetSelect.value);
        toggleCustomRangeVisibility();
    });

    sortButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const key = button.getAttribute('data-sort-key');
            if (!key) {
                return;
            }

            if (sortBy === key) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortBy = key;
                sortDir = key === 'group_name' ? 'asc' : 'desc';
            }
            refreshSortButtonsUi();

            if (appliedContext) {
                await loadTopLevelReport(appliedContext);
            }
        });
    });

    applyButton.addEventListener('click', handleApplyClick);

    syncGroupOrderFromSelection();
    initSortable();
    toggleCustomRangeVisibility();
    setQuickPresetActive(presetSelect.value);
    refreshSortButtonsUi();
});
