<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;

class CountColumn extends Column
{
    public function __construct(
        public string $relation,
        public ?string $label = '-',
        public bool $sortable = false,
    ) {}

    public function type(): string
    {
        return 'count';
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query->withCount($this->relation);
    }

    public function applySort(Builder $query, string $direction): void
    {
        // not supported yet — no-op
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'relation' => $this->relation,
            'field' => $this->relation, // keeps React `key` prop consistent with other column types
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
