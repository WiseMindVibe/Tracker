<?php

namespace App\Modules\Support;

class TrackingLinkBuilder
{
    public static function build(?string $uuid, ?string $slug): ?string
    {
        if (! $uuid || ! $slug) {
            return null;
        }

        $template = config("traffic_sources_tokens.{$slug}");
        if (! $template) {
            return null;
        }

        $base = rtrim(config('tracker.base_url'), '/');
        $path = '/'.ltrim(config('tracker.redirect_path'), '/');

        return "{$base}{$path}?campaign_uuid={$uuid}&{$template}";
    }
}
