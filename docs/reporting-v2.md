# Reporting V2

Reporting v2 is a lazy, hierarchical analytics API designed for large click volumes.

## Endpoint

- `POST /api/report/v2/index.php`
- JSON request body
- JSON response body
- Uses existing `db()` PDO bootstrap and admin access gate

## Filters

Exactly three filter groups are supported:

1. Date filter
   - Presets: `today`, `yesterday`, `last7`, `this_week`
   - Custom range: `date_from` + `date_to` in `YYYY-MM-DD`
   - Date filter always applies to `clicks.created_at`
2. Grouping selection (`group_by`)
   - Allowed values: `offer`, `campaign`, `os`, `browser`
   - Default: `["offer", "campaign"]`
3. Grouping hierarchy order
   - The `group_by` array order defines drill-down order

## Request Contract

```json
{
  "date_from": "2026-04-01",
  "date_to": "2026-04-06",
  "group_by": ["offer", "campaign", "os", "browser"],
  "level": 0,
  "parent_filters": {},
  "sort_by": "clicks",
  "sort_dir": "desc",
  "include_totals": true
}
```

Notes:
- `level` is `0`-based.
- For `level = 0`, `parent_filters` must be empty.
- For deeper levels, `parent_filters` must match all parent levels in hierarchy:
  - `offer -> campaign -> os -> browser`
  - Level 1 requires: `{"offer_id": ...}`
  - Level 2 requires: `{"offer_id": ..., "campaign_id": ...}`
  - Level 3 requires: `{"offer_id": ..., "campaign_id": ..., "os": ...}`
- `sort_by` supports all metric keys plus `group_name`.
- `sort_dir` supports `asc` or `desc`.
- `include_totals` should be `true` for top-level loads and `false` for child drill-down requests.

## Response Contract

```json
{
  "date_from": "2026-04-01",
  "date_to": "2026-04-06",
  "date_preset": "custom",
  "group_by": ["offer", "campaign", "os", "browser"],
  "level": 0,
  "rows": [
    {
      "group_name": "Offer A",
      "group_key": 123,
      "group_field": "offer_id",
      "parent_filters": { "offer_id": 123 },
      "can_expand": true,
      "clicks": 1250,
      "total_conversions": 97,
      "open_conversions": 34,
      "open_conversions_sum": 215.5,
      "confirmed_conversions": 28,
      "confirmed_conversions_sum": 185.0,
      "rejected_conversions": 10,
      "rejected_conversions_sum": 64.0,
      "paid_conversions": 25,
      "paid_conversions_sum": 170.0,
      "revenue": 570.5,
      "spent": 312.6,
      "profit": 257.9,
      "cr": 7.76,
      "roi": 82.5,
      "avg_payout": 6.54
    }
  ],
  "totals": {
    "clicks": 9412,
    "total_conversions": 600,
    "open_conversions": 210,
    "open_conversions_sum": 1250.4,
    "confirmed_conversions": 175,
    "confirmed_conversions_sum": 1131.2,
    "rejected_conversions": 75,
    "rejected_conversions_sum": 460.0,
    "paid_conversions": 140,
    "paid_conversions_sum": 930.1,
    "revenue": 3311.7,
    "spent": 2014.2,
    "profit": 1297.5,
    "cr": 6.95,
    "roi": 64.4,
    "avg_payout": 6.31
  },
  "next_level": true
}
```

## Drill-Down Flow

1. Apply filters, request `level = 0`.
2. User expands a row.
3. Frontend sends the next `level` and row `parent_filters`.
4. Backend returns only that next level.
5. Repeat until `next_level = false`.

No deeper levels are preloaded.
Child requests should set `include_totals=false` so totals queries are skipped for faster expansion.

## SQL Strategy

All queries are aggregate-only and scoped by date.

### Level clicks + spent query (example: group by offer)

```sql
SELECT
    COALESCE(o.id, 0) AS group_key,
    COALESCE(MAX(o.name), '(no offer)') AS group_name,
    COUNT(c.id) AS clicks,
    COALESCE(SUM(c.cost), 0) AS spent
FROM clicks c
LEFT JOIN offers o ON o.id = c.offer_id
WHERE c.created_at >= :date_from
  AND c.created_at <= :date_to
GROUP BY COALESCE(o.id, 0)
ORDER BY clicks DESC;
```

### Level conversions split query (conditional aggregation)

```sql
SELECT
    COALESCE(o.id, 0) AS group_key,
    COALESCE(SUM(CASE WHEN cv.status IN (1,2,3,4) THEN 1 ELSE 0 END), 0) AS total_conversions,
    COALESCE(SUM(CASE WHEN cv.status = 1 THEN 1 ELSE 0 END), 0) AS open_conversions,
    COALESCE(SUM(CASE WHEN cv.status = 1 THEN cv.revenue ELSE 0 END), 0) AS open_conversions_sum,
    COALESCE(SUM(CASE WHEN cv.status = 2 THEN 1 ELSE 0 END), 0) AS confirmed_conversions,
    COALESCE(SUM(CASE WHEN cv.status = 2 THEN cv.revenue ELSE 0 END), 0) AS confirmed_conversions_sum,
    COALESCE(SUM(CASE WHEN cv.status = 3 THEN 1 ELSE 0 END), 0) AS rejected_conversions,
    COALESCE(SUM(CASE WHEN cv.status = 3 THEN cv.revenue ELSE 0 END), 0) AS rejected_conversions_sum,
    COALESCE(SUM(CASE WHEN cv.status = 4 THEN 1 ELSE 0 END), 0) AS paid_conversions,
    COALESCE(SUM(CASE WHEN cv.status = 4 THEN cv.revenue ELSE 0 END), 0) AS paid_conversions_sum
FROM clicks c
LEFT JOIN offers o ON o.id = c.offer_id
LEFT JOIN conversions cv ON cv.click_id = c.id AND cv.status IN (1,2,3,4)
WHERE c.created_at >= :date_from
  AND c.created_at <= :date_to
GROUP BY COALESCE(o.id, 0);
```

### Child level query example

If hierarchy is `offer -> campaign -> os` and user expands offer `123`:

```json
{
  "group_by": ["offer", "campaign", "os"],
  "level": 1,
  "parent_filters": { "offer_id": 123 },
  "date_preset": "today"
}
```

Only campaigns for `offer_id = 123` are returned.

## Added Indexes

Migration: `src/migrations/files/007.0_reporting_hierarchy_indexes.php`

- `clicks(created_at, offer_id)`
- `clicks(offer_id, created_at, campaign_id)`
- `clicks(offer_id, campaign_id, created_at, OS)`
- `clicks(offer_id, campaign_id, OS, created_at, browser)`
- `clicks(created_at, campaign_id)`
- `clicks(campaign_id, created_at, offer_id)`
- `clicks(campaign_id, offer_id, created_at, OS)`
- `clicks(campaign_id, offer_id, OS, created_at, browser)`
- `clicks(created_at, OS)`
- `clicks(created_at, browser)`
- `conversions(click_id, status)`
