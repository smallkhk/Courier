<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /** Keys that must never be written to the audit log. */
    private const REDACT = ['password', 'password_confirmation', 'token', 'secret', 'card', 'cvv', 'delivery_code', 'signature'];

    public static function log(string $action, ?Model $entity = null, array $metadata = [], ?int $actorId = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey() && is_numeric($entity->getKey()) ? $entity->getKey() : null,
            'ip' => $request?->ip(),
            'metadata' => self::redact($metadata),
            'created_at' => now(),
        ]);
    }

    private static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            foreach (self::REDACT as $bad) {
                if (is_string($k) && str_contains(strtolower($k), $bad)) {
                    $data[$k] = '[redacted]';

                    continue 2;
                }
            }
            if (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }

        return $data;
    }
}
