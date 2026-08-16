<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
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
        $mapConfig=['lat'=>(float)setting('map_center_lat','-37.4689',(int)$user['commune_id']),'lng'=>(float)setting('map_center_lng','-72.3527',(int)$user['commune_id']),'zoom'=>(int)setting('map_zoom','13',(int)$user['commune_id']),'commune'=>$user['commune_name']??'Comuna'];
        $contacts = Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name', [$user['commune_id']])->fetchAll();
        $notifications = Database::query('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5', [$user['id']])->fetchAll();
        $this->view('dashboard/index', compact('stats','reports','contacts','notifications','mapReports','mapConfig') + ['title'=>'Panel','useMap'=>true]);
    }
}
