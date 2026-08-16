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
        $reports = array_slice((new Report())->all(), 0, 8);
        $contacts = Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name', [$user['commune_id']])->fetchAll();
        $notifications = Database::query('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5', [$user['id']])->fetchAll();
        $this->view('dashboard/index', compact('stats','reports','contacts','notifications') + ['title'=>'Panel']);
    }
}

