<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Audit
{
    public static function log(
        string $action,
        ?string $entityType = null,
        string|int|null $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            $resolvedUserId = $userId ?? (Auth::user()['id'] ?? null);
            Database::query(
                'INSERT INTO audit_logs (user_id,action,entity_type,entity_id,old_values_json,new_values_json,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)',
                [
                    $resolvedUserId,
                    mb_substr($action, 0, 100),
                    $entityType ? mb_substr($entityType, 0, 80) : null,
                    $entityId !== null ? mb_substr((string) $entityId, 0, 80) : null,
                    $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
                    mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (Throwable) {
            // La auditoría nunca debe interrumpir la operación principal.
        }
    }
}

