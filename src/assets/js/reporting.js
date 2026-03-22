document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('reporting-root');
    const rangeInput = document.getElementById('rep-range');
    const startHidden = document.getElementById('rep-start-hidden');
    const endHidden = document.getElementById('rep-end-hidden');
    const selectEl = document.getElementById('groupSelect');
    const groupList = document.getElementById('groupOrder');
    const groupsInput = document.getElementById('groupsInput');
    const groupsHiddenRange = document.getElementById('reporting-groups-hidden');
    const mainForm = document.getElementById('rep-main-form');
    const rangeForm = document.querySelector('.reporting-range-form');

    if (root && rangeInput && startHidden && endHidden && typeof Litepicker !== 'undefined') {
        const ds = root.dataset.start || '';
        const de = root.dataset.end || '';
        if (ds && de) {
            rangeInput.value = `${ds} → ${de}`;
        }

        new Litepicker({
            element: rangeInput,
            singleMode: false,
            numberOfMonths: 2,
            numberOfColumns: 2,
            format: 'YYYY-MM-DD',
            autoApply: true,
            showTooltip: true,
            setup: (p) => {
                p.on('selected', (start, end) => {
                    const startStr = start.format('YYYY-MM-DD');
                    const endStr = end.format('YYYY-MM-DD');
                    startHidden.value = startStr;
                    endHidden.value = endStr;
                    rangeInput.value = `${startStr} → ${endStr}`;
                });
            },
        });
    }

    function syncGroupsHidden() {
        if (!groupList || !groupsInput) {
            return;
        }
        const values = Array.from(groupList.children).map((li) => li.dataset.value);
        const serialized = values.join(',');
        groupsInput.value = serialized;
        if (groupsHiddenRange) {
            groupsHiddenRange.value = serialized;
        }
    }

    function syncGroupOrderFromSelect() {
        if (!selectEl || !groupList) {
            return;
        }
        const selected = Array.from(selectEl.selectedOptions).map((o) => o.value);
        const existing = Array.from(groupList.children).map((li) => li.dataset.value);
        const ordered = [];
        for (const v of existing) {
            if (selected.includes(v)) {
                ordered.push(v);
            }
        }
        for (const v of selected) {
            if (!ordered.includes(v)) {
                ordered.push(v);
            }
        }
        groupList.innerHTML = '';
        for (const g of ordered) {
            const opt = Array.from(selectEl.options).find((o) => o.value === g);
            const li = document.createElement('li');
            li.dataset.value = g;
            li.textContent = opt ? opt.textContent.trim() : g;
            groupList.appendChild(li);
        }
        syncGroupsHidden();
    }

    let sortable = null;

    function bindSortable() {
        if (!groupList || typeof Sortable === 'undefined') {
            return;
        }
        if (sortable) {
            sortable.destroy();
            sortable = null;
        }
        sortable = Sortable.create(groupList, {
            animation: 150,
            onEnd: syncGroupsHidden,
        });
    }

    bindSortable();
    syncGroupsHidden();

    if (selectEl) {
        selectEl.addEventListener('change', () => {
            syncGroupOrderFromSelect();
            bindSortable();
        });
    }

    if (mainForm) {
        mainForm.addEventListener('submit', () => {
            syncGroupsHidden();
        });
    }

    if (rangeForm) {
        rangeForm.addEventListener('submit', () => {
            syncGroupsHidden();
        });
    }

    const tbody = document.getElementById('rep-tbody');
    if (tbody) {
        tbody.addEventListener('click', (e) => {
            const btn = e.target.closest('.rep-toggle');
            const cell = e.target.closest('.rep-group-cell');
            if (!btn && !cell) {
                return;
            }
            const row = (btn || cell).closest('tr.rep-row');
            if (!row || !row.classList.contains('rep-row--parent')) {
                return;
            }

            const levelMatch = row.className.match(/rep-row--level-(\d+)/);
            if (!levelMatch) {
                return;
            }
            const level = parseInt(levelMatch[1], 10);
            const expanding = !row.classList.contains('rep-row--expanded');

            let next = row.nextElementSibling;
            while (next) {
                const nm = next.className.match(/rep-row--level-(\d+)/);
                if (!nm) {
                    break;
                }
                const nextLevel = parseInt(nm[1], 10);
                if (nextLevel <= level) {
                    break;
                }

                if (expanding && nextLevel === level + 1) {
                    next.removeAttribute('hidden');
                }
                if (!expanding) {
                    next.setAttribute('hidden', '');
                    next.classList.remove('rep-row--expanded');
                    const innerBtn = next.querySelector('.rep-toggle');
                    if (innerBtn) {
                        innerBtn.setAttribute('aria-expanded', 'false');
                        innerBtn.textContent = '▸';
                    }
                }
                next = next.nextElementSibling;
            }

            row.classList.toggle('rep-row--expanded', expanding);
            const toggle = row.querySelector('.rep-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', expanding ? 'true' : 'false');
                toggle.textContent = expanding ? '▾' : '▸';
            }
        });
    }
});
