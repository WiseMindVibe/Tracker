<?php

namespace App\Modules\Support\Actions;

class ViewAction extends Action
{
    public function name(): string
    {
        return 'View';
    }

    public function type(): string
    {
        return 'view';
    }
}
