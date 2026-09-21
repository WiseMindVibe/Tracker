import { formatDateTime } from '@/lib/format-date';

export default function DateTime({ value }: { value: string | null | undefined }) {
    const text = formatDateTime(value);

    if (text === '—') {
        return <span className="text-slate-400 dark:text-slate-600">—</span>;
    }

    return (
        <span className="whitespace-nowrap text-sm font-medium tabular-nums text-slate-900 dark:text-slate-100">
            {text}
        </span>
    );
}