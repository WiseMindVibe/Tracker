import { Head } from '@inertiajs/react';
import { useCallback, useMemo, useRef, useState } from 'react';
import ReportRow from './components/ReportRow';
import { formatCell } from './format';
import './reporting.css';
import { postJson } from './request';
import type { AppliedContext, MetricColumn, ReportApiResponse, ReportRowState, ReportTotals, ReportingIndexProps } from './types';

const RANGE_PRESETS: Array<[string, string]> = [
    ['today', 'Today'],
    ['yesterday', 'Yesterday'],
    ['last7', 'Last 7 days'],
    ['this_week', 'This week'],
    ['last_week', 'Last week'],
    ['this_month', 'This month'],
    ['last30', 'Last 30 days'],
    ['last_month', 'Last month'],
    ['this_year', 'This year'],
    ['last_year', 'Last year'],
    ['all_time', 'All time'],
];

const METRIC_COLUMNS: MetricColumn[] = [
    ['clicks', 'Clicks'],
    ['total_conversions', 'Total conv.'],
    ['open_conversions', 'Open #'],
    ['open_conversions_sum', 'Open $'],
    ['confirmed_conversions', 'Confirmed #'],
    ['confirmed_conversions_sum', 'Confirmed $'],
    ['rejected_conversions', 'Rejected #'],
    ['rejected_conversions_sum', 'Rejected $'],
    ['paid_conversions', 'Paid #'],
    ['paid_conversions_sum', 'Paid $'],
    ['revenue', 'Revenue'],
    ['spent', 'Spent'],
    ['profit', 'Profit'],
    ['loss', 'Loss'],
    ['cr', 'CR'],
    ['roi', 'ROI'],
    ['avg_payout', 'Avg payout'],
];

function fieldToken(row: ReportRowState): string {
    return JSON.stringify(row.parent_filters || {});
}

type Status = 'idle' | 'loading' | 'error';

