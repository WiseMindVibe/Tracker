import { Head } from '@inertiajs/react';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    CalendarDays,
    CircleDollarSign,
    Clock3,
    MousePointerClick,
    ShoppingCart,
    Sparkles,
    Target,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import DateRangePicker from '@/components/date-range/date-range-picker';
import SearchableSelect from '@/components/searchable-select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { DateRangeValue } from '@/lib/date-range';
import {
    addDays,
    defaultQuickOptions,
    fromYmd,
    getPresetLabel,
    toYmd,
} from '@/lib/date-range';
import { formatCell } from '@/pages/Reporting/format';
import { postJson } from '@/pages/Reporting/request';
import type { ReportRowData, ReportTotals } from '@/pages/Reporting/types';

interface DailyMetric {
    date: string;
    clicks: number;
    conversions: number;
    revenue: number;
    cr: number;
    profit: number;
}

interface OfferOption {
    id: number;
    name: string;
    country: string;
    blog: string | null;
    affiliateNetwork: string | null;
}

interface Props {
    offers: OfferOption[];
    selectedOfferId: number | null;
    analyticsEndpoint: string;
    today: string;
    timezone: string;
}

interface CommissionExtreme {
    amount: number;
    currency: string | null;
    status: string;
    affiliate: string | null;
}

interface PeriodInsights {
    days: DailyMetric[];
    transitionAverages: {
        openToConfirmedMinutes: number | null;
        openToRejectedMinutes: number | null;
        confirmedToPaidMinutes: number | null;
        samples: {
            openToConfirmed: number;
            openToRejected: number;
            confirmedToPaid: number;
        };
    };
    lowestCommission: CommissionExtreme | null;
    highestCommission: CommissionExtreme | null;
}

interface InspectionPeriod {
    label: string;
    from: string;
    to: string;
    totals: ReportTotals;
    campaigns: ReportRowData[];
    insights: PeriodInsights;
}

interface InspectionResponse {
    lifetime: InspectionPeriod;
    this_year: InspectionPeriod;
    last_90_days: InspectionPeriod;
    selected: InspectionPeriod;
}

type PeriodKey = keyof InspectionResponse;
type RequestState =
    | { key: string; status: 'loaded'; data: InspectionResponse }
    | { key: string; status: 'error'; errorMessage: string };

const PERIODS: Array<{ key: PeriodKey; title: string }> = [
    { key: 'lifetime', title: 'Lifetime' },
    { key: 'this_year', title: 'This year' },
    { key: 'last_90_days', title: 'Last 90 days' },
    { key: 'selected', title: 'Selected period' },
];

const METRICS = [
    { key: 'clicks', label: 'Clicks', icon: MousePointerClick },
    { key: 'total_conversions', label: 'Conversions', icon: ShoppingCart },
    { key: 'revenue', label: 'Revenue', icon: CircleDollarSign },
    { key: 'spent', label: 'Spend', icon: ArrowDownRight },
    { key: 'profit', label: 'Profit', icon: ArrowUpRight },
    { key: 'cr', label: 'Conversion rate', icon: Target },
    { key: 'roi', label: 'ROI', icon: Activity },
    { key: 'avg_payout', label: 'Average payout', icon: CircleDollarSign },
] as const;

function metricValue(totals: ReportTotals, key: string): number | null {
    const clicks = totals.clicks ?? 0;
    const conversions = totals.total_conversions ?? 0;
    const spent = totals.spent ?? 0;
    const profit = totals.profit ?? 0;

    if (key === 'cr') {
        return clicks > 0 ? (conversions / clicks) * 100 : null;
    }

    if (key === 'roi') {
        return spent > 0 ? profit / spent : null;
    }

    if (key === 'avg_payout') {
        const qualifiedConversions =
            (totals.open_conversions ?? 0) + (totals.confirmed_conversions ?? 0) + (totals.paid_conversions ?? 0);

        return qualifiedConversions > 0 ? (totals.revenue ?? 0) / qualifiedConversions : null;
    }

    const value = totals[key];

    return typeof value === 'number' ? value : null;
}

function approvalRate(totals: ReportTotals): number | null {
    const approved = (totals.confirmed_conversions ?? 0) + (totals.paid_conversions ?? 0);
    const resolved = approved + (totals.rejected_conversions ?? 0);

    return resolved > 0 ? (approved / resolved) * 100 : null;
}

