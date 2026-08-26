<?php

namespace App\Modules\Support\Field;

use Illuminate\Support\Str;
use JsonSerializable;

class Field implements JsonSerializable
{
    public function __construct(
        public string $field,
        public string $label,
        public string $type = 'text' | 'bool' | 'url' | 'select' | 'country',     // 'text','select','number','textarea','repeater','repeater_group','uuid'
        public ?string $placeholder = null,
        public ?array $options = [],
        public mixed $default = null,
        public bool $required = true,
        public bool $disabled = false,
        
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'type' => $this->type,
            'placeholder' => $this->placeholder,
            'options' => $this->options,
            'required' => $this->required,
            'default' => $this->default,
            'disabled' => $this->disabled,

        ];
    }
}
