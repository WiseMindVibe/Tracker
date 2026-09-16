<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressColumn extends Column
{
    public function __construct(
        public string $field,
        public string $relation,
        public string $currentField,
        public string $capField,
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
        $model = $query->getModel();
        $relation = $model->{$this->relation}();

        if (! $relation instanceof HasMany) {
            return;
        }

        $relatedTable = $relation->getRelated()->getTable();

        $foreignKey = $relation->getForeignKeyName();
        $localKey = $relation->getLocalKeyName();

        $parentTable = $model->getTable();

        $current = "(SELECT COALESCE(SUM({$relatedTable}.{$this->currentField}), 0)
            FROM {$relatedTable}
            WHERE {$relatedTable}.{$foreignKey} = {$parentTable}.{$localKey})";

        $cap = "(SELECT COALESCE(SUM({$relatedTable}.{$this->capField}), 0)
            FROM {$relatedTable}
            WHERE {$relatedTable}.{$foreignKey} = {$parentTable}.{$localKey})";

        $query
            ->orderByRaw(
                "CASE
                    WHEN {$cap} > 0
                    THEN {$current} / {$cap}
                    ELSE 0
                END {$direction}"
            )
            ->orderByRaw("{$cap} {$direction}");
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'relation' => $this->relation,
            'field' => $this->field,
            'currentKey' => "{$this->relation}_sum_{$this->currentField}",
            'capKey' => "{$this->relation}_sum_{$this->capField}",
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
