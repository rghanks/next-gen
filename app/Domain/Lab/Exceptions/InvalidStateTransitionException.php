<?php

declare(strict_types=1);

namespace App\Domain\Lab\Exceptions;

use RuntimeException;

class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(string $fromState, string $toState, string $reason = '')
    {
        $message = "Invalid state transition from '{$fromState}' to '{$toState}'.";
        if ($reason !== '') {
            $message .= " Reason: {$reason}";
        }
        parent::__construct($message);
    }
}
