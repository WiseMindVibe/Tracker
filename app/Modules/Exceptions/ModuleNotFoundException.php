<?php

namespace App\Modules\Exceptions;

use RuntimeException;

class ModuleNotFoundException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("No module registered for key [{$key}].");
    }
}
