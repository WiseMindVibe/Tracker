<?php

return [
    'propellerads' => [
        'key' => 'api_key',
        'stop' => 'https://ssp-api.propellerads.com/v5/adv/campaigns/stop',
    ],
    'hilltopads' => [
        'key' => 'key',
        'stop' => 'https://api.hilltopads.com/advertiser/stopCampaign',
    ],
    'popcash' => [
        'key' => 'apikey',
        'stop' => 'https://api.popcash.net/campaigns/', // append {id}
    ],
];
