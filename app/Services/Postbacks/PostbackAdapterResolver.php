<?php

namespace App\Services\Postbacks;

use App\Models\AffiliateCatalog;
use App\Services\Postbacks\Adapters\OponiaPostbackAdapter;
use App\Services\Postbacks\Adapters\YieldKitPostbackAdapter;
use RuntimeException;

class PostbackAdapterResolver
{
    public function resolve(AffiliateCatalog $catalog): PostbackAdapter
    {
        return match ($catalog->slug) {
            'yieldkit' => app(YieldKitPostbackAdapter::class),
            'oponia' => app(OponiaPostbackAdapter::class),

            default => throw new RuntimeException(
                "No postback adapter configured for [{$catalog->slug}]"
            ),
        };
    }
}
