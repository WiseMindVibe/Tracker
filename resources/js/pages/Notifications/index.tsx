import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';

import PaginationBar from '@/components/pagination-bar';
import SearchInput from '@/components/search-input';

import DateTime from '@/components/date-time';
import { isDateField } from '@/lib/format-date';

type PaginationLink = { url: string | null; label: string; active: boolean };

type NotificationRow = {
    id: number;
    click_id: string | null;
    click_pk: number | null;
    offer: string | null;
    campaign: string | null;
    traffic_campaign_id: string | null;
    affiliate: string | null;
    commission_id: string | null;
    status: string | null;
    commission: string | null;
    final_commission: number | null;
    accumulated_commission: number | null;
    loss: number | null;
    click_created_at: string | null;
    click_commission: number | null;
    click_loss: number | null;
    currency: string | null;
    event_type: string | null;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
};

type ConversionEvent = {
    id: number;
    commission_id: string | null;
    commission: string | null;
    currency: string | null;
    status: string | null;
    event_id: string | null;
    event_type: string | null;
    advertiser_id: string | null;
    advertiser_name: string | null;
    commission_type: string | null;
    payout_id: string | null;
    country_code: string | null;
    site_id: string | null;
    sale_date: string | null;
    modified_date: string | null;
    advertiser_sale_amount: string | null;
    created_at: string | null;
};

type CommissionGroup = {
    commission_id: string | null;
    latest_status: string | null;
    snapshot: {
        value: number;
        accumulated: number;
        loss: number;
        events_count: number;
    } | null;
    events: ConversionEvent[];
};

type Props = {
    notifications: {
        data: NotificationRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    unreadCount: number;
    search?: string | null;
    perPage: number;
};

type Theme = 'light' | 'dark';

const HISTORY_COLUMNS: { key: keyof ConversionEvent; label: string }[] = [
    { key: 'id', label: 'ID' },
    { key: 'event_type', label: 'Event type' },
    { key: 'currency', label: 'Currency' },
    { key: 'commission', label: 'Commission' },
    { key: 'status', label: 'Status' },
    { key: 'commission_id', label: 'Commission ID' },
    { key: 'event_id', label: 'Event ID' },
    { key: 'advertiser_id', label: 'Advertiser ID' },
    { key: 'modified_date', label: 'Modified date' },
    { key: 'created_at', label: 'Created at' },
    { key: 'sale_date', label: 'Sale date' },
    { key: 'advertiser_sale_amount', label: 'Advertiser sale amount' },
    { key: 'commission_type', label: 'Commission type' },
    { key: 'advertiser_name', label: 'Advertiser name' },
    { key: 'country_code', label: 'Country' },
    { key: 'payout_id', label: 'Payout ID' },
    { key: 'site_id', label: 'Site ID' },
];

function useTheme(): [Theme, () => void] {
    const [theme, setTheme] = useState<Theme>(() => {
        if (typeof window === 'undefined') return 'light';
        const stored = window.localStorage.getItem('theme');
        if (stored === 'light' || stored === 'dark') return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    });

    useEffect(() => {
        const root = document.documentElement;
        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        window.localStorage.setItem('theme', theme);
    }, [theme]);

    const toggle = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'));

    return [theme, toggle];
}

const STATUS_TONES = {
    open: 'bg-yellow-100 text-yellow-800 ring-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-300 dark:ring-yellow-700',
    confirmed: 'bg-blue-100 text-blue-800 ring-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-700',
    paid: 'bg-green-100 text-green-800 ring-green-300 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-700',
    rejected: 'bg-red-100 text-red-800 ring-red-300 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-700',
    neutral: 'bg-slate-100 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
} as const;

function statusTone(status: string | null | undefined): string {
    const s = (status ?? '').toLowerCase();

    if (/open|pending|new/.test(s)) return STATUS_TONES.open;
    if (/reject|declin|cancel|void/.test(s)) return STATUS_TONES.rejected;
    if (/paid/.test(s)) return STATUS_TONES.paid;
    if (/confirm|approv/.test(s)) return STATUS_TONES.confirmed;

    return STATUS_TONES.neutral;
}

// Positive = lost value (red), negative = gained value (green), zero = muted.
function lossTone(loss: number | null | undefined): string {
    const n = Number(loss ?? 0);

    if (n > 0) return 'text-rose-700 dark:text-rose-300';
    if (n < 0) return 'text-emerald-700 dark:text-emerald-300';

    return 'text-slate-500 dark:text-slate-400';
}

function StatusBadge({ status }: { status: string | null }) {
    if (!status) {
        return <span className="text-slate-400 dark:text-slate-600">—</span>;
    }

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${statusTone(status)}`}>
            {status}
        </span>
    );
}

function ReadBadge({ isRead }: { isRead: boolean }) {
    const tone = isRead
    ? 'bg-slate-100 text-slate-500 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-400 dark:ring-slate-400/20'
    : 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20';

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${tone}`}>
            {isRead ? 'Read' : 'Unread'}
        </span>
    );
}

