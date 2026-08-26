<?php

// Base URLs and any other non-secret, per-network settings.
// Secrets (api_key / api_secret) live in the DB — see
// affiliate_accounts_credentials — never here and never in .env.

return [

    'yieldkit' => [
        'base_url' => 'https://account2.yieldkit.com/api/v3/reports',
    ],

    // Add more networks the same way, e.g.:
    // 'awin' => [
    //     'base_url' => 'https://api.awin.com',
    // ],

];
