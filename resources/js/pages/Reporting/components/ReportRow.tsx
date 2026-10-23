import { formatCell } from '../format';
import type { MetricColumn, ReportRowState } from '../types';

function cellClass(key: string, value: unknown, showConversionStatus: boolean): string {
    if (key === 'spent' || key === 'loss') {
        return Number(value || 0) !== 0 ? 'rep-num rep-val--neg' : 'rep-num rep-val--muted';
    }

    if (key === 'rejected_conversions_sum') return 'rep-num rep-val--cost';

    if (key === 'total_conversions') {
        if (!showConversionStatus) return 'rep-num rep-val--muted';

        return Number(value || 0) > 0 ? 'rep-num rep-val--pos' : 'rep-num rep-val--muted';
    }

    if (key === 'open_conversions' || key === 'open_conversions_sum') {
        if (!showConversionStatus) return 'rep-num rep-val--muted';

        return Number(value || 0) > 0 ? 'rep-num rep-val--warn' : 'rep-num rep-val--muted';
    }

    if (key === 'confirmed_conversions' || key === 'confirmed_conversions_sum') {
        return Number(value || 0) > 0 ? 'rep-num rep-val--info' : 'rep-num rep-val--muted';
    }

    if (key === 'paid_conversions' || key === 'paid_conversions_sum') {
        return Number(value || 0) > 0 ? 'rep-num rep-val--pos' : 'rep-num rep-val--muted';
    }

    if (key === 'rejected_conversions') {
        return Number(value || 0) > 0 ? 'rep-num rep-val--neg' : 'rep-num rep-val--muted';
    }

    if (key === 'profit' || key === 'revenue') {
        const v = Number(value || 0);
        return v !== 0 ? 'rep-num rep-val--pos' : 'rep-num rep-val--muted';
    }

    if (key === 'roi') {
        if (value === null || value === undefined || Number(value) === 0) return 'rep-num rep-val--muted';
        const v = Number(value);
        if (v < 1) return 'rep-num rep-val--neg';
        if (v <= 3) return 'rep-num rep-val--warn';
        return 'rep-num rep-val--pos';
    }

    if (key === 'cr') {
        const v = Number(value || 0);
        if (v === 0) return 'rep-num rep-val--muted';
        if (v < 0.05) return 'rep-num rep-val--neg';
        if (v > 0.2) return 'rep-num rep-val--pos';
        return 'rep-num rep-val--warn';
    }

    return 'rep-num';
}

function Marker({ roi }: { roi: number | null }) {
    const value = roi === null || roi === undefined ? null : Number(roi);
    const title = value === null ? 'ROI —' : `ROI ${value.toFixed(2)}X`;
    const markerClass = value === null
        ? 'rep-marker--muted'
        : value < 1
            ? 'rep-marker--bad'
            : value > 3
                ? 'rep-marker--good'
                : 'rep-marker--warn';

    return (
        <span className={`rep-marker ${markerClass}`} title={title}>
            {value !== null && value > 3 ? '+' : value !== null && value >= 1 ? '~' : '-'}
        </span>
    );
}

interface ReportRowProps {
    row: ReportRowState;
    depth: number;
    path: number[];
    metricColumns: MetricColumn[];
    fieldLabels: Record<string, string>;
    onToggle: (path: number[], row: ReportRowState, depth: number) => void;
    showConversionStatus: boolean;
}

export default function ReportRow({ row, depth, path, metricColumns, fieldLabels, onToggle, showConversionStatus }: ReportRowProps) {
    const indentWidth = `${1.1 + Math.max(0, depth - 1) * 1.1}rem`;

    return (
        <>
            <tr className={`rep-row rep-row--depth-${depth}${depth > 1 ? ' rep-row--nested' : ''}${row.can_expand ? ' rep-row--parent' : ''}${row.expanded ? ' rep-row--expanded' : ''}${row.loading ? ' rep-row--loading' : ''}`}>
                <td className="rep-td-marker">
                    <Marker roi={row.roi} />
                </td>
                <td className="rep-group-cell">
                    <span className="rep-indent" style={{ width: indentWidth }} />
                    {row.can_expand ? (
                        <button
                            type="button"
                            className="rep-toggle"
                            disabled={row.loading}
                            aria-expanded={row.expanded ? 'true' : 'false'}
                            aria-label="Expand nested groups"
                            onClick={() => onToggle(path, row, depth)}
                        >
                            {row.loading ? '…' : row.expanded ? '▾' : '▸'}
                        </button>
                    ) : (
                        <span className="rep-toggle-spacer" />
                    )}
                    <span className="rep-group-name">
                        <span className="rep-dim-chip">{fieldLabels[row.group_field] || row.group_field || 'Group'}</span>
                        {' '}{String(row.group_name || '(none)')}
                    </span>
                </td>
                {metricColumns.map(([key]) => (
                    <td key={key} className={`${cellClass(key, row[key], showConversionStatus)}${['clicks', 'total_conversions', 'profit', 'roi'].includes(key) ? '' : ' rep-mobile-secondary'}`}>
                        {formatCell(key, row[key] as number | string | null)}
                    </td>
                ))}
                <td className="rep-column-toggle-cell" aria-hidden="true" />
            </tr>

            {row.expanded && Array.isArray(row.children) && row.children.length === 0 && (
                <tr className={`rep-row rep-row--depth-${depth + 1}`}>
                    <td className="rep-empty" colSpan={metricColumns.length + 3}>No nested rows for this group.</td>
                </tr>
            )}

            {row.expanded && Array.isArray(row.children) && row.children.map((child, i) => (
                <ReportRow
                    key={`${child.group_field}:${child.group_key}`}
                    row={child}
                    depth={depth + 1}
                    path={[...path, i]}
                    metricColumns={metricColumns}
                    fieldLabels={fieldLabels}
                    onToggle={onToggle}
                    showConversionStatus={showConversionStatus}
                />
            ))}
        </>
    );
}
