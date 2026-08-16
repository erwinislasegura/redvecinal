<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;

final class DispatchController extends Controller
{
    private const STATUSES = ['solicitado','aceptado','en_camino','en_sitio','finalizado','cancelado'];
    private const SERVICES = ['seguridad_municipal','carabineros','bomberos','ambulancia','transito','aseo','alumbrado','otro'];

    public function index(): void
    {
        $user = Auth::user();
        $filters = [
            'status' => in_array($_GET['status'] ?? '', self::STATUSES, true) ? $_GET['status'] : '',
            'service' => in_array($_GET['service'] ?? '', self::SERVICES, true) ? $_GET['service'] : '',
            'search' => trim($_GET['search'] ?? ''),
        ];
        $where = [];
        $params = [];
        if ($user['role_slug'] !== 'superadmin') {
            $where[] = 'r.commune_id=?';
            $params[] = $user['commune_id'];
        }
        if ($filters['status'] !== '') { $where[] = 'd.status=?'; $params[] = $filters['status']; }
        if ($filters['service'] !== '') { $where[] = 'd.service=?'; $params[] = $filters['service']; }
        if ($filters['search'] !== '') {
            $where[] = '(r.public_code LIKE ? OR r.title LIKE ? OR d.unit_name LIKE ? OR d.contact_name LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        $sql = "SELECT d.*,r.public_code,r.title,r.priority,r.status AS report_status,r.address,
                       rt.name AS type_name,c.name AS commune_name,u.name AS creator_name
                FROM dispatches d
                JOIN reports r ON r.id=d.report_id
                JOIN report_types rt ON rt.id=r.report_type_id
                JOIN communes c ON c.id=r.commune_id
                JOIN users u ON u.id=d.created_by";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY FIELD(d.status,\'solicitado\',\'aceptado\',\'en_camino\',\'en_sitio\',\'finalizado\',\'cancelado\'),d.requested_at DESC LIMIT 300';
        $dispatches = Database::query($sql, $params)->fetchAll();

        $scope = $user['role_slug'] === 'superadmin' ? '1=1' : 'r.commune_id=' . (int)$user['commune_id'];
        $summaryRows = Database::query("SELECT d.status,COUNT(*) total FROM dispatches d JOIN reports r ON r.id=d.report_id WHERE $scope GROUP BY d.status")->fetchAll();
        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($summaryRows as $row) $summary[$row['status']] = (int)$row['total'];

        $this->view('dispatches/index', compact('dispatches','filters','summary') + ['title'=>'Despacho de servicios']);
    }

    public function status(string $id): void
    {
        $this->validateCsrf();
        $status = $_POST['status'] ?? '';
        if (!in_array($status, self::STATUSES, true)) $this->redirect('despachos','Estado de despacho inválido.','danger');
        $dispatch = Database::query('SELECT d.*,r.commune_id,r.status AS report_status FROM dispatches d JOIN reports r ON r.id=d.report_id WHERE d.id=?', [$id])->fetch();
        $user = Auth::user();
        if (!$dispatch || ($user['role_slug'] !== 'superadmin' && (int)$dispatch['commune_id'] !== (int)$user['commune_id'])) {
            $this->redirect('despachos','Despacho no encontrado.','danger');
        }
        Database::query("UPDATE dispatches SET status=?,arrived_at=CASE WHEN ?='en_sitio' THEN COALESCE(arrived_at,NOW()) ELSE arrived_at END,finished_at=CASE WHEN ? IN ('finalizado','cancelado') THEN COALESCE(finished_at,NOW()) ELSE NULL END WHERE id=?", [$status,$status,$status,$id]);
        if (in_array($status, ['aceptado','en_camino','en_sitio'], true) && !in_array($dispatch['report_status'], ['resuelto','cerrado','rechazado'], true)) {
            Database::query("UPDATE reports SET status='en_proceso' WHERE id=?", [$dispatch['report_id']]);
        }
        Audit::log('despacho.estado_actualizado','dispatch',(int)$id,['status'=>$dispatch['status']],['status'=>$status]);
        $this->redirect('despachos','Estado del despacho actualizado.');
    }
}
