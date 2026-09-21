const DATE_TIME =
    /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?(Z|[+-]\d{2}:?\d{2})?$/;

const pad = (n: number) => String(n).padStart(2, '0');

/**
 * "2026-09-21 09:56:01" -> "21/09/2026 - 09:56"
 * "2026-09-21"          -> "21/09/2026"
 * null / ""             -> "—"
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';

    const m = DATE_TIME.exec(value.trim());
    if (!m) return value; // not a date, show as is

    const [, y, mo, d, h, mi, tz] = m;

    // Date only (e.g. sale_date)
    if (h === undefined) return `${d}/${mo}/${y}`;

    // Plain SQL datetime, no timezone: reformat the string directly (no timezone shifting)
    if (!tz) return `${d}/${mo}/${y} - ${h}:${mi}`;

    // Has a timezone (e.g. 2026-09-21T09:56:01.000000Z): convert to the browser's local time
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;

    return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} - ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

// Fields that should be treated as dates when no explicit type is given
export const isDateField = (field: string) => /(_at|_date)$/.test(field);