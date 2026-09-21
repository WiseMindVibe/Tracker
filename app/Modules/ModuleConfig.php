<?php

namespace App\Modules;

use App\Modules\Support\Table\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class ModuleConfig
{
    abstract public function titles(): array;

    abstract public function model(): string;

    abstract public function table(): Table;

    public function fields(): array
    {
        return [];
    }

    public function repeaters(): array
    {
        return [];
    }

    public function uniqueConstraint(): ?array
    {
        return null;
    }

    
}
