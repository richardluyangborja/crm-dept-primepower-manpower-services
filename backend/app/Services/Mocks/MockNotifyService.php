<?php

namespace App\Services\Mocks;

use App\Models\Notification;
use App\Services\Contracts\NotifyServiceInterface;
use Illuminate\Support\Facades\Log;

/** In-app + log only in v1. No mail/SMS gateway. */
class MockNotifyService implements NotifyServiceInterface
{
    public function send(int $userId, string $type, string $title, string $body, ?string $link = null): void
    {
        Notification::create(compact('userId', 'type', 'title', 'body', 'link') + ['user_id' => $userId]);
        Log::info('[mock] notify', ['user' => $userId, 'type' => $type, 'title' => $title]);
        file_put_contents(
            database_path('fixtures/mock_outbox.json'),
            json_encode(['to' => $userId, 'type' => $type, 'title' => $title, 'at' => now()->toIso8601String()]).PHP_EOL,
            FILE_APPEND
        );
    }
}
