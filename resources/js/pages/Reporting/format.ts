const MONEY_KEYS = new Set([
    'open_conversions_sum',
    'confirmed_conversions_sum',
    'rejected_conversions_sum',
    'paid_conversions_sum',
    'delayed_conversions_sum',
    'revenue',
    'spent',
    'profit',
    'avg_payout',
]);

const PERCENT_KEYS = new Set(['cr', 'roi']);

const INT_KEYS = new Set([
    'clicks',
    'total_conversions',
    'open_conversions',
    'confirmed_conversions',
    'rejected_conversions',
    'paid_conversions',
    'delayed_conversions',
]);

export function formatCell(key: string, value: number | string | null | undefined): string {
    if (value === null || value === undefined) return '—';
    if (MONEY_KEYS.has(key)) return `$${Number(value).toFixed(2)}`;
    if (PERCENT_KEYS.has(key)) return `${Number(value).toFixed(2)}%`;
    if (INT_KEYS.has(key)) return Number(value).toLocaleString();
    return String(value);
}
