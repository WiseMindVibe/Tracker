import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';

type ConversionRow = {
    id: number;
    click_id: string | null;
    click_pk: number | null;
    commission: string | null;
    commission_mode: string | null;
    status: string | null;
    transaction_id: string | null;
    events_count: number | null;
    created_at: string | null;
    updated_at: string | null;
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

type Props = {
    conversions: {
        data: ConversionRow[];
    };
};

type Theme = 'light' | 'dark';

const HISTORY_COLUMNS: { key: keyof ConversionEvent; label: string }[] = [
    { key: 'id', label: 'ID' },
    { key: 'commission_id', label: 'Commission ID' },
    { key: 'commission', label: 'Commission' },
    { key: 'currency', label: 'Currency' },
    { key: 'status', label: 'Status' },
    { key: 'event_id', label: 'Event ID' },
    { key: 'event_type', label: 'Event type' },
    { key: 'advertiser_id', label: 'Advertiser ID' },
    { key: 'advertiser_name', label: 'Advertiser name' },
    { key: 'commission_type', label: 'Commission type' },
    { key: 'payout_id', label: 'Payout ID' },
    { key: 'country_code', label: 'Country' },
    { key: 'site_id', label: 'Site ID' },
    { key: 'sale_date', label: 'Sale date' },
    { key: 'modified_date', label: 'Modified date' },
    { key: 'advertiser_sale_amount', label: 'Advertiser sale amount' },
    { key: 'created_at', label: 'Created at' },
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

function StatusBadge({ status }: { status: string | null }) {
    if (!status) {
        return <span className="text-slate-400 dark:text-slate-600">—</span>;
    }

    const normalized = status.toLowerCase();

    const tone = /open|pending|new/.test(normalized)
        ? 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-400 dark:ring-amber-400/20'
        : /reject|declin|cancel|void/.test(normalized)
          ? 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-400 dark:ring-rose-400/20'
          : /approv|confirm|paid|complet/.test(normalized)
            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-400 dark:ring-emerald-400/20'
            : 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20';

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${tone}`}>
            {status}
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

export default function Index({ conversions }: Props) {
    const [theme, toggleTheme] = useTheme();
    const [activeClick, setActiveClick] = useState<{ pk: number; label: string | null } | null>(null);
    const [events, setEvents] = useState<ConversionEvent[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const openHistory = async (pk: number, label: string | null) => {
        setActiveClick({ pk, label });
        setEvents([]);
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

            const data: { data: ConversionEvent[] } = await response.json();
            setEvents(data.data);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Something went wrong loading history.');
        } finally {
            setLoading(false);
        }
    };

    const closeHistory = () => {
        setActiveClick(null);
        setEvents([]);
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

    return (
        <>
            <Head title="Conversions" />

            <div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
                <div className="mx-auto max-w-[1400px] px-6 py-10">
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">Conversions</h1>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Latest status per click, sourced from the most recent conversion event.
                            </p>
                        </div>
                        <ThemeToggle theme={theme} onToggle={toggleTheme} />
                    </div>

                    <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                    <th className="px-4 py-3">Click ID</th>
                                    <th className="px-4 py-3">Commission</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Transaction ID</th>
                                    <th className="px-4 py-3 text-right">Events</th>
                                    <th className="px-4 py-3">Created</th>
                                    <th className="px-4 py-3">Updated</th>
                                    <th className="px-4 py-3 text-right">History</th>
                                </tr>
                            </thead>
                            <tbody>
                                {conversions.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-600">
                                            No conversions yet.
                                        </td>
                                    </tr>
                                )}

                                {conversions.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-800/60 dark:hover:bg-slate-800/40"
                                    >
                                        <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300">
                                            {row.click_id ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {row.commission_mode === 'absolute' ? (
                                                row.commission ?? '—'
                                            ) : (
                                                <span className="text-slate-400 dark:text-slate-600">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={row.status} />
                                        </td>
                                        <td className="px-4 py-3 font-mono text-[13px] text-slate-700 dark:text-slate-300">
                                            {row.transaction_id ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <span className="inline-flex min-w-6 items-center justify-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                {row.events_count ?? 0}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                            {row.created_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                            {row.updated_at ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
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
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* wire up conversions.links here for pagination */}
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

                            {!loading && !error && events.length === 0 && (
                                <div className="px-6 py-10 text-center text-sm text-slate-400 dark:text-slate-600">
                                    No events recorded for this click.
                                </div>
                            )}

                            {!loading && !error && events.length > 0 && (
                                <table className="w-full border-collapse text-xs">
                                    <thead>
                                        <tr className="sticky top-0 border-b border-slate-200 bg-slate-50 text-left font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
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
                                                        ) : (
                                                            ev[col.key] ?? <span className="text-slate-300 dark:text-slate-700">—</span>
                                                        )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}