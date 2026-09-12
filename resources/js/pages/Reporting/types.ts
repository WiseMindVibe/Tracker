export type ParentFilters = Record<string, number | string>;

export interface ReportRowData {
    group_name: string;
    group_key: number | string;
    group_field: string;
    parent_filters: ParentFilters;
    can_expand: boolean;
    clicks: number;
    total_conversions: number;
    open_conversions: number;
    open_conversions_sum: number;
    confirmed_conversions: number;
    confirmed_conversions_sum: number;
    rejected_conversions: number;
    rejected_conversions_sum: number;
    paid_conversions: number;
    paid_conversions_sum: number;
    delayed_conversions: number;
    delayed_conversions_sum: number;
    revenue: number;
    spent: number;
    profit: number;
    cr: number;
    roi: number | null;
    avg_payout: number | null;
    [key: string]: unknown;
}

export interface ReportRowState extends ReportRowData {
    expanded: boolean;
    loading: boolean;
    children: ReportRowState[] | null;
}

export interface ReportTotals {
    [key: string]: number | null;
}

export interface ReportApiResponse {
    date_from: string;
    date_to: string;
    date_preset: string;
    group_by: string[];
    level: number;
    sort_by: string;
    sort_dir: 'asc' | 'desc';
    rows: ReportRowData[];
    totals: ReportTotals | null;
    next_level: boolean;
}

export interface AppliedContext {
    date_preset: string;
    date_from: string;
    date_to: string;
    group_by: string[];
}

export interface ReportingIndexProps {
    defaultDatePreset: string;
    defaultDateFrom: string;
    defaultDateTo: string;
    defaultGroupBy: string[];
    availableGroups: Record<string, string>;
    apiEndpoint: string;
}

export type MetricColumn = [key: string, label: string];
