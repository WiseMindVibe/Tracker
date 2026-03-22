<?php

/**
 * Single place to change display labels shared across CRUD pages (offers, campaigns, traffics, …).
 *
 * Use the public const* in controller $config property defaults (PHP requires constant expressions there).
 * Use get() when building arrays at runtime.
 */
class CrudSharedLabels
{
    public const NAME = 'Name';
    public const COUNTRY = 'Country';

    private const LABELS = [
        'name' => self::NAME,
        'country' => self::COUNTRY,
    ];

    public static function get(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }
}
