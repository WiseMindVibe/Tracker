<?php

return [
    'default' => [
        'mode' => 'delta',
        'statuses' => [],
        'normalize_absolute_sign' => false,
        'clamp_to_zero' => false,
    ],

    'yieldkit' => [
        'mode' => 'delta',
        'statuses' => [
            'confirmed' => [
                'mode' => 'absolute',
                'normalize_absolute_sign' => false,
            ],
            'paid' => [
                'mode' => 'absolute',
                'normalize_absolute_sign' => false,
            ],
            'rejected' => [
                'mode' => 'absolute',
                'fallback' => 'accumulated',
                'normalize_absolute_sign' => true,
            ],
        ],
        'normalize_absolute_sign' => false,
        'clamp_to_zero' => false,
    ],

    'oponia' => [
        'mode' => 'absolute',
        'statuses' => [],
        'normalize_absolute_sign' => false,
        'clamp_to_zero' => false,
    ],
];
