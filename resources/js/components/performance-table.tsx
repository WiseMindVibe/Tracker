// resources/js/components/performance-table.tsx
// One table for any "rows with clicks / conversions / revenue / cost" breakdown
// (affiliates, traffic sources, ...). Pick the columns with the `metrics` prop.
import { useMemo, useState } from "react";
import { cpm, epc, aov,  cr, margin, profit, roi, type Raw } from "@/lib/metrics";

export interface PerformanceRow {
    id: number | string;
    name: string;
    status: string;
    clicks: number;
    conversions: number;
    revenue: number;
    cost: number;
    /** only needed for the CPM column; null/undefined = not available */
    impressions?: number | null;
}

export type MetricKey = "clicks" | "conversions" | "revenue" | "cost" | "cpm" | "cr" | "roi" | "profit" | "margin" | "conversions_by_status" | "aov" | "epc" | "approve_rate" ;

export const AFFILIATE_METRICS: MetricKey[] = [
    "clicks",
    "conversions",
    "cr",
    "roi",
    "cost",
    "revenue",
    "profit",
    "margin",
    'epc',
    'aov',
    //'conversions_by_status',
    //'approve_rate',

];
export const TRAFFIC_METRICS: MetricKey[] = [
    "clicks",
    "conversions",
    "cr",
    "roi",
    "cost",
    "revenue",
    "profit",
    "margin",
    "epc",
    "cpm",
];

const LABELS: Record<MetricKey, string> = {
    clicks: "Clicks",
    conversions: "Conversions",
    revenue: "Revenue",
    cost: "Cost",
    cpm: "CPM",
    cr: "CR",
    roi: "ROI",
    profit: "Profit",
    margin: "Profit margin",
    epc: "EPC * 1000",
    aov: "AOV",
    //conversions_by_status: '-',
    //approve_rate: "Approval Rate"
};

const HINTS: Partial<Record<MetricKey, string>> = {
    cpm: "Cost per 1,000 impressions. Impressions aren't tracked per day, so CPM is only available for All time.",
    epc: "Earning per 1,000 impressions. Impressions aren't tracked per day, so CPM is only available for All time.",
};

const metricValue: Record<MetricKey, (r: Raw) => number | null> = {
    clicks: (r) => r.clicks,
    conversions: (r) => r.conversions,
    revenue: (r) => r.revenue,
    cost: (r) => r.cost,
    cpm,
    cr,
    roi,
    profit,
    margin,
    epc,
    aov,
    //conversions_by_status,
    //approve_rate,
};

type SortKey = MetricKey | "name";

interface Props {
    rows: PerformanceRow[];
    metrics: MetricKey[];
    nameLabel?: string;
    currency?: string;
    className?: string;
}

