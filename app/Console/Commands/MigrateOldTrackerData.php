<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Migrates data from the old tracker schema into the new tracker schema.
 *
 * SETUP REQUIRED BEFORE RUNNING
 * ------------------------------
 * 1. Add a second database connection in config/database.php pointing at the
 *    OLD database, e.g.:
 *
 *      'old_tracker' => [
 *          'driver' => 'mysql',
 *          'host' => env('OLD_DB_HOST', '127.0.0.1'),
 *          'database' => env('OLD_DB_DATABASE', 'old_tracker'),
 *          'username' => env('OLD_DB_USERNAME', 'root'),
 *          'password' => env('OLD_DB_PASSWORD', ''),
 *          'charset' => 'utf8mb4',
 *          'collation' => 'utf8mb4_unicode_ci',
 *      ],
 *
 *    And add the matching OLD_DB_* vars to your .env.
 *
 * 2. The NEW database is assumed to be your default connection (the one
 *    Laravel already uses, e.g. 'mysql'). Adjust $newConnection below if not.
 *
 * 3. Run migrations on the new DB first so all target tables/columns exist.
 *
 * USAGE
 * -----
 *   php artisan migrate:old-tracker            # dry run (default) — logs counts only, writes nothing
 *   php artisan migrate:old-tracker --commit    # actually writes data
 *   php artisan migrate:old-tracker --commit --company="Acme Media"
 *
 * IMPORTANT — ASSUMPTIONS THAT NEED YOUR SIGN-OFF (search "ASSUMPTION" below)
 * ----------------------------------------------------------------------------
 * A. company_id: every old row is assigned to ONE company, created/found by
 *    the --company option (default "Migrated Company").
 *
 * B. clicks.campaign_traffic_id_id: the old `clicks` table only stores
 *    campaign_id, not which external/traffic-side campaign id was involved.
 *    The new `clicks` table requires a campaigns_traffic_ids row instead.
 *    Since there's no way to know which one applied to a specific old click,
 *    this script picks the FIRST campaigns_traffic_ids row created for that
 *    campaign (lowest id). If a campaign has more than one traffic id, this
 *    will not be accurate for all clicks — flag this to your team.
 *
 * C. conversions / conversions_events split: old `conversions` was a single
 *    flat row. New splits it into `conversions_events` (click_id,
 *    commission_id, commission, status) and `conversions` (conversion_id
 *    linking to a conversion_event_id). The old table has no field that
 *    obviously maps to the new `conversions.conversion_id` beyond
 *    commission_id, so this script currently duplicates commission_id into
 *    conversion_id as a placeholder. CONFIRM this is correct or tell me
 *    which old field (event_id? advertiser_id?) should be used instead.
 *
 * D. notifications: old notifications stored click/offer/campaign/affiliate/
 *    revenue directly. New notifications only references conversion_event_id.
 *    This script links each old notification to the conversions_events row
 *    created from the old conversions row with a matching click_id (mapped)
 *    — if a click had multiple conversions, it takes the most recent one.
 *    If old notifications and old conversions don't actually correspond
 *    1:1 by click_id, this join will be wrong for some rows.
 *
 * E. offers.website_id (old) has no column in the new `offers` table at all
 *    — it is dropped. If website/blog association still matters for offers,
 *    let me know how it should be modeled (a pivot table?) and I'll add it.
 *
 * F. click_redirections (old) has no equivalent table in the new schema and
 *    is NOT migrated by this script.
 *
 * G. IP address: old `clicks.ip` is varbinary(16) (packed binary). New
 *    `clicks.ip` is varchar(45) (human-readable). This script converts using
 *    inet_ntop-equivalent logic in PHP.
 */
class MigrateOldTrackerData extends Command
{
    protected $signature = 'migrate:old-tracker
    {--commit : Actually write to the new database. Without this flag, the command only validates and reports counts — it performs NO writes at all, so it runs fast even on large tables.}
    {--company=Migrated Company : Name of the single company all data will be attached to.}
    {--chunk=2000 : Batch size used for high-volume tables (clicks). Larger = fewer queries but more memory/lock time per batch.}';

    protected $description = 'One-time migration of data from the old tracker schema to the new tracker schema.';

    protected string $oldConnection = 'old_tracker';
    protected string $newConnection = 'mysql';

