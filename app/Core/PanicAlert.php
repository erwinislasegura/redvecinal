<?php
declare(strict_types=1);

namespace App\Core;

final class PanicAlert
{
    /**
     * Crea una alerta persistente para cada operador habilitado de la comuna.
     * Los superadministradores reciben alertas de todas las comunas.
     */
    public static function notify(array $sourceUser, int $reportId): void
    {
        $communeId = (int) ($sourceUser['commune_id'] ?? 0);
        if (!$communeId || setting('notifications_enabled', '1', $communeId) !== '1') {
            return;
        }

        $recipients = Database::query(
            "SELECT DISTINCT u.id
             FROM users u
             JOIN roles ro ON ro.id=u.role_id
             LEFT JOIN role_permissions rp ON rp.role_id=u.role_id
             LEFT JOIN permissions p ON p.id=rp.permission_id AND p.slug='reports.manage'
             WHERE u.status='activo'
               AND (ro.slug='superadmin' OR (u.commune_id=? AND p.id IS NOT NULL))",
            [$communeId]
        )->fetchAll();

        $name = trim((string) ($sourceUser['name'] ?? 'Vecino/a'));
        $phone = trim((string) ($sourceUser['phone'] ?? ''));
        $message = 'Activada por ' . $name . ($phone !== '' ? ' · ' . $phone : '');
        $reportUrl = url('reportes/' . $reportId);

        foreach ($recipients as $recipient) {
            Database::query(
                "INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,'panic','ALERTA DE PÁNICO',?,?)",
                [(int) $recipient['id'], $message, $reportUrl]
            );
        }
    }
}
