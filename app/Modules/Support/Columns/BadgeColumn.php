<?php

namespace App\Modules\Support\Columns;

class BadgeColumn extends Column
{
    /**
     * @param array<string, string> $colors  Map of raw value => color/variant name,
     *                                        e.g. ['active' => 'green', 'inactive' => 'gray']
     */
    public function __construct(
        public string $field,
        public ?string $label = '-',
        public array $colors = [],
        public string $default = 'gray',
    ) {
    }

    public function type(): string
    {
        return 'badge';
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type(),
            'field' => $this->field,
            'label' => $this->label,
            'colors' => $this->colors,
            'default' => $this->default,
        ];
    }
}
