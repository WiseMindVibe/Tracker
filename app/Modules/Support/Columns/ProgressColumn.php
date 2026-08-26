<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;

class ProgressColumn extends Column
{
    public function __construct(
        public string $relation,      // e.g. "campaignOffers"
        public string $currentField,  // e.g. "current_views"
        public string $capField,      // e.g. "cap_views"
        public ?string $label = '-',
        public bool $sortable = false,
    ) {}

    public function type(): string
    {
        return 'progress';
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query
            ->withSum($this->relation, $this->currentField)
            ->withSum($this->relation, $this->capField);
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
            'field' => $this->relation, // for React key consistency
            'currentKey' => "{$this->relation}_sum_{$this->currentField}",
            'capKey' => "{$this->relation}_sum_{$this->capField}",
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