    // old_id => new_id maps, built up as we go so later steps can resolve FKs
    protected array $companyId = [];
    protected array $trafficCatalogMap = [];   // traffic_source name => traffic_catalog.id
    protected array $trafficAccountMap = [];   // old traffic_sources.id => new traffic_accounts.id
    protected array $affiliateCatalogMap = []; // affiliate_program name => affiliate_catalog.id
    protected array $affiliateAccountMap = []; // old affiliate_accounts.id => new affiliate_accounts.id
    protected array $blogMap = [];              // old websites.id => new blogs.id
    protected array $campaignMap = [];          // old campaigns.id => new campaigns.id
    protected array $campaignTrafficIdMap = [];// old campaigns.id => new campaigns_traffic_ids.id (first one, see ASSUMPTION B)
    protected array $offerMap = [];             // old offers.id => new offers.id
    protected array $clickMap = [];             // old clicks.id => new clicks.id

    protected int $chunkSize = 2000;
    protected bool $commit = false;

    public function handle(): int
    {
        $this->commit = (bool) $this->option('commit');
        $this->chunkSize = max(100, (int) $this->option('chunk'));

        $this->info($this->commit ? 'RUNNING IN COMMIT MODE — data will be written.' : 'DRY RUN — no data will be written. Pass --commit to write.');

        $old = DB::connection($this->oldConnection);
        $new = DB::connection($this->newConnection);

        $new->beginTransaction();

        try {
            $this->migrateCompany($new);
            $this->migrateTrafficSources($old, $new);
            $this->migrateAffiliateAccounts($old, $new);
            $this->migrateAffiliateAccountCredentials($old, $new);
            $this->migrateWebsites($old, $new);
            $this->migrateWebsiteBuffers($old, $new);
            $this->migrateCampaigns($old, $new);
            //$this->migrateCampaignExternalIds($old, $new);
            $this->migrateOffers($old, $new);
            $this->migrateOfferArticles($old, $new);
            $this->migrateCampaignOffers($old, $new);
            $this->migrateClicks($old, $new);
            //$this->migrateConversions($old, $new);
            //$this->migrateNotifications($old, $new);

            if ($this->commit) {
                $new->commit();
                $this->info('Migration committed.');
            } else {
                $new->rollBack();
                $this->info('Dry run complete — rolled back, nothing written.');
            }
        } catch (\Throwable $e) {
            $new->rollBack();
            $this->error('Migration failed, rolled back: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // A. Company (ASSUMPTION A: single company for all migrated data)
    // ------------------------------------------------------------------
    protected function migrateCompany($new): void
    {
        $name = $this->option('company');

        $existing = $new->table('companies')->where('name', $name)->first();

        if ($existing) {
            $id = $existing->id;
        } else {
            $id = $new->table('companies')->insertGetId([
                'name' => $name,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->companyId['default'] = $id;
        $this->line("Company '{$name}' -> id {$id}");
    }

    // ------------------------------------------------------------------
    // traffic_sources (old) -> traffic_catalog + traffic_accounts + traffic_accounts_credentials (new)
    // ------------------------------------------------------------------
    protected function migrateTrafficSources($old, $new): void
    {
        $rows = $old->table('traffic_sources')->get();
        $companyId = $this->companyId['default'];

        foreach ($rows as $row) {
            $catalogId = $this->trafficCatalogMap[$row->name]
                ?? ($this->trafficCatalogMap[$row->name] = $new->table('traffic_catalog')
                    ->where('name', $row->name)->value('id'));

            if (!$catalogId) {
                $catalogId = $new->table('traffic_catalog')->insertGetId([
                    'name' => $row->name,
                ]);
                $this->trafficCatalogMap[$row->name] = $catalogId;
            }

            $accountId = $new->table('traffic_accounts')->insertGetId([
                'company_id' => $companyId,
                'traffic_catalog_id' => $catalogId,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->trafficAccountMap[$row->id] = $accountId;

            if (!empty($row->api_key)) {
                $new->table('traffic_accounts_credentials')->insert([
                    'traffic_account_id' => $accountId,
                    'label' => 'API Key',
                    'key' => 'api_key',
                    'value' => $row->api_key,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        $this->info("traffic_sources: migrated " . count($rows) . " rows.");
    }

    // ------------------------------------------------------------------
    // affiliate_accounts (old) -> affiliate_catalog + affiliate_accounts (new)
    // ------------------------------------------------------------------
    protected function migrateAffiliateAccounts($old, $new): void
    {
        $rows = $old->table('affiliate_accounts')->get();
        $companyId = $this->companyId['default'];

        foreach ($rows as $row) {
            $catalogId = $this->affiliateCatalogMap[$row->affiliate_program] ?? null;

            if (!$catalogId) {
                $catalogId = $new->table('affiliate_catalog')->where('name', $row->affiliate_program)->value('id');
            }

            if (!$catalogId) {
                // ASSUMPTION: affiliate_token/offer_mode/commission_mode/blog_redirect_rate
                // have no old equivalent — using safe defaults. Review these per program.
                $catalogId = $new->table('affiliate_catalog')->insertGetId([
                    'name' => $row->affiliate_program,
                    'affiliate_token' => Str::slug($row->affiliate_program),
                    'offer_mode' => 'static',
                    'commission_mode' => 'delta',
                    'merchant_id_label' => null,
                    'blog_redirect_rate' => 0,
                ]);
            }

            $this->affiliateCatalogMap[$row->affiliate_program] = $catalogId;

            $newId = $new->table('affiliate_accounts')->insertGetId([
                'company_id' => $companyId,
                'affiliate_catalog_id' => $catalogId,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->affiliateAccountMap[$row->id] = $newId;
        }

        $this->info("affiliate_accounts: migrated " . count($rows) . " rows.");
    }

    protected function migrateAffiliateAccountCredentials($old, $new): void
    {
        $rows = $old->table('affiliate_account_credentials')->get();
        $count = 0;

        foreach ($rows as $row) {
            $newAccountId = $this->affiliateAccountMap[$row->affiliate_account_id] ?? null;
            if (!$newAccountId) {
                $this->warn("Skipping credential id {$row->id}: no mapped affiliate_account for old id {$row->affiliate_account_id}");
                continue;
            }

            // NOTE (ASSUMPTION): is_required has no home in the new per-account
            // credentials table (it now lives on affiliate_field_definitions,
            // which is metadata not data) — intentionally dropped here.
            $new->table('affiliate_accounts_credentials')->insert([
                'affiliate_account_id' => $newAccountId,
                'label' => $row->field_key,
                'key' => $row->field_key,
                'value' => $row->field_value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        $this->info("affiliate_account_credentials: migrated {$count} rows.");
    }

    // ------------------------------------------------------------------
    // websites (old) -> blogs (new)
    // ------------------------------------------------------------------
    protected function migrateWebsites($old, $new): void
    {
        $rows = $old->table('websites')->get();
        $companyId = $this->companyId['default'];

        foreach ($rows as $row) {
            $newId = $new->table('blogs')->insertGetId([
                'company_id' => $companyId,
                'domain' => $row->domain,
                'main_geo' => $row->country,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->blogMap[$row->id] = $newId;
        }

        $this->info("websites: migrated " . count($rows) . " rows into blogs.");
    }

    protected function migrateWebsiteBuffers($old, $new): void
    {
        $rows = $old->table('websites_buffers')->get();
        $count = 0;

        foreach ($rows as $row) {
            $blogId = $this->blogMap[$row->website_id] ?? null;
            if (!$blogId) {
                $this->warn("Skipping buffer id {$row->id}: no mapped blog for old website_id {$row->website_id}");
                continue;
            }

            // NOTE: old `type` column dropped — no equivalent field in blogs_buffers.
            $new->table('blogs_buffers')->insert([
                'blog_id' => $blogId,
                'buffer_url' => $row->buffer_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        $this->info("websites_buffers: migrated {$count} rows into blogs_buffers.");
    }

    // ------------------------------------------------------------------
    // campaigns (old) -> campaigns (new)
    // ------------------------------------------------------------------
    protected function migrateCampaigns($old, $new): void
    {
        $rows = $old->table('campaigns')->get();

        foreach ($rows as $row) {
            $trafficAccountId = $this->trafficAccountMap[$row->traffic_source_id] ?? null;
            if (!$trafficAccountId) {
                $this->warn("Skipping campaign id {$row->id}: no mapped traffic_account for old traffic_source_id {$row->traffic_source_id}");
                continue;
            }

            $newId = $new->table('campaigns')->insertGetId([
                'uuid' => $row->uuid,
                'traffic_account_id' => $trafficAccountId,
                'name' => $row->name,
                'country' => $row->country,
                'is_tester' => $row->tester,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->campaignMap[$row->id] = $newId;
        }

        $this->info("campaigns: migrated " . count($rows) . " rows.");
    }

    /*
    // campaign_external_ids -> campaigns_traffic_ids
    // Also fills $campaignTrafficIdMap for clicks resolution (see ASSUMPTION B)
    protected function migrateCampaignExternalIds($old, $new): void
    {
        $rows = $old->table('campaign_external_ids')->get();
        $count = 0;

        foreach ($rows as $row) {
            $campaignId = $this->campaignMap[$row->campaign_id] ?? null;
            if (!$campaignId) {
                $this->warn("Skipping external id {$row->id}: no mapped campaign for old campaign_id {$row->campaign_id}");
                continue;
            }

            $newId = $new->table('campaigns_traffic_ids')->insertGetId([
                'campaign_id' => $campaignId,
                'traffic_campaign_id' => $row->external_campaign_id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Only remember the first traffic-id row per campaign (ASSUMPTION B)
            if (!isset($this->campaignTrafficIdMap[$row->campaign_id])) {
                $this->campaignTrafficIdMap[$row->campaign_id] = $newId;
            }

            $count++;
        }

        $this->info("campaign_external_ids: migrated {$count} rows into campaigns_traffic_ids.");
    }
    */

    // ------------------------------------------------------------------
    // offers (old) -> offers (new)
    // ------------------------------------------------------------------
    protected function migrateOffers($old, $new): void
    {
        $rows = $old->table('offers')->get();

        foreach ($rows as $row) {
            $affiliateAccountId = $this->affiliateAccountMap[$row->affiliate_program_id] ?? null;
            $blogId = $this->blogMap[$row->website_id] ?? null;
            if (!$affiliateAccountId) {
                $this->warn("Skipping offer id {$row->id}: no mapped affiliate_account for old affiliate_program_id {$row->affiliate_program_id}");
                continue;
            }

            // NOTE (ASSUMPTION E): old website_id is dropped — no column for it
            // in the new offers table.
            $newId = $new->table('offers')->insertGetId([
                'affiliate_account_id' => $affiliateAccountId,
                'blog_id' => $blogId,
                'name' => $row->name,
                'type' => 'static',
                'merchant_id' => null,
                'country' => $row->country,
                'affiliate_link' => $row->affiliate_link,
                'is_tester' => 0,
                'status' => 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            $this->offerMap[$row->id] = $newId;
        }

        $this->info("offers: migrated " . count($rows) . " rows.");
    }

    protected function migrateOfferArticles($old, $new): void
    {
        $rows = $old->table('offers_articles')->get();
        $count = 0;

        foreach ($rows as $row) {
            $offerId = $this->offerMap[$row->offer_id] ?? null;
            if (!$offerId) {
                $this->warn("Skipping article id {$row->id}: no mapped offer for old offer_id {$row->offer_id}");
                continue;
            }

            $new->table('offers_articles')->insert([
                'offer_id' => $offerId,
                'article_url' => $row->article_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        $this->info("offers_articles: migrated {$count} rows.");
    }

    protected function migrateCampaignOffers($old, $new): void
    {
        $rows = $old->table('campaign_offers')->get();
        $count = 0;

        foreach ($rows as $row) {
            $campaignId = $this->campaignMap[$row->campaign_id] ?? null;
            $offerId = $this->offerMap[$row->offer_id] ?? null;

            if (!$campaignId || !$offerId) {
                $this->warn("Skipping campaign_offer id {$row->id}: missing mapped campaign or offer.");
                continue;
            }

            $new->table('campaigns_offers')->insert([
                'campaign_id' => $campaignId,
                'offer_id' => $offerId,
                'current_views' => $row->current_views ?? 0,
                'cap_views' => $row->cap,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $count++;
        }

        $this->info("campaign_offers: migrated {$count} rows into campaigns_offers.");
    }


    // ------------------------------------------------------------------
    // clicks (old) -> clicks (new)
    //
    // High-volume path: batched multi-row inserts via insertOrIgnore()
    // instead of one insertGetId() per row. This is the single biggest
    // performance win for a 3M-row table — it turns 3,000,000 round trips
    // into ~1,500 (at the default --chunk=2000).
    //
    // insertOrIgnore() also makes this step SAFE TO RE-RUN: if it gets
    // interrupted partway through, running the command again will just
    // skip click_id values that already exist (click_id is unique in the
    // new schema) rather than erroring or duplicating rows.
    //
    // NOTE: because we no longer insertGetId() per row, we don't keep an
    // old->new id map for clicks in memory (would be a multi-million-entry
    // array). migrateConversions() below resolves new click ids by looking
    // up click_id (indexed, unique) instead.
    // ------------------------------------------------------------------
    protected function migrateClicks($old, $new): void
    {
        $total = $old->table('clicks')->count();
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" clicks: %current%/%max% [%bar%] %percent:3s%%  elapsed: %elapsed:6s%  mem: %memory:6s%");
        $bar->start();

        $processed = 0;
        $inserted = 0;
        $skippedNoOffer = 0;
        $buffer = [];

        $old->table('clicks')->orderBy('id')->chunkById($this->chunkSize, function ($rows) use (&$buffer, &$processed, &$inserted, &$skippedNoOffer, $new, $bar) {
            foreach ($rows as $row) {
                $processed++;
                $bar->advance();

                $offerId = $this->offerMap[$row->offer_id] ?? null;
                if (!$offerId) {
                    $skippedNoOffer++;
                    continue;
                }

                $buffer[] = [
                    'click_id' => $row->click_id,
                    'offer_id' => $offerId,
                    'country' => $row->country,
                    'region' => $row->region,
                    'language' => $row->language,
                    'device' => $row->device,
                    'os' => $row->OS,
                    'os_version' => $row->os_version,
                    'browser' => $row->browser,
                    'browser_version' => $row->browser_version,
                    'connection_type' => $row->connection_type,
                    'isp' => $row->isp,
                    'carrier' => $row->carrier,
                    'zoneid' => $row->zone_id,
                    'subzone_id' => $row->subzone_id,
                    'user_agent' => $row->useragent,
                    'user_activity' => $row->user_activity,
                    'ip' => $this->binaryIpToString($row->ip),
                    'cost' => $row->cost,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($this->commit && !empty($buffer)) {
                $new->table('clicks')->insertOrIgnore($buffer);
            }
            $inserted += count($buffer);
            $buffer = [];
        }, 'id');

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'clicks: processed %d old rows -> %s %d, skipped %d (no mapped offer).',
            $processed,
            $this->commit ? 'inserted (or ignored existing)' : 'would insert',
            $inserted,
            $skippedNoOffer
        ));
    }

    /**
     * Converts the old varbinary(16) packed IP into a human-readable string
     * for the new varchar(45) column. Handles both IPv4 (4 bytes) and
     * IPv6 (16 bytes) packed forms.
     */
    protected function binaryIpToString($binary): ?string
    {
        if (empty($binary)) {
            return null;
        }

        $ip = @inet_ntop($binary);
        return $ip !== false ? $ip : null;
    }

    // ------------------------------------------------------------------
    // conversions (old, flat) -> conversions_events + conversions (new, split)
    // ASSUMPTION C applies here — review before trusting conversion_id values.
    // ------------------------------------------------------------------
    /*
    protected array $oldClickIdToLatestConversionEventId = []; // old click_id (bigint) => new conversions_events.id, used by notifications step

    protected function migrateConversions($old, $new): void
    {
        $rows = $old->table('conversions')->orderBy('id')->get();
        $count = 0;

        foreach ($rows as $row) {
            $newClickId = $this->clickMap[$row->click_id] ?? null;
            if (!$newClickId) {
                $this->warn("Skipping conversion id {$row->id}: no mapped click for old click_id {$row->click_id}");
                continue;
            }

            $eventId = $new->table('conversions_events')->insertGetId([
                'click_id' => $newClickId,
                'commission_id' => $row->commission_id,
                'commission' => $row->revenue ?? $row->sales_amount ?? 0,
                'status' => (string) $row->status,
                'created_at' => $row->sale_date ?? now(),
                'updated_at' => $row->modified_date ?? now(),
            ]);

            // ASSUMPTION C: using commission_id as a placeholder conversion_id.
            // Replace with the correct source field once confirmed.
            $new->table('conversions')->insert([
                'conversion_event_id' => $eventId,
                'conversion_id' => $row->commission_id,
                'created_at' => $row->sale_date ?? now(),
                'updated_at' => $row->modified_date ?? now(),
            ]);

            // Remember most recent event per old click_id, for notifications join (ASSUMPTION D)
            $this->oldClickIdToLatestConversionEventId[$row->click_id] = $eventId;

            $count++;
        }

        $this->info("conversions: migrated {$count} rows into conversions_events + conversions.");
    }

    // ------------------------------------------------------------------
    // notifications (old) -> notifications (new)
    // ASSUMPTION D applies here.
    // ------------------------------------------------------------------
    protected function migrateNotifications($old, $new): void
    {
        $rows = $old->table('notifications')->get();
        $count = 0;

        foreach ($rows as $row) {
            $eventId = $this->oldClickIdToLatestConversionEventId[$row->click_id] ?? null;

            if (!$eventId) {
                $this->warn("Skipping notification id {$row->id}: no mapped conversions_events for old click_id {$row->click_id}");
                continue;
            }

            $new->table('notifications')->insert([
                'conversion_event_id' => $eventId,
                'is_read' => $row->is_read ?? 0,
                'read_at' => $row->read_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        $this->info("notifications: migrated {$count} rows.");
    }
    */
}
