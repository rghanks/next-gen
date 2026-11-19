<?php

declare(strict_types=1);

namespace App\Domain\Lab\Exceptions;

use RuntimeException;

class OptimisticLockException extends RuntimeException
{
    public function __construct(string $message = 'The record was modified by another user. Please reload the latest data.')
    {
        parent::__construct($message);
    }
}
