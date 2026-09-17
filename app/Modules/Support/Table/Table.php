<?php

namespace App\Modules\Support\Table;

use Illuminate\Database\Eloquent\Builder;
use JsonSerializable;

class Table implements JsonSerializable
{
    public function __construct(
        public array $columns = [],
        public array $filters = [],
        public array $actions = [],
        public array $defaultSort = [],
    ) {
    }

    public function applyToQuery(Builder $query): Builder
    {
        foreach ($this->columns as $column) {
            $query = $column->applyToQuery($query);
        }
        return $query;
    }

    public function jsonSerialize(): array
    {
        return [
            'columns' => $this->columns,
            'filters' => $this->filters,
            'actions' => $this->actions,
        ];
    }
}
