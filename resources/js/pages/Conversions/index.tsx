import { Deferred, Head, router } from '@inertiajs/react';
import { Fragment, useEffect, useRef, useState } from 'react';
import DateTime from '@/components/date-time';
import PaginationBar from '@/components/pagination-bar';
import SearchInput from '@/components/search-input';

import { isDateField } from '@/lib/format-date';

type PaginationLink = { url: string | null; label: string; active: boolean };



type CommissionInfo = {
    affiliate_catalog_id: number;
    affiliate: string;
    commission_id: string;
    status: string | null;
    commission: number | null;
    accumulated: number | null;
    loss: number | null;
    events_count: number;
    commission_mode: string | null;
};

type ConversionRow = {
    id: number;
    click_id: string | null;
    click_pk: number | null;
    offer: string | null;
    affiliates: string[];

    // Final commission for the entire click
    commission: number | null;
    loss: number | null;
    commission_mode: string | null;

    // Individual commission ID values
    commissions: CommissionInfo[];

    events_count: number;

    created_at: string | null;
    updated_at: string | null;
};

type OfferGroup = {
    id: number;
    name: string;
    affiliate: string | null;
    conversions_count: number;
    latest_conversion_at: string;
};

type ConversionPage = {
    data: ConversionRow[];
    current_page: number;
    last_page: number;
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
    searchMode: 'offers' | 'conversions';
    conversions?: {
        data: ConversionRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    offerGroups?: {
        data: OfferGroup[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    search?: string | null;
        perPage: number;

};

type Theme = 'light' | 'dark';

const HISTORY_COLUMNS: { key: keyof ConversionEvent; label: string }[] = [
    { key: 'id', label: 'ID' },
    { key: 'event_type', label: 'Event type' },
    { key: 'currency', label: 'Currency' },
    { key: 'commission', label: 'Commission'},
    { key: 'status', label: 'Status' },
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

// SSR-safe: always starts at 'light' on both server and first client render,
// then resolves the real preference after mount to avoid a hydration mismatch.
function useTheme(): [Theme, () => void] {
    const [theme, setTheme] = useState<Theme>('light');
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        const stored = window.localStorage.getItem('theme');
        const resolved =
            stored === 'light' || stored === 'dark'
                ? stored
                : window.matchMedia('(prefers-color-scheme: dark)').matches
                    ? 'dark'
                    : 'light';
        setTheme(resolved);
        setMounted(true);
    }, []);

    useEffect(() => {
        if (!mounted) return;
        const root = document.documentElement;
        if (theme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        window.localStorage.setItem('theme', theme);
    }, [theme, mounted]);

    const toggle = () => setTheme((t) => (t === 'dark' ? 'light' : 'dark'));

    return [theme, toggle];
}
const PER_PAGE_OPTIONS = [15, 50, 100] as const;

const STATUS_TONES = {
    open: 'bg-yellow-100 text-yellow-800 ring-yellow-300 dark:bg-yellow-900/30 dark:text-yellow-300 dark:ring-yellow-700',
    confirmed: 'bg-blue-100 text-blue-800 ring-blue-300 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-700',
    paid: 'bg-green-100 text-green-800 ring-green-300 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-700',
    rejected: 'bg-red-100 text-red-800 ring-red-300 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-700',
    neutral: 'bg-neutral-100 text-neutral-700 ring-neutral-300 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
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

    if (n > 0) return 'text-red-600 dark:text-red-400';
    if (n < 0) return 'text-green-700 dark:text-green-400';

    return 'text-neutral-500 dark:text-neutral-400';
}

function StatusBadge({ status }: { status: string | null }) {
    if (!status) {
        return <span className="text-neutral-400 dark:text-neutral-500">—</span>;
    }

    return (
        <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${statusTone(status)}`}>
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
            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-neutral-200 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-700 dark:border-neutral-700 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
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

export default function Index({ conversions, offerGroups, searchMode, search, perPage }: Props) {
    const [theme, toggleTheme] = useTheme();
    const [activeClick, setActiveClick] = useState<{ pk: number; label: string | null } | null>(null);
    const [groups, setGroups] = useState<CommissionGroup[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [expandedOfferId, setExpandedOfferId] = useState<number | null>(null);
    const [offerConversionPage, setOfferConversionPage] = useState<ConversionPage | null>(null);
    const [offerConversionsLoading, setOfferConversionsLoading] = useState(false);
    const [offerConversionsError, setOfferConversionsError] = useState<string | null>(null);
    const offerConversionsRequest = useRef<AbortController | null>(null);

    const changePerPage = (value: number) => {
        router.get(
            window.location.pathname,
            { search: search || undefined, per_page: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const loadOfferConversions = async (offerId: number, page = 1, append = false) => {
        offerConversionsRequest.current?.abort();
        const controller = new AbortController();
        offerConversionsRequest.current = controller;
        setOfferConversionsLoading(true);
        setOfferConversionsError(null);

        try {
            const response = await fetch(`/conversions/offers/${offerId}?page=${page}&per_page=${perPage}`, {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Offer conversions request failed with status ${response.status}`);
            }

            const result: ConversionPage = await response.json();
            setOfferConversionPage((current) => append && current
                ? { ...result, data: [...current.data, ...result.data] }
                : result);
        } catch (e) {
            if (!(e instanceof DOMException && e.name === 'AbortError')) {
                setOfferConversionsError(e instanceof Error ? e.message : 'Unable to load offer conversions.');
            }
        } finally {
            if (offerConversionsRequest.current === controller) {
                offerConversionsRequest.current = null;
                setOfferConversionsLoading(false);
            }
        }
    };

    const toggleOffer = (offerId: number) => {
        offerConversionsRequest.current?.abort();

        if (expandedOfferId === offerId) {
            setExpandedOfferId(null);
            setOfferConversionPage(null);
            setOfferConversionsError(null);

            return;
        }

        setExpandedOfferId(offerId);
        setOfferConversionPage(null);
        void loadOfferConversions(offerId);
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

    return (
        <>
            <Head title="Conversions" />

            <div className="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
                <div className="mx-auto max-w-[1800px] px-6 py-10">
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-lg font-semibold tracking-tight">Conversions</h1>
                            <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                Latest status per click, sourced from the most recent conversion event.
                            </p>
                        </div>
                        <ThemeToggle theme={theme} onToggle={toggleTheme} />
                    </div>

                    <div className="mb-4 flex items-center justify-between gap-4">
    <div className="flex-1">
        <SearchInput initialValue={search ?? ''} placeholder="Search by click ID, commission ID, or offer name..." />
    </div>

    <label className="flex shrink-0 items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
        Rows per page
        <select
            value={perPage}
            onChange={(e) => changePerPage(Number(e.target.value))}
            className="rounded-md border border-neutral-200 bg-white px-2 py-1.5 text-sm text-neutral-700 shadow-sm transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
        >
            {PER_PAGE_OPTIONS.map((n) => (
                <option key={n} value={n}>
                    {n}
                </option>
            ))}
        </select>
    </label>
</div>
                    {searchMode === 'offers' ? (
                        <Deferred
                        data={['offerGroups']}
                        fallback={
                            <div aria-live="polite" className="mt-4 overflow-hidden rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                <p className="mb-4 text-sm text-neutral-500 dark:text-neutral-400">Finding matching offers…</p>
                                <div className="animate-pulse space-y-3">
                                    <div className="h-8 rounded bg-neutral-100 dark:bg-neutral-800" />
                                    {Array.from({ length: 5 }, (_, index) => (
                                        <div key={index} className="h-10 rounded bg-neutral-100 dark:bg-neutral-800" />
                                    ))}
                                </div>
                            </div>
                        }
                    >
                        {offerGroups && (
                            <>
                    <PaginationBar links={offerGroups.links} from={offerGroups.from} to={offerGroups.to} total={offerGroups.total} />
                    <div className="mt-3 overflow-hidden overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <table className="w-full table-auto text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 bg-neutral-50 text-left text-xs font-medium text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900/60 dark:text-neutral-400">
                                    <th className="w-8 px-4 py-3" />
                                    <th className="px-4 py-3">Offer</th>
                                    <th className="px-4 py-3">Affiliate</th>
                                    <th className="px-4 py-3 text-right">Conversions</th>
                                    <th className="px-4 py-3">Latest conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                {offerGroups.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">
                                            No matching offers with conversions.
                                        </td>
                                    </tr>
                                )}
                                {offerGroups.data.map((offer) => (
                                    <Fragment key={offer.id}>
                                        <tr className="border-b border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800">
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    aria-expanded={expandedOfferId === offer.id}
                                                    aria-label={`${expandedOfferId === offer.id ? 'Collapse' : 'Expand'} conversions for ${offer.name}`}
                                                    onClick={() => toggleOffer(offer.id)}
                                                    className="inline-flex h-7 w-7 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                                >
                                                    {expandedOfferId === offer.id ? '▾' : '▸'}
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 font-medium text-neutral-900 dark:text-neutral-100">{offer.name}</td>
                                            <td className="px-4 py-3 text-neutral-700 dark:text-neutral-300">{offer.affiliate ?? '—'}</td>
                                            <td className="px-4 py-3 text-right tabular-nums font-semibold text-neutral-900 dark:text-neutral-100">{offer.conversions_count}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">
                                                <DateTime value={offer.latest_conversion_at} />
                                            </td>
                                        </tr>
                                        {expandedOfferId === offer.id && (
                                            <tr className="border-b border-neutral-200 bg-neutral-50/70 dark:border-neutral-700 dark:bg-neutral-800/40">
                                                <td colSpan={5} className="p-4">
                                                    {offerConversionsError && (
                                                        <p role="alert" className="py-4 text-center text-sm text-red-600 dark:text-red-400">
                                                            {offerConversionsError}
                                                        </p>
                                                    )}
                                                    {!offerConversionsError && offerConversionsLoading && !offerConversionPage && (
                                                        <p role="status" className="py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                                            Loading offer conversions…
                                                        </p>
                                                    )}
                                                    {!offerConversionsLoading && !offerConversionsError && offerConversionPage?.data.length === 0 && (
                                                        <p className="py-4 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                                            No conversions recorded for this offer.
                                                        </p>
                                                    )}
                                                    {offerConversionPage && offerConversionPage.data.length > 0 && (
                                                        <div className="overflow-x-auto rounded-lg border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                                                            <table className="w-full min-w-[760px] text-left text-sm">
                                                                <thead>
                                                                    <tr className="border-b border-neutral-200 bg-neutral-50 text-xs text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
                                                                        <th className="px-3 py-2">Click ID</th>
                                                                        <th className="px-3 py-2">Commission ID</th>
                                                                        <th className="px-3 py-2 text-right">Events</th>
                                                                        <th className="px-3 py-2">Total</th>
                                                                        <th className="px-3 py-2">Updated</th>
                                                                        <th className="px-3 py-2 text-right">History</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {offerConversionPage.data.map((row) => (
                                                                            <tr key={row.id} className="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                                                            <td className="px-3 py-2 font-mono text-xs text-neutral-700 dark:text-neutral-300">{row.click_id ?? '—'}</td>
                                                                            <td className="px-3 py-2">
                                                                                <div className="flex flex-wrap gap-1">
                                                                                    {row.commissions.map((commission) => (
                                                                                        <span key={`${commission.affiliate_catalog_id}:${commission.commission_id}`} title={`${commission.affiliate} — ${commission.commission_id}`} className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] ring-1 ring-inset ${statusTone(commission.status)}`}>
                                                                                                <span className="font-mono">{commission.commission_id}</span>
                                                                                            <span className="font-semibold">{commission.status ?? '—'}</span>
                                                                                            <span>{commission.commission !== null ? Number(commission.commission).toFixed(2) : '—'}</span>
                                                                                        </span>
                                                                                    ))}
                                                                                </div>
                                                                            </td>
                                                                                <td className="px-3 py-2 text-right tabular-nums text-neutral-700 dark:text-neutral-300">{row.events_count}</td>
                                                                                <td className={`whitespace-nowrap px-3 py-2 font-semibold ${Number(row.commission ?? 0) > 0 ? 'text-green-700 dark:text-green-400' : Number(row.commission ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'}`}>
                                                                                {row.commission !== null ? Number(row.commission).toFixed(2) : '—'}
                                                                            </td>
                                                                            <td className="whitespace-nowrap px-3 py-2 text-xs text-neutral-500 dark:text-neutral-400">
                                                                                <DateTime value={row.updated_at ?? '—'} />
                                                                            </td>
                                                                            <td className="px-3 py-2 text-right">
                                                                                <button
                                                                                    type="button"
                                                                                    onClick={() => {
                                                                                        if (row.click_pk !== null) {
                                                                                            openHistory(row.click_pk, row.click_id);
                                                                                        }
                                                                                    }}
                                                                                    disabled={row.click_pk === null}
                                                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-700 disabled:cursor-not-allowed disabled:text-neutral-300 dark:text-indigo-400 dark:hover:text-indigo-300 dark:disabled:text-neutral-700"
                                                                                >
                                                                                    View
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                            {offerConversionPage.current_page < offerConversionPage.last_page && (
                                                                <div className="border-t border-neutral-200 p-3 text-center dark:border-neutral-700">
                                                                    <button
                                                                        type="button"
                                                                        disabled={offerConversionsLoading}
                                                                        onClick={() => void loadOfferConversions(offer.id, offerConversionPage.current_page + 1, true)}
                                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-700 disabled:opacity-50 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                                    >
                                                                        {offerConversionsLoading ? 'Loading…' : 'Load more conversions'}
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <PaginationBar links={offerGroups.links} from={offerGroups.from} to={offerGroups.to} total={offerGroups.total} />
                            </>
                        )}
                        </Deferred>
                    ) : (
                    <Deferred
                        data={['conversions']}
                        fallback={
                            <div aria-live="polite" className="mt-4 overflow-hidden rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                <p className="mb-4 text-sm text-neutral-500 dark:text-neutral-400">Loading the latest conversions…</p>
                                <div className="animate-pulse space-y-3">
                                    <div className="h-8 rounded bg-neutral-100 dark:bg-neutral-800" />
                                    {Array.from({ length: 5 }, (_, index) => (
                                        <div key={index} className="h-10 rounded bg-neutral-100 dark:bg-neutral-800" />
                                    ))}
                                </div>
                            </div>
                        }
                    >
                        {conversions && (
                            <>
                    <PaginationBar links={conversions.links} from={conversions.from} to={conversions.to} total={conversions.total} />

                    <div className="mt-3 overflow-hidden overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                        <table className="w-max min-w-full table-auto text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 bg-neutral-50 text-left text-xs font-medium text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900/60 dark:text-neutral-400">
                                    <th className="px-4 py-3">Offer</th>
                                    <th className="px-4 py-3">Click ID</th>
                                    <th className="px-4 py-3">Affiliate</th>
                                    <th className="px-4 py-3">Commissions IDs</th>
                                    <th className="px-4 py-3">Total Commission</th>
                                    <th className="px-4 py-3 text-right">Events</th>
                                    <th className="px-4 py-3 text-right">History</th>
                                    <th className="px-4 py-3">Created</th>
                                    <th className="px-4 py-3">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                {conversions.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">
                                            No conversions yet.
                                        </td>
                                    </tr>
                                )}

                                {conversions.data.map((row) => (
                                    <tr
                                        key={row.id}
className="border-b border-neutral-200 transition-colors last:border-0
  even:bg-neutral-50 hover:bg-neutral-100
  dark:border-neutral-700/60 dark:even:bg-neutral-800/50 dark:hover:bg-neutral-800"
                                      >
                                        <td className="max-w-[220px] truncate px-4 py-3 text-neutral-700 dark:text-neutral-300">
                                            {row.offer ?? '—'}
                                        </td>
                                        <td className="max-w-[220px] truncate px-4 py-3 font-mono text-[13px] text-neutral-700 dark:text-neutral-300">
                                            {row.click_id ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {row.affiliates.length > 0 ? row.affiliates.map((affiliate) => (
                                                    <span key={affiliate} className="rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                                        {affiliate}
                                                    </span>
                                                )) : <span className="text-neutral-400 dark:text-neutral-500">—</span>}
                                            </div>
                                        </td>

                                        <td className="px-4 py-3">
                                            <div className="flex max-w-[750px] flex-wrap gap-1.5">
                                                {row.commissions.length > 0 ? (
                                                    row.commissions.map((commission) => {
                                                            const statusClasses = statusTone(commission.status);

                                                        return (
                                                            <span
                                                                key={`${commission.affiliate_catalog_id}:${commission.commission_id}`}
                                                                className={`inline-flex items-center gap-2 rounded-md px-2 py-1 font-mono text-[11px] font-medium ring-1 ring-inset ${statusClasses}`}
                                                                title={`${commission.affiliate} — Commission ID: ${commission.commission_id}`}
                                                            >
                                                                <span>
                                                                    {commission.commission_id}
                                                                </span>

                                                                <span className="font-sans font-semibold">
                                                                    {commission.status ?? '—'}
                                                                </span>

                                                                <span className="font-sans font-bold">
                                                                    {commission.commission !== null
                                                                        ? Number(commission.commission).toFixed(2)
                                                                        : '—'}
                                                                </span>
                                                                            |
                                                                <span className={`font-sans ${lossTone(commission.loss)}`}>
                                                                    loss {commission.loss !== null ? Number(commission.loss).toFixed(2) : '0.00'}
                                                                </span>
                                                            </span>
                                                        );
                                                    })
                                                ) : (
                                                    <span className="text-neutral-400 dark:text-neutral-500">
                                                        —
                                                    </span>
                                                )}
                                            </div>
                                        </td>

                                        <td className={`whitespace-nowrap px-4 py-3 font-semibold ${Number(row.commission ?? 0) > 0 ? 'text-green-700 dark:text-green-400' : Number(row.commission ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-neutral-500 dark:text-neutral-400'}`}>
                                            {row.commission !== null
                                                ? Number(row.commission).toFixed(2)
                                                : '—'}
                                        </td>

                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <span className="inline-flex min-w-6 items-center justify-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                                {row.events_count}
                                            </span>
                                        </td>
                                                                                <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                className="text-sm font-medium text-indigo-600 hover:text-indigo-700 disabled:cursor-not-allowed disabled:text-neutral-300 dark:text-indigo-400 dark:hover:text-indigo-300 dark:disabled:text-neutral-700"
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
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">
                                             <DateTime value={(row.created_at) ?? '—'}/>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-neutral-500 dark:text-neutral-400">
                                            <DateTime value={(row.updated_at) ?? '—'}/>
                                        </td>

                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <PaginationBar links={conversions.links} from={conversions.from} to={conversions.to} total={conversions.total} />
                            </>
                        )}
                    </Deferred>
                    )}
                </div>
            </div>

            {activeClick && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/50 p-4 backdrop-blur-sm"
                    onClick={closeHistory}
                >
                    <div
                        className="flex max-h-[85vh] w-[95vw] max-w-[1600px] flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xl dark:border-neutral-700 dark:bg-neutral-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                            <div>
                                <h2 className="text-sm font-semibold">History</h2>
                                <p className="mt-0.5 font-mono text-xs text-neutral-500 dark:text-neutral-400">
                                    Click ID: {activeClick.label ?? '—'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeHistory}
                                aria-label="Close"
                                className="inline-flex h-7 w-7 items-center justify-center rounded-md text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-300"
                            >
                                <svg viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4">
                                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                </svg>
                            </button>
                        </div>

                        <div className="overflow-auto">
                            {loading && (
                                <div className="px-6 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">
                                    Loading history…
                                </div>
                            )}

                            {!loading && error && (
                                <div className="px-6 py-10 text-center text-sm text-red-600 dark:text-red-400">
                                    {error}
                                </div>
                            )}

                            {!loading && !error && groups.length === 0 && (
                                <div className="px-6 py-10 text-center text-sm text-neutral-400 dark:text-neutral-500">
                                    No events recorded for this click.
                                </div>
                            )}

                            {!loading && !error && groups.length > 0 && (
                                <div className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                    {groups.map((group) => {
                                        const events = group.events ?? [];
                                        return (
                                            <div key={group.commission_id ?? 'none'} className="p-4">
                                                <div className="mb-2 flex items-center gap-2">
                                                    <span className="font-mono text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                                                        Commission: {group.commission_id ?? '—'}
                                                    </span>
                                                    <StatusBadge status={group.latest_status} />
                                                    {group.snapshot && (
                                                        <span className="text-xs text-neutral-500 dark:text-neutral-400">
                                                            final {group.snapshot.value.toFixed(2)},{' '}
                                                            <span className={lossTone(group.snapshot.loss)}>
                                                                loss {group.snapshot.loss.toFixed(2)}
                                                            </span>
                                                        </span>
                                                    )}
                                                    <span className="text-xs text-neutral-400 dark:text-neutral-500">
                                                        {events.length} update{events.length === 1 ? '' : 's'}
                                                    </span>
                                                </div>

                                                <table className="w-full border-collapse text-xs">
                                                    <thead>
                                                        <tr className="border-b border-neutral-200 bg-neutral-50 text-left font-medium text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-400">
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
                                                                className="border-b border-neutral-100 last:border-0 hover:bg-neutral-50 dark:border-neutral-800/60 dark:hover:bg-neutral-800/40"
                                                            >
                                                                {HISTORY_COLUMNS.map((col) => (
                                                                    <td key={col.key} className="whitespace-nowrap px-3 py-2 text-neutral-700 dark:text-neutral-300">
                                                                        {col.key === 'status' ? (
    <StatusBadge status={ev.status} />
) : isDateField(col.key) ? (
    <DateTime value={ev[col.key] as string | null} />
) : (
    ev[col.key] ?? <span className="text-neutral-300 dark:text-neutral-700">—</span>
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