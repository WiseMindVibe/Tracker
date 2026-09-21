<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;

class TextColumn extends Column
{
    public function __construct(
        public string $field,
        public ?string $label = '-',
        public bool $sortable = true,
    ) {
    }

    public function type(): string
    {
        return 'text';
    }

    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->field, $direction);
    }
    
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'field' => $this->field,
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
