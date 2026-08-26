<?php

namespace App\Modules\Support\Filters;

use JsonSerializable;

abstract class Filter implements JsonSerializable
{
    abstract public function name(): string;

    abstract public function type(): string;

    abstract public function jsonSerialize(): array;
}