function formatDuration(minutes: number | null): string {
    if (minutes === null) {
        return 'Not enough status history';
    }

    const rounded = Math.round(minutes);

    if (rounded < 60) {
        return `${rounded} min`;
    }

    const hours = Math.floor(rounded / 60);
    const remainingMinutes = rounded % 60;

    if (hours < 48) {
        return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`;
    }

    const days = Math.floor(hours / 24);
    const remainingHours = hours % 24;

    return remainingHours > 0 ? `${days}d ${remainingHours}h` : `${days}d`;
}

function formatTransition(minutes: number | null, samples: number): string {
    if (minutes === null) {
        return 'Not enough status history';
    }

    return `${formatDuration(minutes)} · ${samples} ${samples === 1 ? 'commission' : 'commissions'}`;
}

function formatCommission(commission: CommissionExtreme | null): string {
    if (!commission) {
        return 'No commission data';
    }

    return `${commission.amount.toFixed(2)} ${commission.currency ?? ''}`.trim();
}

function weekdayFor(date: string): string {
    return new Intl.DateTimeFormat('en', { weekday: 'long', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
}

function periodDateRange(period: InspectionPeriod): string {
    return `${period.from} – ${period.to}`;
}

export default function InspectIndex({ offers, selectedOfferId, analyticsEndpoint, today, timezone }: Props) {
    const [offerId, setOfferId] = useState(selectedOfferId?.toString() ?? '');
    const [dateRange, setDateRange] = useState<DateRangeValue>(() => {
        const end = fromYmd(today);

        return { start: addDays(end, -29), end, preset: 'last-30', includeToday: true };
    });
    const [requestState, setRequestState] = useState<RequestState | null>(null);
    const dateFrom = dateRange.start ? toYmd(dateRange.start) : '1000-01-01';
    const dateTo = dateRange.end ? toYmd(dateRange.end) : today;
    const isDateRangeComplete = dateRange.preset === 'all-time' || (!!dateRange.start && !!dateRange.end);
    const requestKey = `${offerId}:${dateFrom}:${dateTo}:${analyticsEndpoint}`;

    useEffect(() => {
        if (!offerId || !isDateRangeComplete) {
            return;
        }

        let isCurrentRequest = true;
        postJson<InspectionResponse>(analyticsEndpoint, {
            offer_id: Number(offerId),
            date_from: dateFrom,
            date_to: dateTo,
        })
            .then((data) => {
                if (isCurrentRequest) {
                    setRequestState({ key: requestKey, status: 'loaded', data });
                }
            })
            .catch((error: unknown) => {
                if (isCurrentRequest) {
                    setRequestState({
                        key: requestKey,
                        status: 'error',
                        errorMessage: error instanceof Error ? error.message : 'Unable to load offer performance.',
                    });
                }
            });

        return () => {
            isCurrentRequest = false;
        };
    }, [analyticsEndpoint, dateFrom, dateTo, isDateRangeComplete, offerId, requestKey]);

    const currentRequest = requestState?.key === requestKey ? requestState : null;
    const loadState = !offerId ? 'idle' : !isDateRangeComplete ? 'idle' : currentRequest?.status ?? 'loading';
    const data = currentRequest?.status === 'loaded' ? currentRequest.data : null;
    const errorMessage = currentRequest?.status === 'error' ? currentRequest.errorMessage : '';
    const offerOptions = useMemo(
        () =>
            Object.fromEntries(
                offers.map((offer) => [
                    String(offer.id),
                    [offer.name, offer.country, offer.blog, offer.affiliateNetwork].filter(Boolean).join(' · '),
                ]),
            ),
        [offers],
    );

    const selectedOffer = offers.find((offer) => String(offer.id) === offerId);
    const selectedLabel = getPresetLabel(defaultQuickOptions, dateRange.preset) ?? 'Custom range';
    const selectedPeriod = data?.selected;
    const campaignRows = useMemo(() => {
        const lifetimeRows = data?.lifetime.campaigns ?? [];
        const selectedRows = data?.selected.campaigns ?? [];
        const rows = new Map<string, { id: string; name: string; lifetime?: ReportRowData; selected?: ReportRowData }>();

        lifetimeRows.forEach((row) => {
            const id = String(row.group_key);
            rows.set(id, { id, name: row.group_name, lifetime: row });
        });
        selectedRows.forEach((row) => {
            const id = String(row.group_key);
            const existing = rows.get(id);
            rows.set(id, { id, name: existing?.name ?? row.group_name, ...existing, selected: row });
        });

        return [...rows.values()].sort(
            (left, right) =>
                (right.selected?.profit ?? right.lifetime?.profit ?? 0) -
                (left.selected?.profit ?? left.lifetime?.profit ?? 0),
        );
    }, [data]);

    return (
        <>
            <Head title="Inspect offers" />

            <main className="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">Offer analytics</p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight">Inspect</h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Follow performance over time, spot your strongest days, and see how quickly commissions move through each status.
                        </p>
                    </div>

                    <div className="grid w-full gap-3 sm:grid-cols-2 lg:max-w-2xl">
                        <label className="grid gap-1.5 text-sm font-medium">
                            Offer
                            <SearchableSelect
                                field="inspect-offer"
                                options={offerOptions}
                                value={offerId}
                                placeholder="Type to search offers"
                                disabled={offers.length === 0}
                                onChange={setOfferId}
                            />
                        </label>
                        <div className="grid gap-1.5 text-sm font-medium sm:col-span-2">
                            <span>Selected period</span>
                            <div className="flex flex-wrap items-center gap-3">
                                <DateRangePicker
                                    value={dateRange}
                                    onChange={(next) => setDateRange(next)}
                                    quickOptions={defaultQuickOptions}
                                    timezone={timezone}
                                />
                                <span className="text-xs font-normal text-muted-foreground">
                                    The comparison also includes Lifetime, This year, and Last 90 days.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {selectedOffer ? (
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border bg-muted/30 px-4 py-3 text-sm">
                        <span className="font-semibold">{selectedOffer.name}</span>
                        <span className="text-muted-foreground">{selectedOffer.country}</span>
                        {selectedOffer.blog ? <span className="text-muted-foreground">{selectedOffer.blog}</span> : null}
                        {selectedOffer.affiliateNetwork ? (
                            <span className="text-muted-foreground">{selectedOffer.affiliateNetwork}</span>
                        ) : null}
                    </div>
                ) : null}

                {loadState === 'error' ? (
                    <div role="alert" className="rounded-lg border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {errorMessage}
                    </div>
                ) : null}
                {loadState === 'idle' && offerId && !isDateRangeComplete ? (
                    <div role="status" className="rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                        Choose an end date to load the selected-period comparison.
                    </div>
                ) : null}

                {offers.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <h2 className="font-semibold">No offers to inspect</h2>
                            <p className="mt-2 text-sm text-muted-foreground">Offers available to your account will appear here.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <section className="grid gap-5 xl:grid-cols-2" aria-label="Offer performance comparisons">
                            {PERIODS.map(({ key, title }) => {
                                const period = data?.[key];

                                return (
                                    <MetricPanel
                                        key={key}
                                        title={key === 'selected' ? selectedLabel : title}
                                        description={period ? periodDateRange(period) : key === 'selected' ? 'Selected date range' : title}
                                        period={period}
                                        isLoading={loadState === 'loading'}
                                    />
                                );
                            })}
                        </section>

                        <Card>
                            <CardHeader>
                                <CardTitle>Campaign performance</CardTitle>
                                <CardDescription>
                                    Lifetime compared with {selectedLabel.toLowerCase()} ({selectedPeriod ? periodDateRange(selectedPeriod) : `${dateFrom} – ${dateTo}`}).
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {loadState === 'loading' ? (
                                    <div className="space-y-3 py-4" aria-label="Loading campaign performance">
                                        {[0, 1, 2].map((item) => (
                                            <div key={item} className="h-8 animate-pulse rounded bg-muted" />
                                        ))}
                                    </div>
                                ) : campaignRows.length > 0 ? (
                                    <div className="overflow-x-auto">
                                        <table className="w-full min-w-[1400px] text-left text-sm">
                                            <thead>
                                                <tr className="border-b text-xs uppercase tracking-wide text-muted-foreground">
                                                    <th className="py-3 pr-4 font-medium">Campaign</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime clicks</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime conv.</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime CR</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime spend</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime revenue</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime profit</th>
                                                    <th className="px-3 py-3 text-right font-medium">Lifetime ROI</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected clicks</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected conv.</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected CR</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected spend</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected revenue</th>
                                                    <th className="px-3 py-3 text-right font-medium">Selected ROI</th>
                                                    <th className="py-3 pl-3 text-right font-medium">Selected profit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {campaignRows.map((row) => (
                                                    <tr key={row.id} className="border-b last:border-0">
                                                        <td className="py-3 pr-4 font-medium">{row.name}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('clicks', row.lifetime?.clicks)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('total_conversions', row.lifetime?.total_conversions)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('cr', row.lifetime?.cr)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('spent', row.lifetime?.spent)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('revenue', row.lifetime?.revenue)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('profit', row.lifetime?.profit)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('roi', row.lifetime?.roi)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('clicks', row.selected?.clicks)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('total_conversions', row.selected?.total_conversions)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('cr', row.selected?.cr)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('spent', row.selected?.spent)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('revenue', row.selected?.revenue)}</td>
                                                        <td className="px-3 py-3 text-right">{formatCell('roi', row.selected?.roi)}</td>
                                                        <td className="py-3 pl-3 text-right">{formatCell('profit', row.selected?.profit)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : loadState === 'loaded' ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        No campaign activity is recorded for this offer yet.
                                    </p>
                                ) : loadState === 'error' ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground">Campaign performance could not be loaded.</p>
                                ) : null}
                            </CardContent>
                        </Card>
                    </>
                )}
            </main>
        </>
    );
}

function MetricPanel({
    title,
    description,
    period,
    isLoading,
}: {
    title: string;
    description: string;
    period?: InspectionPeriod;
    isLoading: boolean;
}) {
    const totals = period?.totals;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {METRICS.map((metric) => {
                        const Icon = metric.icon;

                        return (
                            <div key={metric.key} className="rounded-lg border bg-card px-3 py-3">
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Icon className="size-3.5" aria-hidden="true" />
                                    {metric.label}
                                </div>
                                <div className="mt-2 min-h-6 text-lg font-semibold tabular-nums">
                                    {isLoading ? (
                                        <span className="inline-block h-5 w-16 animate-pulse rounded bg-muted" />
                                    ) : formatCell(metric.key, totals ? metricValue(totals, metric.key) : null)}
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 border-t pt-4 text-sm">
                    {(['open', 'confirmed', 'rejected', 'paid'] as const).map((status) => (
                        <div key={status} className="text-muted-foreground">
                            <span className="capitalize">{status}</span>
                            <span className="ml-2 font-medium text-foreground">
                                {isLoading ? '…' : formatCell(`${status}_conversions`, totals ? metricValue(totals, `${status}_conversions`) : null)}
                            </span>
                            <span className="ml-1">
                                ({isLoading ? '…' : formatCell(`${status}_conversions_sum`, totals ? metricValue(totals, `${status}_conversions_sum`) : null)})
                            </span>
                        </div>
                    ))}
                </div>

                <div className="mt-5 border-t pt-5">
                    <h3 className="mb-3 font-semibold">Performance over time</h3>
                    {period && period.insights.days.length > 0 ? (
                        <div className="h-72 w-full" aria-label={`${title} offer performance chart`}>
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={period.insights.days} margin={{ top: 8, right: 24, left: 4, bottom: 4 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
                                    <XAxis
                                        dataKey="date"
                                        tickFormatter={(date: string) =>
                                            new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(
                                                new Date(`${date}T00:00:00Z`),
                                            )
                                        }
                                        minTickGap={28}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                        axisLine={{ stroke: 'var(--border)' }}
                                        tickLine={{ stroke: 'var(--border)' }}
                                    />
                                    <YAxis
                                        yAxisId="clicks"
                                        allowDecimals={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{
                                            value: 'Clicks',
                                            angle: -90,
                                            position: 'insideLeft',
                                            fill: 'var(--muted-foreground)',
                                            fontSize: 11,
                                        }}
                                    />
                                    <YAxis
                                        yAxisId="conversions"
                                        orientation="right"
                                        allowDecimals={false}
                                        domain={[0, 'auto']}
                                        tick={{ fill: 'var(--chart-2)', fontSize: 11 }}
                                        axisLine={false}
                                        tickLine={false}
                                        label={{
                                            value: 'Conversions',
                                            angle: 90,
                                            position: 'insideRight',
                                            fill: 'var(--chart-2)',
                                            fontSize: 11,
                                        }}
                                    />
                                    <Tooltip content={<PerformanceTooltip />} />
                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                    <Line
                                        type="monotone"
                                        dataKey="clicks"
                                        name="Clicks"
                                        stroke="var(--chart-1)"
                                        yAxisId="clicks"
                                        strokeWidth={2}
                                        dot={false}
                                        activeDot={{ r: 4 }}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="conversions"
                                        name="Conversions"
                                        stroke="var(--chart-2)"
                                        yAxisId="conversions"
                                        strokeWidth={2}
                                        dot={false}
                                        activeDot={{ r: 4 }}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    ) : isLoading ? (
                        <div className="h-72 animate-pulse rounded-lg bg-muted" aria-label="Loading performance chart" />
                    ) : (
                        <p className="py-16 text-center text-sm text-muted-foreground">No daily activity for this period yet.</p>
                    )}
                </div>

                <PeriodHighlights title={title} period={period} isLoading={isLoading} />
            </CardContent>
        </Card>
    );
}

function PerformanceTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: Array<{ payload?: DailyMetric }>;
}) {
    const day = payload?.[0]?.payload;

    if (!active || !day) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md">
            <p className="mb-2 font-semibold">
                {new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${day.date}T00:00:00Z`))}
            </p>
            <p>Clicks: {day.clicks.toLocaleString()}</p>
            <p>Conversions: {day.conversions.toLocaleString()}</p>
            <p>Revenue: {formatCell('revenue', day.revenue)}</p>
            <p>CR: {formatCell('cr', day.cr)}</p>
        </div>
    );
}

