<?php

require_once __DIR__ . '/../models/ModelDashboard.php';
require_once __DIR__ . '/../models/ModelInspect.php';
require_once __DIR__ . '/../models/ModelOffers.php';

class ControllerInspect
{
    public function index(): void
    {
        $offerId = isset($_GET['offer_id']) ? (int) $_GET['offer_id'] : 0;
        $offers = ModelInspect::listOffersForSelect();

        $offer = null;
        $clickBounds = null;
        $statsLifetime = null;
        $stats7 = null;
        $stats30 = null;
        $rangeLifetime = null;
        $range7 = null;
        $range30 = null;
        $campaignRows = [];
        $orphanAssignments = [];
        $topCountriesLifetime = [];
        $topCountries30 = [];

        if ($offerId > 0) {
            $offer = ModelOffers::view($offerId);
            if (!is_array($offer) || empty($offer['id'])) {
                $offer = null;
                $offerId = 0;
            } else {
                $rangeLifetime = ModelDashboard::resolveDateRange('all_time');
                $range7 = ModelDashboard::resolveDateRange('last7');
                $range30 = ModelDashboard::resolveDateRange('last30');

                $clickBounds = ModelInspect::fetchOfferClickBounds($offerId);
                $statsLifetime = ModelInspect::fetchOfferStats(
                    $offerId,
                    $rangeLifetime['start'],
                    $rangeLifetime['end']
                );
                $stats7 = ModelInspect::fetchOfferStats($offerId, $range7['start'], $range7['end']);
                $stats30 = ModelInspect::fetchOfferStats($offerId, $range30['start'], $range30['end']);

                $topCountriesLifetime = ModelInspect::fetchTopCountries(
                    $offerId,
                    $rangeLifetime['start'],
                    $rangeLifetime['end'],
                    10
                );
                $topCountries30 = ModelInspect::fetchTopCountries(
                    $offerId,
                    $range30['start'],
                    $range30['end'],
                    10
                );

                $assignments = ModelInspect::fetchCampaignOfferAssignments($offerId);
                $orphanAssignments = ModelInspect::fetchOrphanAssignments($offerId);

                $clickBreakdown = ModelInspect::fetchCampaignClickBreakdown(
                    $offerId,
                    $range7['start'],
                    $range30['start'],
                    $range30['end']
                );

                $campaignIds = array_values(array_unique(array_filter(array_map(
                    static fn(array $r): int => (int) ($r['campaign_id'] ?? 0),
                    $clickBreakdown
                ))));
                foreach ($assignments as $a) {
                    $cid = (int) ($a['campaign_id'] ?? 0);
                    if ($cid > 0) {
                        $campaignIds[] = $cid;
                    }
                }
                $campaignIds = array_values(array_unique(array_filter($campaignIds)));

                $externalByCampaignId = ModelInspect::fetchExternalIdsByCampaignIds($campaignIds);

                $merged = ModelInspect::mergeCampaignRows(
                    $clickBreakdown,
                    $assignments,
                    $externalByCampaignId
                );

                $seenCids = [];
                foreach ($merged as $m) {
                    $cid = (int) ($m['campaign_id'] ?? 0);
                    if ($cid > 0) {
                        $seenCids[$cid] = true;
                    }
                }

                foreach ($assignments as $a) {
                    $cid = (int) ($a['campaign_id'] ?? 0);
                    if ($cid < 1 || isset($seenCids[$cid])) {
                        continue;
                    }
                    $ext = $externalByCampaignId[$cid] ?? '';
                    $merged[] = [
                        'click_campaign_ref' => $a['campaign_uuid'] ?? $cid,
                        'campaign_id' => $cid,
                        'campaign_uuid' => $a['campaign_uuid'] ?? null,
                        'campaign_name' => $a['campaign_name'] ?? '',
                        'tester' => $a['tester'] ?? null,
                        'traffic_name' => $a['traffic_name'] ?? null,
                        'clicks_lifetime' => 0,
                        'clicks_7d' => 0,
                        'clicks_30d' => 0,
                        'first_click' => null,
                        'last_click' => null,
                        'cap' => (int) ($a['cap'] ?? 0),
                        'current_views' => (int) ($a['current_views'] ?? 0),
                        'assignment_active' => (int) ($a['assignment_active'] ?? 0),
                        'has_remaining_cap' => (int) ($a['has_remaining_cap'] ?? 0),
                        'external_ids' => $ext,
                    ];
                    $seenCids[$cid] = true;
                }

                usort($merged, static function (array $x, array $y): int {
                    $cx = (int) ($x['clicks_lifetime'] ?? 0);
                    $cy = (int) ($y['clicks_lifetime'] ?? 0);
                    if ($cx !== $cy) {
                        return $cy <=> $cx;
                    }
                    return strcasecmp((string) ($x['campaign_name'] ?? ''), (string) ($y['campaign_name'] ?? ''));
                });

                $campaignRows = $merged;
            }
        }

        require __DIR__ . '/../views/ViewInspect.php';
    }
}
