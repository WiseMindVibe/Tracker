<?php

function generateTrackingUrl(string $base_url, array $campaign): string
{
    $tracking_url =     $base_url . "/public/redirect.php?cid=" . $campaign['id'];

    switch ($campaign['traffic_source_name']) {

        case 'PropellerAds':
            $tracking_url .= '&clickid=${SUBID}&campaignid={campaignid}&country={country}&os={os}&browser={browser}&connection.type={connection.type}&isp={isp}&carrier={carrier}&zoneid={zoneid}&cost={price}';
            break;

        case 'HilltopAds':
            $tracking_url .= '&geo={{geo}}&zoneid={{zoneid}}&adid={{adid}}&campaignid={{campaignid}}&category={{category}}&cpmbid={{cpmbid}}&price={{price}}&browsername={{browsername}}&appname={{appname}}';
            break;

        default:
            // no tokens added
            break;
    }

    return $tracking_url;
}
