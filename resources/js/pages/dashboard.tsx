import { Deferred, Head, router } from "@inertiajs/react";
import { useState } from "react";
import CurrentTime from "@/components/current-time";
import DateRangePicker from "@/components/date-range/date-range-picker";
import PerformanceTable, {
    AFFILIATE_METRICS,
    TRAFFIC_METRICS,
    PerformanceTableSkeleton,
    type PerformanceRow,
} from "@/components/performance-table";
import StatCards from "@/components/stat-cards";
import { fromYmd, toQuery, type DateRangeValue } from "@/lib/date-range";
import type { Totals } from "@/lib/metrics";

interface Props {
    currentTime: string;
    timezone: string;
    /** Resolved by DashboardController: "This month" unless custom dates / another preset were requested. */
    range: {
        start: string | null;
        end: string | null;
        preset: string;
        includeToday: boolean;
    };
    /** Deferred: undefined until the second request finishes. */
    totals?: Totals;
    affiliates?: PerformanceRow[];
    trafficSources?: PerformanceRow[];
}

export default function Dashboard({ currentTime, timezone, range: initialRange, totals, affiliates, trafficSources }: Props) {
    const [range, setRange] = useState<DateRangeValue>(() => ({
        start: initialRange.start ? fromYmd(initialRange.start) : null,
        end: initialRange.end ? fromYmd(initialRange.end) : null,
        preset: initialRange.preset,
        includeToday: initialRange.includeToday,
    }));

    function handleRangeChange(next: DateRangeValue) {
        setRange(next);

        // Only reload once the range is complete (2nd click, quick option, or "All time").
        const complete = !!next.end || next.preset === "all-time";
        if (!complete) return;

        router.get(window.location.pathname, toQuery(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Dashboard" />
            <h1 className="px-4 pt-4 text-center text-3xl font-bold sm:text-4xl">Dashboard</h1>
            <hr className="my-4 border-grey-200" />

            <div className="flex min-w-0 flex-col gap-4 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <CurrentTime currentTime={currentTime} timezone={timezone} />
                <div className="w-full sm:w-auto">
                    <DateRangePicker value={range} onChange={handleRangeChange} timezone={timezone} />
                </div>
            </div>

            <section className="min-w-0 px-4 pt-6 sm:px-6 lg:px-8">
                <StatCards totals={totals} />
            </section>

            <section className="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
                <h2 className="mb-3 text-xl font-semibold">Affiliates</h2>
                <Deferred data="affiliates" fallback={<PerformanceTableSkeleton metrics={AFFILIATE_METRICS} nameLabel="Affiliate" />}>
                    <PerformanceTable rows={affiliates ?? []} metrics={AFFILIATE_METRICS} nameLabel="Affiliate" />
                </Deferred>
            </section>
            <section className="min-w-0 px-4 pb-8 sm:px-6 lg:px-8">
                <h2 className="mb-3 text-xl font-semibold">Traffic sources</h2>
                <Deferred data="trafficSources" fallback={<PerformanceTableSkeleton metrics={TRAFFIC_METRICS} nameLabel="Traffic source" />}>
                    <PerformanceTable rows={trafficSources ?? []} metrics={TRAFFIC_METRICS} nameLabel="Traffic source" />
                </Deferred>
            </section>
        </>
    );
}