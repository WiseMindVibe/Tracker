import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent, KeyboardEvent } from 'react';
import DateRangePicker from '@/components/date-range/date-range-picker';
import { ALL_TIME_PRESET, fromYmd, toYmd, type DateRangeValue } from '@/lib/date-range';
import ReportRow from './components/ReportRow';
import { formatCell } from './format';
import './reporting.css';
import { postJson } from './request';
import type { AppliedContext, MetricColumn, ReportApiResponse, ReportRowState, ReportTotals, ReportingIndexProps } from './types';

const BASE_METRIC_COLUMNS: MetricColumn[] = [
    ['clicks', 'Clicks'],
    ['total_conversions', 'Total conv.'],
    ['revenue', 'Revenue'],
    ['spent', 'Spent'],
    ['profit', 'Profit'],
    ['cr', 'CR'],
    ['roi', 'ROI'],
    ['avg_payout', 'Avg payout'],
    ['loss', 'Loss'],
];

const STATUS_METRIC_COLUMNS: MetricColumn[] = [
    ['open_conversions', 'Open conv.'],
    ['open_conversions_sum', 'Open commission'],
    ['confirmed_conversions', 'Confirmed #'],
    ['confirmed_conversions_sum', 'Confirmed commission'],
    ['rejected_conversions', 'Rejected #'],
    ['rejected_conversions_sum', 'Rejected commission'],
    ['paid_conversions', 'Paid #'],
    ['paid_conversions_sum', 'Paid commission'],
];

function fieldToken(row: ReportRowState): string {
    return JSON.stringify(row.parent_filters || {});
}

function sortReportRows(rows: ReportRowState[], sortBy: string, sortDir: 'asc' | 'desc'): ReportRowState[] {
    const direction = sortDir === 'desc' ? -1 : 1;
    const sorted: ReportRowState[] = rows.map((row) => ({
        ...row,
        children: row.children ? sortReportRows(row.children, sortBy, sortDir) : null,
    }));

    sorted.sort((left, right) => {
        const leftValue = left[sortBy];
        const rightValue = right[sortBy];
        const nameComparison = left.group_name.localeCompare(right.group_name, undefined, { sensitivity: 'base' });

        if (sortBy === 'group_name') {
            return nameComparison * direction;
        }

        if (leftValue === null || leftValue === undefined) {
            return rightValue === null || rightValue === undefined ? nameComparison : 1;
        }

        if (rightValue === null || rightValue === undefined) {
            return -1;
        }

        const valueComparison = (Number(leftValue) - Number(rightValue)) * direction;
        if (valueComparison !== 0) {
            return valueComparison;
        }

        return Number(right.clicks) - Number(left.clicks) || nameComparison;
    });

    return sorted;
}

type Status = 'idle' | 'loading' | 'error';
type GroupDropTarget = { group: string; position: 'before' | 'after' } | null;