function PeriodHighlights({
    title,
    period,
    isLoading,
}: {
    title: string;
    period?: InspectionPeriod;
    isLoading: boolean;
}) {
    const bestDay = period?.insights.days.reduce<(PeriodInsights['days'][number] | null)>(
        (best, day) => (!best || day.profit > best.profit ? day : best),
        null,
    );
    const weekdayProfits = new Map<string, number>();
    period?.insights.days.forEach((day) => {
        const weekday = weekdayFor(day.date);
        weekdayProfits.set(weekday, (weekdayProfits.get(weekday) ?? 0) + day.profit);
    });
    const bestWeekday = [...weekdayProfits.entries()].sort((left, right) => right[1] - left[1])[0];
    const insights = period?.insights;

    return (
        <div className="mt-5 border-t pt-5">
            <div className="mb-3">
                <h3 className="flex items-center gap-2 font-semibold">
                    <Sparkles className="size-4 text-primary" aria-hidden="true" />
                    {title} highlights
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Milestones and conversion quality · {period ? periodDateRange(period) : title}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                    <Highlight
                        icon={CalendarDays}
                        label="Best day by profit"
                        value={isLoading ? '…' : bestDay ? `${bestDay.date} · ${formatCell('profit', bestDay.profit)}` : 'No activity yet'}
                        detail={bestDay ? `${bestDay.clicks.toLocaleString()} clicks · ${bestDay.conversions.toLocaleString()} conversions` : undefined}
                    />
                    <Highlight
                        icon={Activity}
                        label="Strongest weekday"
                        value={isLoading ? '…' : bestWeekday ? `${bestWeekday[0]} · ${formatCell('profit', bestWeekday[1])}` : 'No activity yet'}
                    />
                    <Highlight
                        icon={Target}
                        label="Confirmed / resolved rate"
                        value={isLoading ? '…' : period && approvalRate(period.totals) !== null ? `${approvalRate(period.totals)?.toFixed(1)}%` : 'Not enough data'}
                    />
                    <Highlight
                        icon={Clock3}
                        label="Open → confirmed"
                        value={
                            isLoading
                                ? '…'
                                : formatTransition(
                                      insights?.transitionAverages.openToConfirmedMinutes ?? null,
                                      insights?.transitionAverages.samples.openToConfirmed ?? 0,
                                  )
                        }
                    />
                    <Highlight
                        icon={Clock3}
                        label="Open → rejected"
                        value={
                            isLoading
                                ? '…'
                                : formatTransition(
                                      insights?.transitionAverages.openToRejectedMinutes ?? null,
                                      insights?.transitionAverages.samples.openToRejected ?? 0,
                                  )
                        }
                    />
                    <Highlight
                        icon={Clock3}
                        label="Confirmed → paid"
                        value={
                            isLoading
                                ? '…'
                                : formatTransition(
                                      insights?.transitionAverages.confirmedToPaidMinutes ?? null,
                                      insights?.transitionAverages.samples.confirmedToPaid ?? 0,
                                  )
                        }
                    />
                    <Highlight
                        icon={ArrowDownRight}
                        label="Lowest positive commission"
                        value={isLoading ? '…' : formatCommission(insights?.lowestCommission ?? null)}
                        detail={
                            insights?.lowestCommission
                                ? `${insights.lowestCommission.affiliate ?? 'Unknown network'} · ${insights.lowestCommission.status}`
                                : undefined
                        }
                    />
                    <Highlight
                        icon={ArrowUpRight}
                        label="Highest positive commission"
                        value={isLoading ? '…' : formatCommission(insights?.highestCommission ?? null)}
                        detail={
                            insights?.highestCommission
                                ? `${insights.highestCommission.affiliate ?? 'Unknown network'} · ${insights.highestCommission.status}`
                                : undefined
                        }
                    />
            </div>
        </div>
    );
}

function Highlight({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: typeof Activity;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="min-w-0 rounded-lg border bg-card px-3 py-3">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <Icon className="size-3.5 shrink-0" aria-hidden="true" />
                {label}
            </div>
            <div className="mt-2 truncate text-sm font-semibold tabular-nums" title={value}>
                {value}
            </div>
            {detail ? <div className="mt-1 truncate text-xs text-muted-foreground">{detail}</div> : null}
        </div>
    );
}
