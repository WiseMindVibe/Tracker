// resources/js/lib/metrics.ts
// Derived metrics shared by the summary cards and the tables, so they always agree.
// Ratios return null when the denominator is zero (shown as "—").

export interface Raw {
    clicks: number;
    conversions: number;
    revenue: number;
    cost: number;
    /** only needed for CPM; null/undefined = not available */
    impressions?: number | null;
}

export type Totals = Pick<Raw, "clicks" | "conversions" | "revenue" | "cost">;

export const profit = (r: Raw) => r.revenue - r.cost;
export const cr = (r: Raw) => (r.clicks ? (r.conversions / r.clicks) * 100 : null);
export const roi = (r: Raw) => (r.cost > 0 ? (profit(r) / r.cost) : null);
export const margin = (r: Raw) => (r.revenue > 0 ? (profit(r) / r.revenue) * 100 : null);
export const cpm = (r: Raw) => (r.clicks ? (r.cost / r.clicks) * 1000 : null); // in the future change this to impressions
export const epc = (r: Raw) => (r.clicks ? (r.revenue / r.clicks) * 1000: null);
export const aov = (r: Raw) => (r.conversions ? (r.revenue / r.conversions): null);