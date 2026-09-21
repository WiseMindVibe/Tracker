<?php

namespace App\Modules\Support\Filters;

class SelectFilter extends Filter
{
    public function __construct(
        public string $field,
        public array $options
    ) {
    }

    public function name(): string
    {
        return $this->field;
    }

    public function type(): string
    {
        return 'select';
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'field' => $this->field,
            'options' => $this->options,
        ];
    }
}
