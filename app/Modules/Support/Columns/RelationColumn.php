<?php

namespace App\Modules\Support\Columns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationColumn extends Column
{
    public function __construct(
        public string $relation,
        public string $field,
        public ?string $label = '-',
        public bool $sortable = true,
    ) {
    }

    public function type(): string
    {
        return 'relation';
    }

    public function applySort(Builder $query, string $direction): void
    {
        $model = $query->getModel();
        $relation = $model->{$this->relation}();

        // Only belongsTo is safe to join without risking duplicate rows.
        // hasMany/belongsToMany would multiply rows and need aggregation instead.
        if (! $relation instanceof BelongsTo) {
            return;
        }

        $relatedTable = $relation->getRelated()->getTable();
        $alias = 'sort_' . $this->relation;

        $query->select($model->getTable() . '.*')
            ->leftJoin(
                "{$relatedTable} as {$alias}",
                "{$alias}.{$relation->getOwnerKeyName()}",
                '=',
                $relation->getQualifiedForeignKeyName()
            )
            ->orderBy("{$alias}.{$this->field}", $direction);
    }

    public function applyToQuery(Builder $query): Builder
    {
        return $query->with($this->relation);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'relation' => collect(explode('.', $this->relation))
                ->map(fn($segment) => \Illuminate\Support\Str::snake($segment))
                ->implode('.'),
            'field' => $this->field,
            'label' => $this->label,
            'sortable' => $this->sortable,
        ];
    }
}
