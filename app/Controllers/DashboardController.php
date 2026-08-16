<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\PanicTracking;
use App\Models\Report;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $scope = $user['role_slug'] === 'superadmin' ? '1=1' : 'commune_id=' . (int)$user['commune_id'];
        if (!Auth::can('reports.commune')) $scope = 'user_id=' . (int)$user['id'];
        $stats = [
            'open' => (int) Database::query("SELECT COUNT(*) FROM reports WHERE $scope AND status IN ('nuevo','validando','asignado','en_proceso')")->fetchColumn(),
            'resolved' => (int) Database::query("SELECT COUNT(*) FROM reports WHERE $scope AND status IN ('resuelto','cerrado') AND created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn(),
            'pets' => (int) Database::query("SELECT COUNT(*) FROM pets WHERE " . ($user['role_slug']==='superadmin'?'1=1':'commune_id='.(int)$user['commune_id']) . " AND status='perdida'")->fetchColumn(),
            'users' => (int) Database::query("SELECT COUNT(*) FROM users WHERE " . ($user['role_slug']==='superadmin'?'1=1':'commune_id='.(int)$user['commune_id']) . " AND status='activo'")->fetchColumn(),
        ];
        $allReports = (new Report())->all();
        $reports = array_slice($allReports, 0, 8);
        $mapReports = array_values(array_map(static fn(array $report): array => [
            'id'=>(int)$report['id'],'code'=>$report['public_code'],'title'=>$report['title'],'type'=>$report['type_name'],
            'priority'=>$report['priority'],'status'=>$report['status'],'color'=>$report['color'],'address'=>$report['address'],
            'lat'=>(float)$report['latitude'],'lng'=>(float)$report['longitude'],'url'=>url('reportes/'.$report['id'])
        ],array_filter($allReports,static fn(array $report): bool => is_numeric($report['latitude'])&&is_numeric($report['longitude']))));
        $configuredCommuneId=(int)setting('map_commune_id',(string)$user['commune_id'],(int)$user['commune_id']);
        $configuredCommune=$configuredCommuneId?Database::query("SELECT name,region FROM communes WHERE id=? AND status='activa'",[$configuredCommuneId])->fetch():false;
        $mapConfig=['lat'=>(float)setting('map_center_lat','-37.4689',(int)$user['commune_id']),'lng'=>(float)setting('map_center_lng','-72.3527',(int)$user['commune_id']),'zoom'=>(int)setting('map_zoom','13',(int)$user['commune_id']),'commune'=>$configuredCommune['name']??$user['commune_name']??'Comuna'];
        $contacts = Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name', [$user['commune_id']])->fetchAll();
        $notifications = Database::query('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5', [$user['id']])->fetchAll();
        $this->view('dashboard/index', compact('stats','reports','contacts','notifications','mapReports','mapConfig') + ['title'=>'Panel','useMap'=>true]);
    }

    public function panicAlerts(): void
    {
        $user = Auth::user();
        $alerts = Database::query(
            "SELECT n.id notification_id,n.title,n.message,n.action_url,n.created_at,
                    r.id report_id,r.public_code,r.address,r.latitude,r.longitude,r.status,
                    reporter.name reporter_name,reporter.phone reporter_phone,c.name commune_name
             FROM notifications n
             LEFT JOIN reports r ON r.id=CAST(SUBSTRING_INDEX(n.action_url,'/',-1) AS UNSIGNED)
             LEFT JOIN users reporter ON reporter.id=r.user_id
             LEFT JOIN communes c ON c.id=r.commune_id
             WHERE n.user_id=? AND n.type='panic' AND n.read_at IS NULL
             ORDER BY n.created_at DESC,n.id DESC LIMIT 5",
            [(int) $user['id']]
        )->fetchAll();

        $payload = array_map(static function (array $alert): array {
            $created = strtotime((string) $alert['created_at']);
            return [
                'id' => (int) $alert['notification_id'],
                'report_id' => (int) ($alert['report_id'] ?? 0),
                'code' => (string) ($alert['public_code'] ?? ''),
                'title' => (string) $alert['title'],
                'message' => (string) $alert['message'],
                'reporter' => (string) ($alert['reporter_name'] ?? 'Vecino/a'),
                'phone' => (string) ($alert['reporter_phone'] ?? ''),
                'address' => (string) ($alert['address'] ?? 'Ubicación no informada'),
                'commune' => (string) ($alert['commune_name'] ?? ''),
                'latitude' => is_numeric($alert['latitude'] ?? null) ? (float) $alert['latitude'] : null,
                'longitude' => is_numeric($alert['longitude'] ?? null) ? (float) $alert['longitude'] : null,
                'status' => (string) ($alert['status'] ?? 'nuevo'),
                'created_at' => $created ? date(DATE_ATOM, $created) : (string) $alert['created_at'],
                'action_url' => (string) $alert['action_url'],
            ];
        }, $alerts);

        $this->json(['ok' => true, 'alerts' => $payload]);
    }

    public function acknowledgePanic(string $id): void
    {
        $this->validateCsrf();
        $user = Auth::user();
        $notificationId = (int) $id;
        $notification = Database::query(
            "SELECT id,action_url FROM notifications WHERE id=? AND user_id=? AND type='panic' AND read_at IS NULL",
            [$notificationId, (int) $user['id']]
        )->fetch();
        if (!$notification) {
            $this->json(['ok' => false, 'message' => 'La alerta ya fue confirmada o no está disponible.'], 404);
        }

        Database::query('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?', [$notificationId, (int) $user['id']]);
        Audit::log('panico.alerta_confirmada', 'notification', $notificationId, null, ['action_url' => $notification['action_url']]);
        $this->json(['ok' => true, 'message' => 'Recepción confirmada.']);
    }

    public function panicTracking(): void
    {
        try{$trackings=PanicTracking::liveFor(Auth::user());}catch(\Throwable $error){error_log('RedVecinal mapa seguimiento pánico: '.$error->getMessage());$this->json(['ok'=>false,'trackings'=>[],'message'=>'Seguimiento GPS no disponible.'],503);}
        $items=array_map(static function(array $item):array{return [
            'tracking_id'=>(int)$item['tracking_id'],'report_id'=>(int)$item['report_id'],'code'=>$item['public_code'],'title'=>$item['title'],
            'reporter'=>$item['reporter_name'],'phone'=>$item['reporter_phone'],'commune'=>$item['commune_name'],'address'=>$item['address'],
            'latitude'=>is_numeric($item['last_latitude'])?(float)$item['last_latitude']:null,'longitude'=>is_numeric($item['last_longitude'])?(float)$item['last_longitude']:null,
            'accuracy'=>is_numeric($item['last_accuracy'])?(float)$item['last_accuracy']:null,'started_at'=>$item['started_at'],'last_seen_at'=>$item['last_seen_at'],
            'url'=>url('reportes/'.$item['report_id']),'trail'=>$item['trail'],
        ];},$trackings);
        $this->json(['ok'=>true,'trackings'=>$items,'server_time'=>date(DATE_ATOM)]);
    }
}
