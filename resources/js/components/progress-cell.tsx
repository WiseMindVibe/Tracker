interface Props {
    row: Record<string, any>;
    column: { currentKey: string; capKey: string };
}

export default function ProgressCell({ row, column }: Props) {
    const current = Number(row[column.currentKey] ?? 0);
    const cap = Number(row[column.capKey] ?? 0);
    const pct = cap > 0 ? Math.round((current / cap) * 100) : 0;

    return (
        <span className="text-sm">
            {current.toLocaleString()} / {cap.toLocaleString()} — {pct}%
        </span>
    );
}