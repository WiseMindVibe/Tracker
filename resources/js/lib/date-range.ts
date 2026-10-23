// resources/js/lib/date-range.ts
// Date helpers + quick-option presets. All dates are local-midnight Date objects.

export type WeekStart = 0 | 1; // 0 = Sunday, 1 = Monday

export interface DateRangeValue {
    start: Date | null;
    end: Date | null;
    /** id of the quick option that produced this range (null when picked by hand) */
    preset?: string | null;
    /** state of the "Include today" tickbox (used by the "Last N days" presets) */
    includeToday?: boolean;
}

export const CUSTOM_PRESET = "custom"; // set automatically when dates are picked by hand
export const ALL_TIME_PRESET = "all-time";

/** What triggered a change: a calendar click, a quick option, or the tickbox. */
export type ChangeSource = "day" | "preset" | "toggle";
export interface ChangeMeta {
    source: ChangeSource;
}

export const EMPTY_RANGE: DateRangeValue = { start: null, end: null, preset: null };

export interface QuickOptionContext {
    today: Date;
    weekStartsOn: WeekStart;
    /** value of the "Include today" tickbox */
    includeToday: boolean;
    /** the range currently selected (the "Custom" option keeps it) */
    current: DateRangeValue;
}

export interface QuickOption {
    id: string;
    label: string;
    /** Longer name shown next to the dates, e.g. "Last 7 days". Falls back to `label`. */
    summary?: string;
    getRange: (ctx: QuickOptionContext) => { start: Date | null; end: Date | null };
}

export interface QuickOptionGroup {
    id: string;
    /** omit for a group without a heading */
    label?: string;
    /** shows a tickbox under the group; its state reaches getRange as `includeToday` */
    toggle?: { label: string };
    options: QuickOption[];
}

/* ---------- date helpers ---------- */

export const addDays = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
export const startOfMonth = (d: Date) => new Date(d.getFullYear(), d.getMonth(), 1);
export const endOfMonth = (d: Date) => new Date(d.getFullYear(), d.getMonth() + 1, 0);
export const addMonths = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth() + n, 1);
export const startOfYear = (d: Date) => new Date(d.getFullYear(), 0, 1);
export const endOfYear = (d: Date) => new Date(d.getFullYear(), 11, 31);

export function startOfWeek(d: Date, weekStartsOn: WeekStart = 1) {
    return addDays(d, -((d.getDay() - weekStartsOn + 7) % 7));
}
export const endOfWeek = (d: Date, weekStartsOn: WeekStart = 1) => addDays(startOfWeek(d, weekStartsOn), 6);

export const isSameDay = (a: Date | null, b: Date | null) =>
    !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** "Today" in the app's timezone (not the browser's), as a local-midnight Date. */
export function getToday(timezone?: string): Date {
    const ymd = new Intl.DateTimeFormat("en-CA", {
        timeZone: timezone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(new Date()); // "2026-10-15"
    const [y, m, d] = ymd.split("-").map(Number);
    return new Date(y, m - 1, d);
}

/** YYYY-MM-DD, the format to send to Laravel. */
export const toYmd = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;

export function formatRange(value: DateRangeValue): string {
    if (value.preset === ALL_TIME_PRESET) return "All time";
    const fmt = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    if (!value.start) return "Select a start date";
    if (!value.end) return `${fmt.format(value.start)} – select an end date`;
    return `${fmt.format(value.start)} – ${fmt.format(value.end)}`;
}

export function findPreset(groups: QuickOptionGroup[], id?: string | null) {
    if (!id) return null;
    for (const group of groups) {
        const option = group.options.find((o) => o.id === id);
        if (option) return { group, option };
    }
    return null;
}

/** Display name for a preset id ("Last 7 days", "Custom"), or null. */
export function getPresetLabel(groups: QuickOptionGroup[], id?: string | null): string | null {
    if (id === CUSTOM_PRESET) return "Custom";
    const found = findPreset(groups, id);
    return found ? (found.option.summary ?? found.option.label) : null;
}

/** "2026-10-15" -> local-midnight Date */
export function fromYmd(value: string): Date {
    const [y, m, d] = value.split("-").map(Number);
    return new Date(y, m - 1, d);
}

/** Query-string params for router.get(): only defined values are included. */
export function toQuery(value: DateRangeValue): Record<string, string> {
    const q: Record<string, string> = {};
    if (value.preset) q.preset = value.preset;
    if (value.start) q.start = toYmd(value.start);
    if (value.end) q.end = toYmd(value.end);
    if (value.includeToday) q.include_today = "1";
    return q;
}

/* ---------- default quick options ---------- */

const lastDays = (n: number): QuickOption => ({
    id: `last-${n}`,
    label: `${n}`,
    summary: `Last ${n} days`,
    getRange: ({ today, includeToday }) => {
        // unticked: the N days BEFORE today. ticked: the N days ending today.
        const end = includeToday ? today : addDays(today, -1);
        return { start: addDays(end, -(n - 1)), end };
    },
});

export const defaultQuickOptions: QuickOptionGroup[] = [
    {
        id: "quick",
        options: [
            { id: "today", label: "Today", getRange: ({ today }) => ({ start: today, end: today }) },
            {
                id: "yesterday",
                label: "Yesterday",
                getRange: ({ today }) => ({ start: addDays(today, -1), end: addDays(today, -1) }),
            },
            { id: "all-time", label: "All time", getRange: () => ({ start: null, end: null }) },
            {
                id: CUSTOM_PRESET,
                label: "Custom",
                getRange: ({ current }) => ({ start: current.start, end: current.end }),
            },
        ],
    },
    {
        id: "this",
        label: "This",
        options: [
            {
                id: "this-week",
                summary: "This week",
                label: "Week",
                getRange: ({ today, weekStartsOn }) => ({
                    start: startOfWeek(today, weekStartsOn),
                    end: endOfWeek(today, weekStartsOn),
                }),
            },
            {
                id: "this-month",
                summary: "This month",
                label: "Month",
                getRange: ({ today }) => ({ start: startOfMonth(today), end: endOfMonth(today) }),
            },
            {
                id: "this-year",
                summary: "This year",
                label: "Year",
                getRange: ({ today }) => ({ start: startOfYear(today), end: endOfYear(today) }),
            },
        ],
    },
    {
        id: "previous",
        label: "Previous",
        options: [
            {
                id: "previous-week",
                summary: "Previous week",
                label: "Week",
                getRange: ({ today, weekStartsOn }) => {
                    const start = addDays(startOfWeek(today, weekStartsOn), -7);
                    return { start, end: addDays(start, 6) };
                },
            },
            {
                id: "previous-month",
                summary: "Previous month",
                label: "Month",
                getRange: ({ today }) => {
                    const prev = addMonths(today, -1);
                    return { start: startOfMonth(prev), end: endOfMonth(prev) };
                },
            },
            {
                id: "previous-year",
                summary: "Previous year",
                label: "Year",
                getRange: ({ today }) => {
                    const y = today.getFullYear() - 1;
                    return { start: new Date(y, 0, 1), end: new Date(y, 11, 31) };
                },
            },
        ],
    },
    {
        id: "last",
        label: "Last (days)",
        toggle: { label: "Include today" },
        options: [3, 7, 14, 30, 90, 180, 365].map(lastDays),
    },
];