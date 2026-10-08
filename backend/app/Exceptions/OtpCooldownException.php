<?php

namespace App\Exceptions;

use Exception;

/** Thrown when a resend is attempted inside the cooldown window. */
class OtpCooldownException extends Exception
{
    public function __construct(public int $retryAfter)
    {
        parent::__construct("A code was just sent — wait {$retryAfter}s before resending.");
    }
}
