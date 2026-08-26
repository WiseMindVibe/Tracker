<?php

namespace App\Modules\Support\Actions;

class CreateAction extends Action
{
    public function __construct(
        public ?string $label = null,
    ){
    }
    public function name(): string
    {
        return 'Create';
    }

    public function type(): string
    {
        return 'create';
    }
}
