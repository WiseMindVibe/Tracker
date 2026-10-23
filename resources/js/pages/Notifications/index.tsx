import { Deferred, Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import DateTime from '@/components/date-time';
import PaginationBar from '@/components/pagination-bar';
import SearchInput from '@/components/search-input';

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
    notification_created_at: string | null;
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
    notifications?: {
        data: NotificationRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    unreadCount?: number;
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
    neutral: 'bg-muted text-muted-foreground ring-border',
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

    return 'text-muted-foreground';
}

function StatusBadge({ status }: { status: string | null }) {
    if (!status) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${statusTone(status)}`}>
            {status}
        </span>
    );
}

function ReadBadge({ isRead }: { isRead: boolean }) {
    const tone = isRead
    ? 'bg-muted text-muted-foreground ring-border'
    : 'bg-destructive/10 text-destructive ring-destructive/30';

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
            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border text-muted-foreground transition hover:bg-muted hover:text-foreground"
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

export default function Index({ notifications, unreadCount = 0, search, perPage }: Props) {
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

            <div className="min-w-0 bg-background text-foreground">
                <div className="mx-auto max-w-[1800px] px-3 py-5 sm:px-6 sm:py-8">
                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">Notifications</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Events flagged as notification-worthy — status changes or meaningful commission moves.
                            </p>
                        </div>
                        <div className="flex w-full items-center gap-2 sm:w-auto sm:gap-3">
                            <button
                                type="button"
                                onClick={markAllRead}
                                disabled={markingAll || unreadCount === 0}
                                className="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm font-medium text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                            >
                                {markingAll ? 'Marking…' : `Mark all as read${unreadCount ? ` (${unreadCount})` : ''}`}
                            </button>
                            <ThemeToggle theme={theme} onToggle={toggleTheme} />
                        </div>
                    </div>
<div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
    <div className="w-full min-w-0 sm:flex-1">
        <SearchInput initialValue={search ?? ''} placeholder="Search by click ID, commission ID, or offer name..." />
    </div>

    <label className="flex w-full items-center justify-between gap-2 text-sm text-muted-foreground sm:w-auto sm:shrink-0">
        Rows per page
        <select
            value={perPage}
            onChange={(e) => changePerPage(Number(e.target.value))}
            className="min-h-10 rounded-md border border-input bg-background px-3 py-1.5 text-sm text-foreground shadow-sm transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary/40"
        >
            {PER_PAGE_OPTIONS.map((n) => (
                <option key={n} value={n}>
                    {n}
                </option>
            ))}
        </select>
    </label>
</div>

<Deferred
    data={['notifications', 'unreadCount']}
    fallback={
        <div
            role="status"
            aria-live="polite"
            className="mt-4 overflow-hidden rounded-lg border border-border bg-card p-4 shadow-sm"
        >
            <p className="mb-4 text-sm text-muted-foreground">Loading recent notifications…</p>
            <div className="animate-pulse space-y-3">
                <div className="h-8 rounded bg-muted" />
                {Array.from({ length: 5 }, (_, index) => (
                    <div key={index} className="h-12 rounded bg-muted" />
                ))}
            </div>
        </div>
    }
>
{notifications && (
<>
<PaginationBar links={notifications.links} from={notifications.from} to={notifications.to} total={notifications.total} />

                    <div className="mt-3 space-y-3 xl:hidden">
                        {notifications.data.length === 0 ? (
                            <div className="rounded-lg border border-border bg-card px-4 py-10 text-center text-sm text-muted-foreground">
                                No notifications yet.
                            </div>
                        ) : (
                            notifications.data.map((row) => (
                                <article
                                    key={row.id}
                                    className={`min-w-0 rounded-lg border border-border p-4 shadow-sm ${
                                        row.is_read ? 'bg-card' : 'bg-orange-300/15'
                                    }`}
                                >
                                    <div className="flex min-w-0 items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <h2 className="break-words text-sm font-semibold text-foreground">
                                                {row.offer ?? '—'}
                                            </h2>
                                            <p className="mt-1 break-words text-sm text-muted-foreground">
                                                {row.campaign ?? '—'}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-2">
                                            <StatusBadge status={row.status} />
                                            <ReadBadge isRead={row.is_read} />
                                        </div>
                                    </div>

                                    <div className="mt-4 grid grid-cols-3 gap-2 rounded-md bg-muted/40 p-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="text-xs text-muted-foreground">Commission</p>
                                            <p className="mt-1 break-words font-medium tabular-nums">
                                                {row.commission ?? '—'}{row.currency ? ` ${row.currency}` : ''}
                                            </p>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs text-muted-foreground">Final</p>
                                            <p className="mt-1 break-words font-medium tabular-nums">
                                                {row.final_commission !== null ? Number(row.final_commission).toFixed(2) : '—'}
                                            </p>
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-xs text-muted-foreground">Loss</p>
                                            <p className={`mt-1 break-words font-medium tabular-nums ${lossTone(row.loss)}`}>
                                                {row.loss !== null ? Number(row.loss).toFixed(2) : '0.00'}
                                            </p>
                                        </div>
                                    </div>

                                    <dl className="mt-4 grid min-w-0 grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Click ID</dt>
                                            <dd className="mt-1 break-all font-mono text-xs">{row.click_id ?? '—'}</dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Commission ID</dt>
                                            <dd className="mt-1 break-all font-mono text-xs">{row.commission_id ?? '—'}</dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Click total</dt>
                                            <dd className="mt-1 font-medium tabular-nums">
                                                {row.click_commission !== null ? Number(row.click_commission).toFixed(2) : '—'}
                                                {row.currency ? ` ${row.currency}` : ''}
                                            </dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Affiliate / event</dt>
                                            <dd className="mt-1 break-words">
                                                {row.affiliate ?? '—'}{row.event_type ? ` · ${row.event_type}` : ''}
                                            </dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Click created</dt>
                                            <dd className="mt-1 text-xs"><DateTime value={row.click_created_at ?? '—'} /></dd>
                                        </div>
                                        <div className="min-w-0">
                                            <dt className="text-xs text-muted-foreground">Notification created</dt>
                                            <dd className="mt-1 text-xs"><DateTime value={row.notification_created_at} /></dd>
                                        </div>
                                    </dl>

                                    <div className="mt-4 flex flex-col gap-2 border-t border-border pt-3 sm:flex-row">
                                        <button
                                            type="button"
                                            className="inline-flex min-h-10 flex-1 items-center justify-center rounded-md border border-border px-3 text-sm font-medium text-primary hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                            onClick={() => {
                                                if (row.click_pk !== null) {
                                                    openHistory(row.click_pk, row.click_id);
                                                }
                                            }}
                                            disabled={row.click_pk === null}
                                        >
                                            View history
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => toggleRead(row.id)}
                                            disabled={togglingId === row.id}
                                            className="inline-flex min-h-10 flex-1 items-center justify-center rounded-md border border-border px-3 text-sm font-medium text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {togglingId === row.id ? 'Updating…' : row.is_read ? 'Mark unread' : 'Mark read'}
                                        </button>
                                    </div>
                                </article>
                            ))
                        )}
                    </div>

                    <div className="mt-3 hidden max-w-full overflow-x-auto touch-pan-x rounded-lg border border-border bg-card shadow-sm xl:block">
                    <table className="w-max min-w-full table-auto text-sm">
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-xs font-medium text-muted-foreground">
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
                                        <td colSpan={17} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                            No notifications yet.
                                        </td>
                                    </tr>
                                )}

                                {notifications.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className={`border-b border-border transition-colors last:border-0 ${
                                            row.is_read ? 'even:bg-muted/40 hover:bg-accent' : 'bg-orange-300/15 hover:bg-primary/15'
                                        }`}
                                    >
                                        <td className="max-w-[220px] truncate px-4 py-3 font-mono text-[13px] text-foreground">
                                            {row.offer ?? '—'}
                                        </td>
                                        <td className="max-w-[220px] truncate px-4 py-3 font-mono text-[13px] text-foreground">
                                            {row.campaign ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums max-w-[220px] truncate">
                                            {row.commission ?? '—'}
                                            {row.currency ? (
                                                <span className="ml-1 text-xs text-muted-foreground">{row.currency}</span>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={row.status} />
                                        </td>
                                        <td className="max-w-[220px] truncate px-4 py-3 font-mono text-[13px] text-foreground">
                                            {row.click_id ?? '—'}
                                        </td>
                                       <td className="max-w-[220px] truncate px-4 py-3 font-mono text-[13px] text-foreground">
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
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                            <DateTime value={(row.click_created_at) ?? '—'}/>
                                        </td>
                                        <td className="px-4 py-3 max-w-[220px] truncate">
                                            <ReadBadge isRead={row.is_read} />
                                        </td>
                                        <td className="px-4 py-3 text-right max-w-[220px] truncate">
                                            <button
                                                type="button"
                                                className="text-sm font-medium text-primary underline-offset-4 hover:underline disabled:cursor-not-allowed disabled:opacity-50"
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
                                                        ? 'text-sm font-medium text-muted-foreground hover:text-foreground disabled:cursor-not-allowed disabled:opacity-50'
                                                        : 'border border-destructive/30 bg-destructive/5 text-destructive hover:bg-destructive/10'
                                                }`}                                            >
                                                {togglingId === row.id ? '…' : row.is_read ? 'Mark unread' : 'Mark read'}
                                            </button>
                                        </td>
                                       <td className="max-w-[220px] truncate px-4 py-3 text-muted-foreground">
                                            {row.affiliate ?? '—'}
                                        </td>
                                        <td className="max-w-[220px] truncate px-4 py-3 text-muted-foreground">
                                            {row.event_type ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <DateTime value={row.read_at} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <DateTime value={row.notification_created_at} />
                                        </td>

                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

<PaginationBar links={notifications.links} from={notifications.from} to={notifications.to} total={notifications.total} />
</>
)}
</Deferred>
                </div>
            </div>

            {activeClick && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 backdrop-blur-sm sm:p-4"
                    onClick={closeHistory}
                >
                    <div
                        className="flex max-h-[calc(100dvh-1rem)] w-full max-w-[1600px] flex-col overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-xl sm:max-h-[85dvh] sm:w-[95vw]"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-6 sm:py-4">
                            <div className="min-w-0">
                                <h2 className="text-sm font-semibold">History</h2>
                                <p className="mt-0.5 break-all font-mono text-xs text-muted-foreground">
                                    {activeClick.label ?? '—'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeHistory}
                                aria-label="Close"
                                className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        </div>

                        <div className="min-h-0 overflow-auto overscroll-contain">
    {loading && (
        <div className="px-6 py-10 text-center text-sm text-muted-foreground">
            Loading history…
        </div>
    )}

    {!loading && error && (
        <div className="px-6 py-10 text-center text-sm text-rose-600 dark:text-rose-400">
            {error}
        </div>
    )}

    {!loading && !error && groups.length === 0 && (
        <div className="px-6 py-10 text-center text-sm text-muted-foreground">
            No events recorded for this click.
        </div>
    )}

    {!loading && !error && groups.length > 0 && (
        <div className="divide-y divide-border">
            {groups.map((group) => {
                const events = group.events ?? [];

                return (
                    <div key={group.commission_id ?? 'none'} className="min-w-0 p-3 sm:p-4">
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <span className="font-mono text-xs font-semibold text-foreground">
                                Commission: {group.commission_id ?? '—'}
                            </span>

                            <StatusBadge status={group.latest_status} />

                            {group.snapshot && (
                                <span className="text-xs text-muted-foreground">
                                    final {group.snapshot.value.toFixed(2)},{' '}
                                    <span className={lossTone(group.snapshot.loss)}>
                                        loss {group.snapshot.loss.toFixed(2)}
                                    </span>
                                </span>
                            )}

                            <span className="text-xs text-muted-foreground">
                                {events.length} update{events.length === 1 ? '' : 's'}
                            </span>
                        </div>

                        <div className="max-w-full overflow-x-auto touch-pan-x">
                        <table className="min-w-max border-collapse text-xs">
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left font-medium text-muted-foreground">
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
                                        className="border-b border-border last:border-0 hover:bg-accent"
                                    >
                                        {HISTORY_COLUMNS.map((col) => (
                                            <td key={col.key} className="whitespace-nowrap px-3 py-2 text-foreground">
                                                {col.key === 'status' ? (
    <StatusBadge status={ev.status} />
) : isDateField(col.key) ? (
    <DateTime value={ev[col.key] as string | null} />
) : (
    ev[col.key] ?? <span className="text-muted-foreground">—</span>
)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
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