export default function ReportingIndex({
    defaultDatePreset,
    defaultDateFrom,
    defaultDateTo,
    defaultGroupBy,
    availableGroups,
    apiEndpoint,
    timezone,
}: ReportingIndexProps) {
    const [dateRange, setDateRange] = useState<DateRangeValue>(() => ({
        start: fromYmd(defaultDateFrom),
        end: fromYmd(defaultDateTo),
        preset: defaultDatePreset === 'last7' ? 'last-7' : defaultDatePreset,
        includeToday: true,
    }));
    const [groupBy, setGroupBy] = useState<string[]>(defaultGroupBy);
    const [sortBy, setSortBy] = useState('roi');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
    const [showConversionStatus, setShowConversionStatus] = useState(false);
    const [showAllMobileMetrics, setShowAllMobileMetrics] = useState(false);

    const [appliedContext, setAppliedContext] = useState<AppliedContext | null>(null);
    const [rows, setRows] = useState<ReportRowState[]>([]);
    const [totals, setTotals] = useState<ReportTotals | null>(null);
    const [status, setStatus] = useState<Status>('loading');
    const [errorMessage, setErrorMessage] = useState('');
    const [activeRequests, setActiveRequests] = useState(0);

    const sliceCache = useRef(new Map<string, ReportRowState[]>());
    const requestSerial = useRef(0);
    const activeSort = useRef({ sortBy, sortDir });
    activeSort.current = { sortBy, sortDir };
    const draggedGroup = useRef<string | null>(null);
    const groupDropHandled = useRef(false);
    const [draggingGroup, setDraggingGroup] = useState<string | null>(null);
    const [groupDropTarget, setGroupDropTarget] = useState<GroupDropTarget>(null);
    const [isRemovalDropTarget, setIsRemovalDropTarget] = useState(false);

    const remainingGroups = useMemo(
        () => Object.keys(availableGroups).filter((g) => !groupBy.includes(g)),
        [availableGroups, groupBy]
    );
    const metricColumns = useMemo(
        () => showConversionStatus ? [...BASE_METRIC_COLUMNS, ...STATUS_METRIC_COLUMNS] : BASE_METRIC_COLUMNS,
        [showConversionStatus]
    );

    const moveGroup = (index: number, delta: number) => {
        setGroupBy((prev) => {
            const next = [...prev];
            const target = index + delta;
            if (target < 0 || target >= next.length) {
                return prev;
            }

            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const addGroup = (value: string) => {
        if (!value || groupBy.includes(value)) {
            return;
        }

        setGroupBy((prev) => [...prev, value]);
    };

    const removeGroup = (value: string) => {
        setGroupBy((prev) => prev.filter((g) => g !== value));
    };

    const reorderGroup = (dragged: string, target: string, position: 'before' | 'after') => {
        setGroupBy((prev) => {
            const targetIndex = prev.indexOf(target);
            if (!prev.includes(dragged) || targetIndex < 0 || dragged === target) {
                return prev;
            }

            const next = prev.filter((group) => group !== dragged);
            const insertIndex = next.indexOf(target) + (position === 'after' ? 1 : 0);
            next.splice(insertIndex, 0, dragged);

            return next;
        });
    };

    const groupDropPosition = (event: DragEvent<HTMLLIElement>): 'before' | 'after' => {
        const bounds = event.currentTarget.getBoundingClientRect();

        return event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';
    };

    const handleGroupDragOver = (event: DragEvent<HTMLLIElement>, group: string) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        setIsRemovalDropTarget(false);
        if (draggedGroup.current === group) {
            setGroupDropTarget(null);
            return;
        }

        const position = groupDropPosition(event);
        setGroupDropTarget((current) => (
            current?.group === group && current.position === position ? current : { group, position }
        ));
    };

    const handleGroupKeyDown = (event: KeyboardEvent<HTMLLIElement>, index: number) => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            moveGroup(index, event.key === 'ArrowUp' ? -1 : 1);
        }
    };

    const handleGroupDrop = (event: DragEvent<HTMLLIElement>, target: string) => {
        event.preventDefault();
        const dragged = draggedGroup.current || event.dataTransfer.getData('text/plain');
        if (dragged) {
            reorderGroup(dragged, target, groupDropPosition(event));
        }

        groupDropHandled.current = true;
        draggedGroup.current = null;
        setDraggingGroup(null);
        setGroupDropTarget(null);
        setIsRemovalDropTarget(false);
    };

    const buildContext = useCallback((): AppliedContext => {
        if (groupBy.length === 0) {
            throw new Error('Select at least one grouping dimension.');
        }

        if (dateRange.preset === ALL_TIME_PRESET) {
            return { date_preset: 'all_time', date_from: '', date_to: '', group_by: groupBy };
        }

        if (!dateRange.start || !dateRange.end) {
            throw new Error('Select both a start and end date.');
        }

        return {
            date_preset: 'custom',
            date_from: toYmd(dateRange.start),
            date_to: toYmd(dateRange.end),
            group_by: groupBy,
        };
    }, [dateRange, groupBy]);

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

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            void loadTopLevel({
                date_preset: defaultDatePreset,
                date_from: '',
                date_to: '',
                group_by: defaultGroupBy,
            }, 'roi', 'desc');
        }, 0);

        return () => window.clearTimeout(timeoutId);
    }, [defaultDatePreset, defaultGroupBy, loadTopLevel]);

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

    const handleSort = (key: string) => {
        const nextDir: 'asc' | 'desc' = sortBy === key ? (sortDir === 'asc' ? 'desc' : 'asc') : key === 'group_name' ? 'asc' : 'desc';
        setSortBy(key);
        setSortDir(nextDir);
        setRows((currentRows) => sortReportRows(currentRows, key, nextDir));
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

    const updateRowByFilters = useCallback((filters: Record<string, number | string>, updater: (row: ReportRowState) => ReportRowState) => {
        const targetToken = JSON.stringify(filters);

        setRows((prevRows) => {
            const updateList = (items: ReportRowState[]): ReportRowState[] => items.map((row) => {
                if (fieldToken(row) === targetToken) {
                    return updater(row);
                }

                if (row.children) {
                    return { ...row, children: updateList(row.children) };
                }

                return row;
            });

            return updateList(prevRows);
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
            const cacheKey = `${level}:${fieldToken(row)}`;

            const cached = sliceCache.current.get(cacheKey);
            if (cached) {
                updateRowAtPath(path, (r) => ({ ...r, expanded: true, children: sortReportRows(cached, sortBy, sortDir) }));
                return;
            }

            updateRowAtPath(path, (r) => ({ ...r, loading: true }));
            try {
                const data = await requestLevel(appliedContext, level, row.parent_filters || {}, false, sortBy, sortDir);
                const children = sortReportRows(
                    (data.rows || []).map((r) => ({ ...r, expanded: false, loading: false, children: null })),
                    activeSort.current.sortBy,
                    activeSort.current.sortDir
                );
                sliceCache.current.set(cacheKey, children);
                updateRowByFilters(row.parent_filters || {}, (r) => ({ ...r, expanded: true, loading: false, children }));
            } catch (err) {
                window.alert(err instanceof Error ? err.message : 'Could not load nested rows.');
                updateRowByFilters(row.parent_filters || {}, (r) => ({ ...r, loading: false }));
            }
        },
        [appliedContext, requestLevel, sortBy, sortDir, updateRowAtPath, updateRowByFilters]
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
                        Your last 7 days load automatically. Expand rows to fetch deeper grouping levels.
                    </p>
                </div>
            </header>

            <section className="rep-section rep-section--filters">
                <h2 className="rep-section__title">Filters</h2>
                <p className="rep-section__lede">
                    Choose a date range and grouping dimensions, then click <strong>Apply report</strong> to fetch data.
                </p>

                <div className="rep-filters">
                    <div className="rep-filters__toolbar">
                        <div className="rep-field rep-field--range">
                            <span className="rep-field__label">Date range</span>
                            <DateRangePicker
                                value={dateRange}
                                onChange={setDateRange}
                                timezone={timezone}
                                align="left"
                            />
                        </div>

                        <div className="rep-field rep-field--apply">
                            <span className="rep-field__label">&nbsp;</span>
                            <button type="button" className="range-custom__btn rep-apply-wide" onClick={handleApply}>
                                Apply report
                            </button>
                        </div>
                    </div>

                    <div className="rep-filters__grid rep-filters__grid--dims">
                        <div className="rep-field">
                            <span className="rep-field__label">Available dimensions</span>
                            <div
                                className={`group-options${isRemovalDropTarget ? ' group-options--drop-target' : ''}`}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    event.dataTransfer.dropEffect = 'move';
                                    setIsRemovalDropTarget(true);
                                }}
                                onDragLeave={(event) => {
                                    if (!(event.relatedTarget instanceof Node) || !event.currentTarget.contains(event.relatedTarget)) {
                                        setIsRemovalDropTarget(false);
                                    }
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    const dragged = draggedGroup.current || event.dataTransfer.getData('text/plain');

                                    if (dragged) {
                                        removeGroup(dragged);
                                    }

                                    groupDropHandled.current = true;
                                    draggedGroup.current = null;
                                    setDraggingGroup(null);
                                    setGroupDropTarget(null);
                                    setIsRemovalDropTarget(false);
                                }}
                                aria-label="Available grouping dimensions. Click to add or drop a group here to remove it."
                            >
                                {remainingGroups.map((g) => (
                                    <button key={g} type="button" className="group-option" onClick={() => addGroup(g)}>
                                        <span aria-hidden="true">+</span> {availableGroups[g]}
                                    </button>
                                ))}
                                {remainingGroups.length === 0 && <span className="group-options__empty">All dimensions are in the grouping order.</span>}
                            </div>
                        </div>
                        <div className="rep-field">
                            <span className="rep-field__label">Grouping order <span className="group-order__hint">Drag to reorder · drag out to remove</span></span>
                            {draggingGroup && (
                                <p className="group-order__status" aria-live="polite">
                                    Moving <strong>{availableGroups[draggingGroup] || draggingGroup}</strong>
                                    {groupDropTarget
                                        ? ` — drop ${groupDropTarget.position} ${availableGroups[groupDropTarget.group] || groupDropTarget.group}`
                                        : isRemovalDropTarget ? ' — drop in Available dimensions to remove' : ' — choose a highlighted position'}
                                </p>
                            )}
                            <ul className="group-order" aria-label="Grouping order">
                                {groupBy.map((g, i) => (
                                    <li
                                        key={g}
                                        className={[
                                            'group-order__item',
                                            draggingGroup === g ? 'group-order__item--dragging' : '',
                                            groupDropTarget?.group === g ? `group-order__item--drop-${groupDropTarget.position}` : '',
                                        ].filter(Boolean).join(' ')}
                                        draggable
                                        tabIndex={0}
                                        onDragStart={(event) => {
                                            draggedGroup.current = g;
                                            groupDropHandled.current = false;
                                            setDraggingGroup(g);
                                            event.dataTransfer.effectAllowed = 'move';
                                            event.dataTransfer.setData('text/plain', g);
                                        }}
                                        onDragOver={(event) => handleGroupDragOver(event, g)}
                                        onDrop={(event) => handleGroupDrop(event, g)}
                                        onDragEnd={(event) => {
                                            const pointerTarget = document.elementFromPoint(event.clientX, event.clientY);
                                            const droppedInsideOrder = Boolean(pointerTarget?.closest('.group-order'));

                                            if (!groupDropHandled.current && !droppedInsideOrder) {
                                                removeGroup(g);
                                            }

                                            groupDropHandled.current = false;
                                            draggedGroup.current = null;
                                            setDraggingGroup(null);
                                            setGroupDropTarget(null);
                                            setIsRemovalDropTarget(false);
                                        }}
                                        onKeyDown={(event) => handleGroupKeyDown(event, i)}
                                        aria-label={`${availableGroups[g] || g}, position ${i + 1} of ${groupBy.length}. Use arrow keys to reorder.`}
                                    >
                                        <button
                                            type="button"
                                            className="group-order__remove"
                                            draggable={false}
                                            aria-label={`Remove ${availableGroups[g] || g} from grouping order`}
                                            title={`Remove ${availableGroups[g] || g}`}
                                            onClick={() => removeGroup(g)}
                                            onDragStart={(event) => {
                                                event.preventDefault();
                                                event.stopPropagation();
                                            }}
                                        >
                                            ×
                                        </button>
                                        <span className="group-order__grip" aria-hidden="true">⠿</span>
                                        <span className="group-order__name">{i + 1}. {availableGroups[g] || g}</span>
                                    </li>
                                ))}
                                {groupBy.length === 0 && <li className="group-order__empty">Click an available dimension to add it.</li>}
                            </ul>
                        </div>
                    </div>

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

                <div className="rep-results-toolbar">
                    <label className="rep-column-toggle rep-mobile-metrics-toggle">
                        <input
                            type="checkbox"
                            checked={showAllMobileMetrics}
                            onChange={(event) => setShowAllMobileMetrics(event.target.checked)}
                        />
                        <span>Show all metrics</span>
                    </label>
                    <label className="rep-column-toggle rep-mobile-status-toggle">
                        <input
                            type="checkbox"
                            checked={showConversionStatus}
                            onChange={(event) => setShowConversionStatus(event.target.checked)}
                        />
                        <span>Conversion status</span>
                    </label>
                    <span className="rep-scroll-hint">Swipe to see more columns</span>
                </div>

                <div className="rep-table-scroll rep-results" data-show-all-metrics={showAllMobileMetrics}>
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
                                {metricColumns.map(([key, label]) => (
                                    <th key={key} className={`rep-th-num${['clicks', 'total_conversions', 'profit', 'roi'].includes(key) ? '' : ' rep-mobile-secondary'}`}>
                                        <button type="button" className={`rep-sort-btn${sortBy === key ? ' is-active' : ''}`} onClick={() => handleSort(key)}>
                                            {label}
                                            <span className="rep-sort-ind">{sortBy === key ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}</span>
                                        </button>
                                    </th>
                                ))}
                                <th className="rep-th-toggle">
                                    <label className="rep-column-toggle">
                                        <input
                                            type="checkbox"
                                            checked={showConversionStatus}
                                            onChange={(event) => setShowConversionStatus(event.target.checked)}
                                        />
                                        <span>Conversions by status</span>
                                    </label>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {status === 'loading' && rows.length === 0 && (
                                <tr><td className="rep-empty" colSpan={metricColumns.length + 3}>Loading report…</td></tr>
                            )}
                            {status === 'error' && (
                                <tr><td className="rep-empty" colSpan={metricColumns.length + 3}>{errorMessage}</td></tr>
                            )}
                            {status !== 'error' && status !== 'loading' && rows.length === 0 && (
                                <tr><td className="rep-empty" colSpan={metricColumns.length + 3}>No report rows for this date range.</td></tr>
                            )}
                            {rows.map((row, i) => (
                                <ReportRow
                                    key={`${row.group_field}:${row.group_key}`}
                                    row={row}
                                    depth={1}
                                    path={[i]}
                                    metricColumns={metricColumns}
                                    fieldLabels={availableGroups}
                                    onToggle={toggleRow}
                                    showConversionStatus={showConversionStatus}
                                />
                            ))}
                        </tbody>
                        {totals && (
                            <tfoot className="rep-tfoot">
                                <tr className="rep-foot">
                                    <td className="rep-td-marker rep-foot__marker">Σ</td>
                                    <td className="rep-group-cell rep-foot__label">Total</td>
                                    {metricColumns.map(([key]) => (
                                        <td key={key} className={`rep-num${['clicks', 'total_conversions', 'profit', 'roi'].includes(key) ? '' : ' rep-mobile-secondary'}`}>{formatCell(key, totals[key])}</td>
                                    ))}
                                    <td className="rep-column-toggle-cell" aria-hidden="true" />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>

            </section>
        </div>
    );
}