export default function PerformanceTable({ rows, metrics, nameLabel = "Name", currency = "EUR", className = "" }: Props) {
    const [sort, setSort] = useState<{ key: SortKey; dir: "asc" | "desc" }>({ key: "clicks", dir: "desc" });

    const int = useMemo(() => new Intl.NumberFormat("en-US"), []);
    const money = useMemo(() => new Intl.NumberFormat("en-US", { style: "currency", currency }), [currency]);
    const floatmoney = useMemo(() => new Intl.NumberFormat("en-US", { maximumFractionDigits: 2, style: "currency", currency }), [currency]);
    const pct = (n: number | null) => (n === null ? "—" : `${n.toFixed(2)}%`);
    const x = (n: number | null) => (n === null ? "-" : `${n.toFixed(2)}x`);

    const totals = useMemo<Raw>(() => {
        const t = rows.reduce(
            (a, r) => ({
                clicks: a.clicks + r.clicks,
                conversions: a.conversions + r.conversions,
                revenue: a.revenue + r.revenue,
                cost: a.cost + r.cost,
                impressions: a.impressions + (r.impressions ?? 0),
            }),
            { clicks: 0, conversions: 0, revenue: 0, cost: 0, impressions: 0 },
        );
        // If any row has no impressions data, the total CPM would be wrong too.
        return { ...t, impressions: rows.length && rows.every((r) => r.impressions != null) ? t.impressions : null };
    }, [rows]);

    const sorted = useMemo(() => {
        const mult = sort.dir === "asc" ? 1 : -1;
        const value = (r: PerformanceRow): string | number =>
            sort.key === "name" ? r.name.toLowerCase() : (metricValue[sort.key](r) ?? -Infinity);
        return [...rows].sort((a, b) => {
            const x = value(a);
            const y = value(b);
            return (x < y ? -1 : x > y ? 1 : 0) * mult;
        });
    }, [rows, sort]);

    function toggleSort(key: SortKey) {
        setSort((s) => (s.key === key ? { key, dir: s.dir === "asc" ? "desc" : "asc" } : { key, dir: key === "name" ? "asc" : "desc" }));
    }

    const th = "px-4 py-2.5 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-neutral-500 dark:text-neutral-400";
    const td = "px-4 py-2.5 text-right tabular-nums whitespace-nowrap";
    const tone = (n: number | null) =>
        n === null || n === 0 ? "" : n > 0 ? "text-green-700 dark:text-green-400" : "text-red-600 dark:text-red-400";

    function cell(key: MetricKey, r: Raw) {
        const v = metricValue[key](r);
        switch (key) {
            case "clicks":
            case "conversions":
                return <td key={key} className={td}>{int.format(r[key])}</td>;
            case "revenue":
            case "cost":
                return <td key={key} className={td}>{money.format(r[key])}</td>;
            case "cpm":
                return <td key={key} className={td}>{v === null ? "—" : money.format(v)}</td>;
            case "epc":
                return <td key={key} className={td}>{v === null ? "—" : floatmoney.format(v)}</td>;
            case "aov":
                return <td key={key} className={td}>{v === null ? "—" : money.format(v)}</td>;
            case "cr":
                return <td key={key} className={td}>{pct(v)}</td>;
            case "profit":
                return <td key={key} className={`${td} font-medium ${tone(v)}`}>{money.format(v ?? 0)}</td>;
            case "roi":
                return <td key={key} className={`${td} ${tone(v)}`}>{x(v)}</td>;
            default: // , margin
                return <td key={key} className={`${td} ${tone(v)}`}>{pct(v)}</td>;
        }
    }

    const sortable: { key: SortKey; label: string }[] = [{ key: "name", label: nameLabel }, ...metrics.map((m) => ({ key: m, label: LABELS[m] }))];

    return (
        <div className={`max-w-full overflow-x-auto touch-pan-x rounded-lg border border-neutral-200 dark:border-neutral-700 ${className}`}>
            <table className="min-w-max border-collapse text-sm sm:min-w-full">
                <thead className="bg-neutral-50 dark:bg-neutral-800/60">
                    <tr>
                        {sortable.map((c) => {
                            const active = sort.key === c.key;
                            return (
                                <th
                                    key={c.key}
                                    scope="col"
                                    title={c.key !== "name" ? HINTS[c.key] : undefined}
                                    aria-sort={active ? (sort.dir === "asc" ? "ascending" : "descending") : "none"}
                                    className={`${th} ${c.key === "name" ? "text-left" : "text-right"}`}
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleSort(c.key)}
                                        className="inline-flex items-center gap-1 uppercase hover:text-neutral-900 dark:hover:text-neutral-100"
                                    >
                                        {c.label}
                                        <span aria-hidden className={active ? "" : "opacity-0"}>
                                            {sort.dir === "asc" ? "▲" : "▼"}
                                        </span>
                                    </button>
                                </th>
                            );
                        })}
                    </tr>
                </thead>

                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                    {sorted.length === 0 && (
                        <tr>
                            <td colSpan={sortable.length} className="px-4 py-8 text-center text-neutral-500">
                                Nothing to show.
                            </td>
                        </tr>
                    )}
                    {sorted.map((r) => (
                        <tr key={r.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/40">
                            <td className="px-4 py-2.5 font-medium whitespace-nowrap">
                                {r.name}
                                {r.status !== "active" && (
                                    <span className="ml-2 rounded bg-neutral-100 px-1.5 py-0.5 text-xs font-normal text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {r.status}
                                    </span>
                                )}
                            </td>
                            {metrics.map((m) => cell(m, r))}
                        </tr>
                    ))}
                </tbody>

                {sorted.length > 0 && (
                    <tfoot className="border-t-2 border-neutral-200 bg-neutral-50 font-semibold dark:border-neutral-700 dark:bg-neutral-800/60">
                        <tr>
                            <td className="px-4 py-2.5">Total ({rows.length})</td>
                            {metrics.map((m) => cell(m, totals))}
                        </tr>
                    </tfoot>
                )}
            </table>
        </div>
    );
}

/** Placeholder shown while deferred data loads. */
export function PerformanceTableSkeleton({
    metrics,
    nameLabel = "Name",
    rows = 5,
    className = "",
}: {
    metrics: MetricKey[];
    nameLabel?: string;
    rows?: number;
    className?: string;
}) {
    const cols: { key: string; label: string }[] = [{ key: "name", label: nameLabel }, ...metrics.map((m) => ({ key: m, label: LABELS[m] }))];
    return (
        <div role="status" aria-label="Loading data" className={`max-w-full overflow-x-auto touch-pan-x rounded-lg border border-neutral-200 dark:border-neutral-700 ${className}`}>
            <table className="min-w-max border-collapse text-sm sm:min-w-full">
                <thead className="bg-neutral-50 dark:bg-neutral-800/60">
                    <tr>
                        {cols.map((c) => (
                            <th
                                key={c.key}
                                className={`px-4 py-2.5 text-xs font-semibold uppercase tracking-wide whitespace-nowrap text-neutral-500 dark:text-neutral-400 ${c.key === "name" ? "text-left" : "text-right"}`}
                            >
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                    {Array.from({ length: rows }, (_, i) => (
                        <tr key={i}>
                            {cols.map((c) => (
                                <td key={c.key} className="px-4 py-3">
                                    <div className={`h-3 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700 ${c.key === "name" ? "w-28" : "ml-auto w-14"}`} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}