<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;
use JsonSerializable;

abstract class Column implements JsonSerializable
{
    abstract public function type(): string;

    abstract public function applySort(Builder $query, string $direction): void;

    public function applyToQuery(Builder $query): Builder
    {
        return $query;
    }
    
    abstract public function jsonSerialize(): array;
}