function ThemeToggle({ theme, onToggle }: { theme: Theme; onToggle: () => void }) {
    return (
        <button
            type="button"
            onClick={onToggle}
            aria-label="Toggle color theme"
            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        >
            {theme === 'dark' ? (
                <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.95 2.05a1 1 0 010 1.414l-.707.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 9a1 1 0 110 2h-1a1 1 0 110-2h1zm-3.05 4.95a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.95-1.05a1 1 0 010-1.414l.707-.707A1 1 0 117.17 14.24l-.707.707a1 1 0 01-1.414 0zM4 11a1 1 0 110-2h1a1 1 0 110 2H4zm1.757-6.95a1 1 0 011.414 0l.707.707A1 1 0 116.464 6.17l-.707-.707a1 1 0 010-1.414zM10 6a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
            ) : (
                <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
            )}
        </button>
    );
}

export default function Index({ notifications, unreadCount, search, perPage }: Props) {
    const [theme, toggleTheme] = useTheme();
    const [activeClick, setActiveClick] = useState<{ pk: number; label: string | null } | null>(null);
    const [groups, setGroups] = useState<CommissionGroup[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [togglingId, setTogglingId] = useState<number | null>(null);
    const [markingAll, setMarkingAll] = useState(false);

    const PER_PAGE_OPTIONS = [15, 50, 100, 250] as const;
    const changePerPage = (value: number) => {
    router.get(
        window.location.pathname,
        { search: search || undefined, per_page: value },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};


    const openHistory = async (pk: number, label: string | null) => {
    setActiveClick({ pk, label });
    setGroups([]);
    setError(null);
    setLoading(true);
    try {
        const response = await fetch(`/conversions/${pk}/history`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`History request failed with status ${response.status}`);
        }

        const data: { data: CommissionGroup[] } = await response.json();
        setGroups(data.data ?? []);
    } catch (e) {
        setError(e instanceof Error ? e.message : 'Something went wrong loading history.');
    } finally {
        setLoading(false);
    }
};

const closeHistory = () => {
    setActiveClick(null);
    setGroups([]);
    setError(null);
};

    useEffect(() => {
        if (!activeClick) return;
        const onKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closeHistory();
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [activeClick]);

    const toggleRead = (id: number) => {
        setTogglingId(id);
        router.patch(
            `/notifications/${id}/toggle-read`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            },
        );
    };

    const markAllRead = () => {
        setMarkingAll(true);
        router.patch(
            '/notifications/mark-all-read',
            {},
            {
                preserveScroll: true,
                onFinish: () => setMarkingAll(false),
            },
        );
    };

    return (
        <>
            <Head title="Notifications" />

            <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
                <div className="mx-auto max-w-[1800px] px-6 py-10">
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">Notifications</h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Events flagged as notification-worthy — status changes or meaningful commission moves.
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={markAllRead}
                                disabled={markingAll || unreadCount === 0}
                                className="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                            >
                                {markingAll ? 'Marking…' : `Mark all as read${unreadCount ? ` (${unreadCount})` : ''}`}
                            </button>
                            <ThemeToggle theme={theme} onToggle={toggleTheme} />
                        </div>
                    </div>
<div className="mb-4 flex items-center justify-between gap-4">
    <div className="flex-1">
        <SearchInput initialValue={search ?? ''} placeholder="Search by click ID, commission ID, or offer name..." />
    </div>

    <label className="flex shrink-0 items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
        Rows per page
        <select
            value={perPage}
            onChange={(e) => changePerPage(Number(e.target.value))}
            className="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
        >
            {PER_PAGE_OPTIONS.map((n) => (
                <option key={n} value={n}>
                    {n}
                </option>
            ))}
        </select>
    </label>
</div>

<PaginationBar links={notifications.links} from={notifications.from} to={notifications.to} total={notifications.total} />
                    
                    <div className="overflow-hidden mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table className="w-max min-w-full table-auto text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                    <th className="px-4 py-3">Offer</th>
                                    <th className="px-4 py-3">Campaign</th>
                                    <th className="px-4 py-3">Commission</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Click ID</th>
                                    <th className="px-4 py-3">Commission ID</th>
                                    <th className="px-4 py-3">Final</th>
                                    <th className="px-4 py-3">Click total</th>
                                    <th className="px-4 py-3">Loss</th>
                                    <th className="px-4 py-3">Click Created At</th>
                                    <th className="px-4 py-3">Read</th>
                                    <th className="px-4 py-3 text-right">History</th>
                                    <th className="px-4 py-3 text-right">Action</th>
                                    <th className="px-4 py-3">Affiliate</th>
                                    <th className="px-4 py-3">Event type</th>
                                    <th className="px-4 py-3">Read at</th>
                                    <th className="px-4 py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                {notifications.data.length === 0 && (
                                    <tr>
                                        <td colSpan={17} className="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-600">
                                            No notifications yet.
                                        </td>
                                    </tr>
                                )}

                                {notifications.data.map((row) => (
                                    <tr
                                        key={row.id}
className={`border-b border-slate-200 transition-colors last:border-0
    dark:border-slate-700/60 ${
    row.is_read
        ? 'even:bg-slate-100/50 hover:bg-sky-100 dark:even:bg-slate-800/50 dark:hover:bg-sky-900/40'
        : 'bg-orange-100 hover:bg-orange-200 dark:bg-orange-400/20 dark:hover:bg-orange-400/30'
}`}
                                    >
                                        <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300 max-w-[220px] truncate">
                                            {row.offer ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300 max-w-[220px] truncate">
                                            {row.campaign ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums max-w-[220px] truncate">
                                            {row.commission ?? '—'}
                                            {row.currency ? (
                                                <span className="ml-1 text-xs text-slate-400 dark:text-slate-600">{row.currency}</span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={row.status} />
                                        </td>
                                        <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300 max-w-[220px] truncate">
                                            {row.click_id ?? '—'}
                                        </td>
                                       <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300 max-w-[220px] truncate">
                                            {row.commission_id ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {row.final_commission !== null ? Number(row.final_commission).toFixed(2) : '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {row.click_commission !== null ? Number(row.click_commission).toFixed(2) : '—'}
                                        </td>
                                        <td className={`px-4 py-3 tabular-nums ${lossTone(row.loss)}`}>
                                            {row.loss !== null ? Number(row.loss).toFixed(2) : '0.00'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                            <DateTime value={(row.click_created_at) ?? '—'}/>
                                        </td>
                                        <td className="px-4 py-3 max-w-[220px] truncate">
                                            <ReadBadge isRead={row.is_read} />
                                        </td>
                                        <td className="px-4 py-3 text-right max-w-[220px] truncate">
                                            <button
                                                type="button"
                                                className="text-sm font-medium text-indigo-600 hover:text-indigo-700 disabled:cursor-not-allowed disabled:text-slate-300 dark:text-indigo-400 dark:hover:text-indigo-300 dark:disabled:text-slate-700"
                                                onClick={() => {
                                                    if (row.click_pk !== null) {
                                                        openHistory(row.click_pk, row.click_id);
                                                    }
                                                }}
                                                disabled={row.click_pk === null}
                                            >
                                                View
                                            </button>
                                        </td>
                                       <td className="px-4 py-3 text-right max-w-[220px] truncate">
                                            <button
                                                type="button"
                                                onClick={() => toggleRead(row.id)}
                                                disabled={togglingId === row.id}
                                                className={`inline-flex items-center rounded-full border px-3 py-0.5 text-xs font-medium shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                                                    row.is_read
                                                        ? 'text-sm font-medium text-slate-500 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-400 dark:hover:text-slate-200"'
                                                        : 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:text-indigo-800 dark:border-blue-500/40 dark:bg-blue-400/10 dark:text-indigo-400 dark:hover:text-indigo-300'
                                                }`}                                            >
                                                {togglingId === row.id ? '…' : row.is_read ? 'Mark unread' : 'Mark read'}
                                            </button>
                                        </td>
                                       <td className="px-4 py-3 text-slate-600 dark:text-slate-400 max-w-[220px] truncate">
                                            {row.affiliate ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600 dark:text-slate-400 max-w-[220px] truncate">
                                            {row.event_type ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <DateTime value={row.created_at} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <DateTime value={row.updated_at} />
                                        </td>

                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

<PaginationBar links={notifications.links} from={notifications.from} to={notifications.to} total={notifications.total} />
                </div>
            </div>

            {activeClick && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                    onClick={closeHistory}
                >
                    <div
                        className="flex max-h-[85vh] w-[95vw] max-w-[1600px] flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                            <div>
                                <h2 className="text-sm font-semibold">History</h2>
                                <p className="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                    {activeClick.label ?? '—'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeHistory}
                                aria-label="Close"
                                className="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        </div>

                        <div className="overflow-auto">
    {loading && (
        <div className="px-6 py-10 text-center text-sm text-slate-400 dark:text-slate-600">
            Loading history…
        </div>
    )}

    {!loading && error && (
        <div className="px-6 py-10 text-center text-sm text-rose-600 dark:text-rose-400">
            {error}
        </div>
    )}

    {!loading && !error && groups.length === 0 && (
        <div className="px-6 py-10 text-center text-sm text-slate-400 dark:text-slate-600">
            No events recorded for this click.
        </div>
    )}

    {!loading && !error && groups.length > 0 && (
        <div className="divide-y divide-slate-200 dark:divide-slate-800">
            {groups.map((group) => {
                const events = group.events ?? [];

                return (
                    <div key={group.commission_id ?? 'none'} className="p-4">
                        <div className="mb-2 flex items-center gap-2">
                            <span className="font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Commission: {group.commission_id ?? '—'}
                            </span>

                            <StatusBadge status={group.latest_status} />

                            {group.snapshot && (
                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                    final {group.snapshot.value.toFixed(2)},{' '}
                                    <span className={lossTone(group.snapshot.loss)}>
                                        loss {group.snapshot.loss.toFixed(2)}
                                    </span>
                                </span>
                            )}

                            <span className="text-xs text-slate-400 dark:text-slate-600">
                                {events.length} update{events.length === 1 ? '' : 's'}
                            </span>
                        </div>

                        <table className="w-full border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                                    {HISTORY_COLUMNS.map((col) => (
                                        <th key={col.key} className="whitespace-nowrap px-3 py-2">
                                            {col.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {events.map((ev) => (
                                    <tr
                                        key={ev.id}
                                        className="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800/60 dark:hover:bg-slate-800/40"
                                    >
                                        {HISTORY_COLUMNS.map((col) => (
                                            <td key={col.key} className="whitespace-nowrap px-3 py-2 text-slate-700 dark:text-slate-300">
                                                {col.key === 'status' ? (
    <StatusBadge status={ev.status} />
) : isDateField(col.key) ? (
    <DateTime value={ev[col.key] as string | null} />
) : (
    ev[col.key] ?? <span className="text-slate-300 dark:text-slate-700">—</span>
)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                );
            })}
        </div>
    )}
</div>
                    </div>
                </div>
            )}
        </>
    );
}