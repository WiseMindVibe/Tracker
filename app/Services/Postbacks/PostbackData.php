<?php

namespace App\Services\Postbacks;

class PostbackData
{
    public function __construct(
        public readonly string $clickId,

        public readonly string $commissionId,

        public readonly float $commission,

        public readonly string $status,

        public readonly string $currency,

        public readonly ?string $eventId = null,
        public readonly ?string $eventType = null,

        public readonly ?string $advertiserId = null,

        public readonly ?string $saleDate = null,

        public readonly ?string $modifiedDate = null,

        public readonly ?string $advertiserSaleAmount = null,
    ) {}
}
