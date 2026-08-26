<?php

namespace App\Modules\Support\Actions;

use JsonSerializable;

abstract class Action implements JsonSerializable
{
    abstract public function name(): string;

    abstract public function type(): string;

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'name' => $this->name(),
        ];
    }
}
