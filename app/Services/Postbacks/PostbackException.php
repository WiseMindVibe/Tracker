<?php

namespace App\Services\Postbacks;

use RuntimeException;

class PostbackException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