export default function ReportingIndex({
    defaultDatePreset,
    defaultDateFrom,
    defaultDateTo,
    defaultGroupBy,
    availableGroups,
    apiEndpoint,
}: ReportingIndexProps) {
    const [datePreset, setDatePreset] = useState(defaultDatePreset);
    const [dateFrom, setDateFrom] = useState(defaultDateFrom);
    const [dateTo, setDateTo] = useState(defaultDateTo);
    const [groupBy, setGroupBy] = useState<string[]>(defaultGroupBy);
    const [sortBy, setSortBy] = useState('clicks');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');

    const [appliedContext, setAppliedContext] = useState<AppliedContext | null>(null);
    const [rows, setRows] = useState<ReportRowState[]>([]);
    const [totals, setTotals] = useState<ReportTotals | null>(null);
    const [status, setStatus] = useState<Status>('idle');
    const [errorMessage, setErrorMessage] = useState('');
    const [activeRequests, setActiveRequests] = useState(0);

    const sliceCache = useRef(new Map<string, ReportRowState[]>());
    const requestSerial = useRef(0);

    const remainingGroups = useMemo(
        () => Object.keys(availableGroups).filter((g) => !groupBy.includes(g)),
        [availableGroups, groupBy]
    );

    const moveGroup = (index: number, delta: number) => {
        setGroupBy((prev) => {
            const next = [...prev];
            const target = index + delta;
            if (target < 0 || target >= next.length) return prev;
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    };

    const addGroup = (value: string) => {
        if (!value || groupBy.includes(value)) return;
        setGroupBy((prev) => [...prev, value]);
    };

    const removeGroup = (value: string) => {
        setGroupBy((prev) => prev.filter((g) => g !== value));
    };

    const buildContext = useCallback((): AppliedContext => {
        if (groupBy.length === 0) {
            throw new Error('Select at least one grouping dimension.');
        }
        const context: AppliedContext = { date_preset: datePreset, date_from: '', date_to: '', group_by: groupBy };
        if (datePreset === 'custom') {
            if (!dateFrom || !dateTo) {
                throw new Error('Custom date range requires both start and end dates.');
            }
            if (dateFrom > dateTo) {
                throw new Error('Custom start date cannot be greater than end date.');
            }
            context.date_from = dateFrom;
            context.date_to = dateTo;
        }
        return context;
    }, [datePreset, dateFrom, dateTo, groupBy]);

    const requestLevel = useCallback(
        async (
            context: AppliedContext,
            level: number,
            parentFilters: Record<string, number | string>,
            includeTotals: boolean,
            sortByArg: string,
            sortDirArg: 'asc' | 'desc'
        ): Promise<ReportApiResponse> => {
            const payload: Record<string, unknown> = {
                date_preset: context.date_preset,
                group_by: context.group_by,
                level,
                parent_filters: parentFilters,
                sort_by: sortByArg,
                sort_dir: sortDirArg,
                include_totals: includeTotals,
            };
            if (context.date_preset === 'custom') {
                payload.date_from = context.date_from;
                payload.date_to = context.date_to;
            }

            setActiveRequests((n) => n + 1);
            try {
                return await postJson<ReportApiResponse>(apiEndpoint, payload);
            } finally {
                setActiveRequests((n) => Math.max(0, n - 1));
            }
        },
        [apiEndpoint]
    );

    const loadTopLevel = useCallback(
        async (context: AppliedContext, sortByArg: string, sortDirArg: 'asc' | 'desc') => {
            requestSerial.current += 1;
            const serial = requestSerial.current;
            sliceCache.current.clear();
            setStatus('loading');
            setErrorMessage('');
            setTotals(null);

            try {
                const data = await requestLevel(context, 0, {}, true, sortByArg, sortDirArg);
                if (serial !== requestSerial.current) return;
                setRows(data.rows.map((r) => ({ ...r, expanded: false, loading: false, children: null })));
                setTotals(data.totals || null);
                setAppliedContext({ ...context, date_from: data.date_from, date_to: data.date_to });
                setStatus('idle');
            } catch (err) {
                if (serial !== requestSerial.current) return;
                setErrorMessage(err instanceof Error ? err.message : 'Unable to load report.');
                setRows([]);
                setStatus('error');
            }
        },
        [requestLevel]
    );

    const handleApply = async () => {
        let context: AppliedContext;
        try {
            context = buildContext();
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Invalid filters.');
            return;
        }
        await loadTopLevel(context, sortBy, sortDir);
    };

    const handleSort = async (key: string) => {
        const nextDir: 'asc' | 'desc' = sortBy === key ? (sortDir === 'asc' ? 'desc' : 'asc') : key === 'group_name' ? 'asc' : 'desc';
        setSortBy(key);
        setSortDir(nextDir);
        if (appliedContext) {
            await loadTopLevel(appliedContext, key, nextDir);
        }
    };

    const updateRowAtPath = useCallback((path: number[], updater: (row: ReportRowState) => ReportRowState) => {
        setRows((prevRows) => {
            const next = [...prevRows];
            let list = next;
            for (let i = 0; i < path.length - 1; i++) {
                const idx = path[i];
                list[idx] = { ...list[idx], children: [...(list[idx].children || [])] };
                list = list[idx].children as ReportRowState[];
            }
            const lastIdx = path[path.length - 1];
            list[lastIdx] = updater(list[lastIdx]);
            return next;
        });
    }, []);

    const toggleRow = useCallback(
        async (path: number[], row: ReportRowState, depth: number) => {
            if (!row.can_expand || !appliedContext) return;

            if (row.expanded) {
                updateRowAtPath(path, (r) => ({ ...r, expanded: false }));
                return;
            }

            const level = depth; // depth 1 row expands into level 1, etc.
            const cacheKey = `${level}:${sortBy}:${sortDir}:${fieldToken(row)}`;

            const cached = sliceCache.current.get(cacheKey);
            if (cached) {
                updateRowAtPath(path, (r) => ({ ...r, expanded: true, children: cached }));
                return;
            }

            updateRowAtPath(path, (r) => ({ ...r, loading: true }));
            try {
                const data = await requestLevel(appliedContext, level, row.parent_filters || {}, false, sortBy, sortDir);
                const children: ReportRowState[] = (data.rows || []).map((r) => ({ ...r, expanded: false, loading: false, children: null }));
                sliceCache.current.set(cacheKey, children);
                updateRowAtPath(path, (r) => ({ ...r, expanded: true, loading: false, children }));
            } catch (err) {
                window.alert(err instanceof Error ? err.message : 'Could not load nested rows.');
                updateRowAtPath(path, (r) => ({ ...r, loading: false }));
            }
        },
        [appliedContext, requestLevel, sortBy, sortDir, updateRowAtPath]
    );

    const summaryLine = appliedContext
        ? `${appliedContext.date_from || ''}${appliedContext.date_from !== appliedContext.date_to ? ' - ' + appliedContext.date_to : ''} | ${appliedContext.group_by.map((g) => availableGroups[g] || g).join(' -> ')}`
        : 'No report loaded.';

    return (
        <div className="reporting-page">
            <Head title="Reporting" />

            <header className="reporting-header">
                <div className="reporting-header__titles">
                    <h1 className="reporting-title">Reporting</h1>
                    <p className="reporting-subtitle">
                        Apply filters to load level 1 only. Expand rows to fetch deeper levels lazily.
                    </p>
                </div>
            </header>

            <section className="rep-section rep-section--filters">
                <h2 className="rep-section__title">Filters</h2>
                <p className="rep-section__lede">
                    Filters do not auto-reload the report. Click <strong>Apply report</strong> to fetch data.
                </p>

                <div className="rep-filters">
                    <div className="rep-filters__toolbar">
                        <div className="rep-field rep-field--range">
                            <label className="rep-field__label" htmlFor="rep-date-preset">Date range</label>
                            <select
                                id="rep-date-preset"
                                className="rep-field__select rep-field__select--single"
                                value={datePreset}
                                onChange={(e) => setDatePreset(e.target.value)}
                            >
                                {RANGE_PRESETS.map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                                <option value="custom">Custom range</option>
                            </select>
                        </div>

                        {datePreset === 'custom' && (
                            <div className="rep-field rep-field--custom-range">
                                <label className="rep-field__label" htmlFor="rep-date-from">Custom dates</label>
                                <div className="range-custom">
                                    <input
                                        type="date"
                                        id="rep-date-from"
                                        className="range-custom__input"
                                        value={dateFrom}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                    />
                                    <input
                                        type="date"
                                        className="range-custom__input"
                                        value={dateTo}
                                        onChange={(e) => setDateTo(e.target.value)}
                                    />
                                </div>
                            </div>
                        )}

                        <div className="rep-field rep-field--apply">
                            <span className="rep-field__label">&nbsp;</span>
                            <button type="button" className="range-custom__btn rep-apply-wide" onClick={handleApply}>
                                Apply report
                            </button>
                        </div>
                    </div>

                    <div className="rep-filters__grid rep-filters__grid--dims">
                        <div className="rep-field">
                            <span className="rep-field__label">Add a grouping dimension</span>
                            <select
                                className="rep-field__select rep-field__select--single"
                                value=""
                                onChange={(e) => addGroup(e.target.value)}
                            >
                                <option value="" disabled>Choose a dimension…</option>
                                {remainingGroups.map((g) => (
                                    <option key={g} value={g}>{availableGroups[g]}</option>
                                ))}
                            </select>
                        </div>
                        <div className="rep-field">
                            <span className="rep-field__label">Grouping order (drill-down goes top to bottom)</span>
                            <ul className="group-order">
                                {groupBy.map((g, i) => (
                                    <li key={g} className="group-order__item">
                                        <span>{availableGroups[g] || g}</span>
                                        <span className="group-order__actions">
                                            <button type="button" onClick={() => moveGroup(i, -1)} disabled={i === 0} aria-label="Move up">↑</button>
                                            <button type="button" onClick={() => moveGroup(i, 1)} disabled={i === groupBy.length - 1} aria-label="Move down">↓</button>
                                            <button type="button" onClick={() => removeGroup(g)} aria-label="Remove">✕</button>
                                        </span>
                                    </li>
                                ))}
                                {groupBy.length === 0 && <li className="group-order__empty">No groupings selected.</li>}
                            </ul>
                        </div>
                    </div>

                    <nav className="range-pills">
                        {RANGE_PRESETS.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                className={`range-pill range-pill--btn${datePreset === key ? ' is-active' : ''}`}
                                onClick={() => setDatePreset(key)}
                            >
                                {label}
                            </button>
                        ))}
                    </nav>
                </div>
            </section>

            <section className="rep-section">
                <div className="rep-section__head">
                    <h2 className="rep-section__title">Results</h2>
                    <p className="rep-range-line">{summaryLine}</p>
                </div>

                {activeRequests > 0 && (
                    <div className="rep-progress" aria-live="polite">
                        <div className="rep-progress__track">
                            <span className="rep-progress__bar" />
                        </div>
                        <span className="rep-progress__text">Loading…</span>
                    </div>
                )}

                <div className="rep-table-scroll">
                    <table className="rep-table">
                        <thead>
                            <tr>
                                <th className="rep-th-marker">±</th>
                                <th className="rep-th-group">
                                    <button type="button" className={`rep-sort-btn${sortBy === 'group_name' ? ' is-active' : ''}`} onClick={() => handleSort('group_name')}>
                                        Group
                                        <span className="rep-sort-ind">{sortBy === 'group_name' ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}</span>
                                    </button>
                                </th>
                                {METRIC_COLUMNS.map(([key, label]) => (
                                    <th key={key} className="rep-th-num">
                                        <button type="button" className={`rep-sort-btn${sortBy === key ? ' is-active' : ''}`} onClick={() => handleSort(key)}>
                                            {label}
                                            <span className="rep-sort-ind">{sortBy === key ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}</span>
                                        </button>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {status === 'loading' && rows.length === 0 && (
                                <tr><td className="rep-empty" colSpan={METRIC_COLUMNS.length + 2}>Loading report…</td></tr>
                            )}
                            {status === 'error' && (
                                <tr><td className="rep-empty" colSpan={METRIC_COLUMNS.length + 2}>{errorMessage}</td></tr>
                            )}
                            {status !== 'error' && status !== 'loading' && rows.length === 0 && (
                                <tr><td className="rep-empty" colSpan={METRIC_COLUMNS.length + 2}>Configure filters, then click <strong>Apply report</strong>.</td></tr>
                            )}
                            {rows.map((row, i) => (
                                <ReportRow
                                    key={`${row.group_field}:${row.group_key}`}
                                    row={row}
                                    depth={1}
                                    path={[i]}
                                    metricColumns={METRIC_COLUMNS}
                                    fieldLabels={availableGroups}
                                    onToggle={toggleRow}
                                />
                            ))}
                        </tbody>
                        {totals && (
                            <tfoot className="rep-tfoot">
                                <tr className="rep-foot">
                                    <td className="rep-td-marker rep-foot__marker">Σ</td>
                                    <td className="rep-group-cell rep-foot__label">Total</td>
                                    {METRIC_COLUMNS.map(([key]) => (
                                        <td key={key} className="rep-num">{formatCell(key, totals[key])}</td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </section>
        </div>
    );
}
