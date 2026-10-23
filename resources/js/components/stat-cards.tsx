// resources/js/components/stat-cards.tsx
import { useMemo, useRef } from "react";
import AnimatedNumber from "@/components/animated-number";
import { cr, epc, margin, profit, roi, type Totals } from "@/lib/metrics";

interface Props {
    /** undefined while the deferred request is in flight */
    totals?: Totals;
    currency?: string;
    className?: string;
}

type Kind = "int" | "float" | "money" | "pct" | "x";

interface CardDef {
    key: string;
    label: string;
    kind: Kind;
    value: (t: Totals) => number | null;
    /** colour the number green / red by sign */
    signed?: boolean;
}

const CARDS: CardDef[] = [
    { key: "clicks", label: "Clicks", kind: "int", value: (t) => t.clicks },
    { key: "conversions", label: "Conversions", kind: "int", value: (t) => t.conversions },
    { key: "cr", label: "CR", kind: "pct", value: cr },
    { key: "roi", label: "ROI", kind: "x", value: roi, signed: true },
    { key: "cost", label: "Cost", kind: "money", value: (t) => t.cost },
    { key: "revenue", label: "Revenue", kind: "money", value: (t) => t.revenue },
    { key: "profit", label: "Profit", kind: "money", value: profit, signed: true },
    { key: "epc", label: "EPC * 1000", kind: "float", value: epc, signed: true },
    //{ key: "margin", label: "Profit margin", kind: "pct", value: margin, signed: true },
];

const grid = "grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4";
const card = "min-w-0 rounded-xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4 dark:border-neutral-700 dark:bg-neutral-900";

export default function StatCards({ totals, currency = "EUR", className = "" }: Props) {
    // Keep showing the previous numbers (dimmed) while new ones load, so they can count to the new value.
    const last = useRef<Totals | null>(null);
    if (totals) last.current = totals;
    const shown = totals ?? last.current;
    const loading = !totals;

    const formats = useMemo<Record<Kind, (n: number) => string>>(() => {
        const int = new Intl.NumberFormat("en-US", { maximumFractionDigits: 0 });
        const float = new Intl.NumberFormat("en-US", { maximumFractionDigits: 2, style: "currency", currency });
        const money = new Intl.NumberFormat("en-US", { style: "currency", currency });
        return { int: int.format, money: money.format, float: float.format,
             x: (n) => `${n.toFixed(2)}x`, pct: (n) => `${n.toFixed(2)}%` };
    }, [currency]);

    if (!shown) return <StatCardsSkeleton className={className} />;

    return (
        <div className={`${grid} ${loading ? "opacity-60 transition-opacity" : "transition-opacity"} ${className}`}>
            {CARDS.map((c) => {
                const v = c.value(shown);
                const tone = !c.signed || v === null || v === 0 ? "" : v > 0 ? "text-green-700 dark:text-green-400" : "text-red-600 dark:text-red-400";
                return (
                    <div key={c.key} className={card}>
                        <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{c.label}</div>
                        <div className={`mt-2 break-words text-xl font-semibold tabular-nums sm:text-3xl ${tone}`}>
                            {v === null ? "—" : <AnimatedNumber value={v} format={formats[c.kind]} />}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export function StatCardsSkeleton({ className = "" }: { className?: string }) {
    return (
        <div role="status" aria-label="Loading totals" className={`${grid} ${className}`}>
            {CARDS.map((c) => (
                <div key={c.key} className={card}>
                    <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{c.label}</div>
                    <div className="mt-3 h-7 w-24 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" />
                </div>
            ))}
        </div>
    );
}