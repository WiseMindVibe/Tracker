import { formatCell } from '../format';
import type { MetricColumn, ReportRowState } from '../types';

function cellClass(key: string, value: unknown): string {
    if (key === 'spent' || key === 'rejected_conversions_sum') return 'rep-num rep-val--cost';

    if (key === 'profit' || key === 'revenue') {
        const v = Number(value || 0);
        if (v > 0) return 'rep-num rep-val--pos';
        if (v < 0) return 'rep-num rep-val--neg';
        return 'rep-num rep-val--muted';
    }

    if (key === 'roi') {
        if (value === null || value === undefined) return 'rep-num rep-val--neg';
        const v = Number(value);
        if (v < 0) return 'rep-num rep-val--neg';
        if (v <= 300) return 'rep-num rep-val--warn';
        return 'rep-num rep-val--pos';
    }

    if (key === 'cr') {
        const v = Number(value || 0);
        if (v < 0.05) return 'rep-num rep-val--neg';
        if (v < 0.25) return 'rep-num rep-val--warn';
        return 'rep-num rep-val--pos';
    }

    return 'rep-num';
}

function Marker({ roi }: { roi: number | null }) {
    const value = roi === null || roi === undefined ? null : Number(roi);
    const isPositive = value !== null && value >= 100;
    const title = value === null ? 'ROI —' : `ROI ${value.toFixed(2)}%`;

    return (
        <span className={`rep-marker ${isPositive ? 'rep-marker--good' : 'rep-marker--bad'}`} title={title}>
            {isPositive ? '+' : '-'}
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
}

export default function ReportRow({ row, depth, path, metricColumns, fieldLabels, onToggle }: ReportRowProps) {
    const indentWidth = `${1.1 + Math.max(0, depth - 1) * 1.1}rem`;

    return (
        <>
            <tr className={`rep-row rep-row--depth-${depth}${row.can_expand ? ' rep-row--parent' : ''}${row.loading ? ' rep-row--loading' : ''}`}>
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
                    <td key={key} className={cellClass(key, row[key])}>{formatCell(key, row[key] as number | string | null)}</td>
                ))}
            </tr>

            {row.expanded && Array.isArray(row.children) && row.children.length === 0 && (
                <tr className={`rep-row rep-row--depth-${depth + 1}`}>
                    <td className="rep-empty" colSpan={metricColumns.length + 2}>No nested rows for this group.</td>
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
                />
            ))}
        </>
    );
}
