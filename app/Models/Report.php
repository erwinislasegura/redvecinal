<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

final class Report extends Model
{
    public function all(array $filters = []): array
    {
        $user = Auth::user();
        $where = ['1=1'];
        $params = [];
        if (!Auth::can('reports.commune')) {
            $where[] = 'r.user_id = ?';
            $params[] = $user['id'];
        } elseif ($user['role_slug'] !== 'superadmin') {
            $where[] = 'r.commune_id = ?';
            $params[] = $user['commune_id'];
        }
        if (!empty($filters['status'])) { $where[] = 'r.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['category'])) { $where[] = 'rt.category = ?'; $params[] = $filters['category']; }
        if (!empty($filters['search'])) {
            $where[] = '(r.public_code LIKE ? OR r.title LIKE ? OR r.address LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            array_push($params, $term, $term, $term);
        }
        $sql = "SELECT r.*, rt.name AS type_name, rt.category, rt.color, u.name AS reporter_name, c.name AS commune_name,
                       a.name AS assigned_name
                FROM reports r JOIN report_types rt ON rt.id=r.report_type_id JOIN users u ON u.id=r.user_id
                JOIN communes c ON c.id=r.commune_id LEFT JOIN users a ON a.id=r.assigned_to
                WHERE " . implode(' AND ', $where) . ' ORDER BY FIELD(r.priority,\'critica\',\'alta\',\'media\',\'baja\'), r.created_at DESC LIMIT 200';
        return Database::query($sql, $params)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $report = Database::query(
            "SELECT r.*,rt.name AS type_name,rt.category,rt.color,u.name AS reporter_name,u.phone AS reporter_phone,
                    c.name AS commune_name,s.name AS sector_name,a.name AS assigned_name
             FROM reports r JOIN report_types rt ON rt.id=r.report_type_id JOIN users u ON u.id=r.user_id
             JOIN communes c ON c.id=r.commune_id LEFT JOIN sectors s ON s.id=r.sector_id LEFT JOIN users a ON a.id=r.assigned_to
             WHERE r.id=? LIMIT 1", [$id]
        )->fetch();
        if (!$report) return null;
        $user = Auth::user();
        if (!Auth::can('reports.commune') && (int)$report['user_id'] !== (int)$user['id']) return null;
        if ($user['role_slug'] !== 'superadmin' && Auth::can('reports.commune') && (int)$report['commune_id'] !== (int)$user['commune_id']) return null;
        return $report;
    }

    public function create(array $data): int
    {
        $code = 'RV-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        Database::query(
            'INSERT INTO reports (public_code,user_id,report_type_id,commune_id,sector_id,title,description,address,latitude,longitude,priority,is_anonymous,happened_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$code,$data['user_id'],$data['report_type_id'],$data['commune_id'],$data['sector_id'] ?: null,$data['title'],$data['description'],$data['address'] ?: null,$data['latitude'] ?: null,$data['longitude'] ?: null,$data['priority'],$data['is_anonymous'],$data['happened_at'] ?: null]
        );
        $id = (int) $this->db->lastInsertId();
        Database::query('INSERT INTO report_status_history (report_id,user_id,old_status,new_status,notes) VALUES (?,?,NULL,\'nuevo\',?)', [$id,$data['user_id'],'Reporte creado']);
        return $id;
    }
}

