<?php

namespace App\Modules\Support\Field;

class Repeater implements \JsonSerializable
{
    public function __construct(
        public string $relation,     // e.g. "buffers" — hasMany method name on the model
        public string $label,        // e.g. "Buffer URLs"
        public array $fields,        // Field[] describing one row (usually just one field)
        public int $min = 0,         // minimum rows required
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'relation' => $this->relation,
            'label' => $this->label,
            'fields' => $this->fields,
            'min' => $this->min,
        ];
    }
}
