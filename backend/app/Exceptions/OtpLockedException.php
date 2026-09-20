<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Thrown when OTP brute-force protection trips (specs/16): 429 + cooldown. */
class OtpLockedException extends HttpException
{
    public function __construct(string $message = 'Too many attempts. Try again later.')
    {
        parent::__construct(429, $message);
    }
}
