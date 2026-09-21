<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;

class ChipListColumn extends Column
{
    public function __construct(
        public string $relation,   // e.g. "trafficIds"
        public string $field,      // e.g. "traffic_campaign_id"
        public ?string $label = '-',
        public bool $sortable = false,
    ) {}

    public function type(): string
    {
        return 'chip_list';
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query->with($this->relation);
    }

    public function applySort(Builder $query, string $direction): void
    {
        // not applicable — no-op
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'relation' => \Illuminate\Support\Str::snake($this->relation), // matches JSON serialization of the nested array
            'field' => $this->field,
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
