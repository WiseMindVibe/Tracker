<?php

namespace App\Support\Countries;

use JsonSerializable;

class Country implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $name,
        public array $aliases = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'aliases' => $this->aliases,
        ];
    }
}
