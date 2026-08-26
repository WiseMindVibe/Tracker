# YieldKit → conversions_events integration

## What this does

1. `php artisan yieldkit:backfill-commissions` — one-time pull of the last
   365 days of YieldKit commissions, inserted/updated into `conversions_events`.
2. `php artisan yieldkit:fetch-daily-commissions` — pulls the last day (with
   a 1-day overlap for safety) and upserts. This is what runs every day at
   08:00 via the Laravel scheduler.

Both commands share the same underlying client/importer, so there's one
place to fix things if YieldKit's API changes.

## Where each file goes

Copy each file into your Laravel app at the **same relative path** it's
provided at:

```
config/affiliates.php
app/Models/AffiliateCatalog.php
app/Models/AffiliateAccount.php
app/Models/AffiliateAccountCredential.php
app/Models/ConversionEvent.php
app/Services/Affiliates/AffiliateCredentialResolver.php
app/Services/Affiliates/YieldKit/YieldKitClient.php
app/Services/Affiliates/YieldKit/YieldKitCommissionImporter.php
app/Console/Commands/YieldKitBackfillCommissions.php
app/Console/Commands/YieldKitFetchDailyCommissions.php
```

`KERNEL_SCHEDULE_SNIPPET.php` is NOT a file to copy in as-is — open your
existing `app/Console/Commands/Kernel.php` and paste its contents into the
`schedule()` method (Laravel 11+ auto-discovers commands in
`app/Console/Commands`, so no other registration is needed; on Laravel <11
double check the command is listed in `Kernel::$commands` or that
`load(__DIR__.'/Commands')` is called).

## Before you run anything: check two assumptions

I don't have your exact schema for `affiliate_accounts_credentials` or how
clicks map to conversions in your tracker, so I made the most likely
assumption for each and flagged it in the code:

**1. Credentials table shape** (`AffiliateAccountCredential.php`)
Assumed: `affiliate_accounts_credentials` has columns
`id, affiliate_account_id, key, value` — one row per secret, e.g.:

| affiliate_account_id | key | value |
|---|---|---|
| 4 | api_key | abc123 |
| 4 | api_secret | def456 |

If instead you have literal `api_key` / `api_secret` columns directly on
the account (or one row per account with both columns), tell me and I'll
adjust `AffiliateCredentialResolver::get()` — it's a 2-line change.

**2. click_id lookup** (`YieldKitCommissionImporter::resolveClickId()`)
Assumed: you pass your internal `click_id` out as YieldKit's tracking tag,
and it comes back in the `ykTag` field on each commission row. If your
tracker links conversions to clicks some other way (a sub-id, `orderId`,
or a separate lookup), this is the one method to change — nothing else in
the pipeline depends on it.

## Setup steps

1. Copy the files in per the table above.
2. Make sure a row exists for YieldKit in your `affiliate_catalogs` table
   with `slug = 'yieldkit'` (or `name = 'yieldkit'`), and that the
   corresponding `affiliate_accounts` row has its `api_key` / `api_secret`
   stored per the credentials table shape above.
3. No `.env` changes needed — the base URL lives in `config/affiliates.php`,
   and secrets live in the DB, exactly as you wanted.
4. Add the scheduler snippet to `app/Console/Kernel.php`.
5. Make sure your server actually runs the Laravel scheduler (this is
   usually already set up, but worth confirming):
   ```
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```
   This single cron entry lets Laravel's scheduler fire your 08:00 job
   (and anything else you schedule) without needing a separate cron line
   per job.

## Running it

```bash
# One-time backfill of the last year
php artisan yieldkit:backfill-commissions

# Backfill a custom window instead of 365 days
php artisan yieldkit:backfill-commissions --days=180

# Manually trigger what the daily cron does, for testing
php artisan yieldkit:fetch-daily-commissions

# Test the scheduler wiring without waiting for 08:00
php artisan schedule:run
```

Both commands print progress (`fetching X -> Y ...`, rows imported/updated)
so you can watch them work. Rows that can't be matched to a `click_id` are
skipped and logged as a warning (check `storage/logs/laravel.log`) rather
than failing the whole run — check those logs after your first backfill to
see how big that group is.

## Adding another affiliate network later

This is why the base URL lives in `config/affiliates.php` and credentials
live in the DB rather than `.env`: to add e.g. Awin, you'd add an entry to
`config/affiliates.php`, add an `affiliate_catalogs` row with
`slug = 'awin'`, store its credentials the same way, and write an
`AwinClient` / `AwinCommissionImporter` following the same shape as the
YieldKit ones — no `.env` or config changes needed per network beyond the
one config array entry.
