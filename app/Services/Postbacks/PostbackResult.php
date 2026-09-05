<?php

namespace App\Services\Postbacks;

use App\Models\ConversionEvent;
use App\Models\Notification;

final class PostbackResult
{
    public function __construct(
        public readonly bool $created,
        public readonly ConversionEvent $event,
        public readonly ?Notification $notification,
    ) {}
}
