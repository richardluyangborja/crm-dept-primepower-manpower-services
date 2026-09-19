<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Attach to any write model: $model->audit('created', $userId, $meta). Never hard-fail a request on audit errors. */
trait HasAuditLog
{
    public function audit(string $action, ?int $userId, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'entity' => $this->getTable(),
                'entity_id' => $this->getKey(),
                'meta' => $meta,
            ]);
        } catch (\Throwable) {
            // audit must never break the request
        }
    }
}
