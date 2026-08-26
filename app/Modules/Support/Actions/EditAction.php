<?php

namespace App\Modules\Support\Actions;

class EditAction extends Action
{
    public function name(): string
    {
        return 'Edit';
    }

    public function type(): string
    {
        return 'edit';
    }
}
