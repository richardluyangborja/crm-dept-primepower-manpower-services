<?php

namespace App\Services\Contracts;

interface NotifyServiceInterface
{
    /** v1: writes to log + notifications table. No real gateway. */
    public function send(int $userId, string $type, string $title, string $body, ?string $link = null): void;
}
