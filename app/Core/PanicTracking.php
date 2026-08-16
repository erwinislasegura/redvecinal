<?php
declare(strict_types=1);

namespace App\Core;

final class PanicTracking
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS panic_trackings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status ENUM('activo','detenido','finalizado','expirado') NOT NULL DEFAULT 'activo',
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            stopped_at DATETIME NULL,
            last_latitude DECIMAL(10,7) NULL,
            last_longitude DECIMAL(10,7) NULL,
            last_accuracy DECIMAL(8,2) NULL,
            last_seen_at DATETIME NULL,
            UNIQUE KEY uq_panic_tracking_report (report_id),
            INDEX idx_panic_tracking_live (status,last_seen_at),
            CONSTRAINT fk_panic_tracking_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
            CONSTRAINT fk_panic_tracking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS panic_tracking_points (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tracking_id BIGINT UNSIGNED NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            accuracy DECIMAL(8,2) NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_panic_point_timeline (tracking_id,recorded_at),
            CONSTRAINT fk_panic_point_tracking FOREIGN KEY (tracking_id) REFERENCES panic_trackings(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$schemaReady = true;
    }

    public static function start(int $reportId, int $userId, ?float $latitude, ?float $longitude, ?float $accuracy): bool
    {
        self::ensureSchema();
        Database::query("INSERT INTO panic_trackings (report_id,user_id,status,last_latitude,last_longitude,last_accuracy,last_seen_at)
            VALUES (?,?,'activo',?,?,?,IF(? IS NULL,NULL,NOW()))
            ON DUPLICATE KEY UPDATE status='activo',stopped_at=NULL,last_latitude=VALUES(last_latitude),last_longitude=VALUES(last_longitude),last_accuracy=VALUES(last_accuracy),last_seen_at=VALUES(last_seen_at)",
            [$reportId,$userId,$latitude,$longitude,$accuracy,$latitude]);
        if ($latitude !== null && $longitude !== null) {
            $trackingId=(int)Database::query('SELECT id FROM panic_trackings WHERE report_id=?',[$reportId])->fetchColumn();
            self::storePoint($trackingId,$latitude,$longitude,$accuracy);
        }
        return true;
    }

    public static function update(int $reportId, int $userId, float $latitude, float $longitude, ?float $accuracy): array
    {
        self::ensureSchema();
        $tracking=Database::query("SELECT pt.*,r.status report_status FROM panic_trackings pt JOIN reports r ON r.id=pt.report_id WHERE pt.report_id=? AND pt.user_id=? LIMIT 1",[$reportId,$userId])->fetch();
        if(!$tracking)return ['ok'=>false,'status'=>404,'message'=>'Seguimiento no encontrado.'];
        if($tracking['status']!=='activo'||in_array($tracking['report_status'],['resuelto','cerrado','rechazado'],true))return ['ok'=>false,'status'=>410,'message'=>'El seguimiento ya finalizó.'];
        if(strtotime((string)$tracking['started_at']) < time()-7200){Database::query("UPDATE panic_trackings SET status='expirado',stopped_at=NOW() WHERE id=?",[$tracking['id']]);return ['ok'=>false,'status'=>410,'message'=>'El seguimiento alcanzó su límite de seguridad.'];}
        Database::query('UPDATE panic_trackings SET last_latitude=?,last_longitude=?,last_accuracy=?,last_seen_at=NOW() WHERE id=?',[$latitude,$longitude,$accuracy,$tracking['id']]);
        Database::query("UPDATE reports SET latitude=?,longitude=?,address=? WHERE id=?",[$latitude,$longitude,'Ubicación GPS en vivo: '.number_format($latitude,7,'.','').', '.number_format($longitude,7,'.',''),$reportId]);
        self::storePoint((int)$tracking['id'],$latitude,$longitude,$accuracy);
        return ['ok'=>true,'status'=>200,'message'=>'Ubicación actualizada.','received_at'=>date(DATE_ATOM)];
    }

    public static function stop(int $reportId, ?int $userId = null, string $status='detenido'): bool
    {
        self::ensureSchema();
        $params=[$status,$reportId];$scope='report_id=?';
        if($userId!==null){$scope.=' AND user_id=?';$params[]=$userId;}
        $statement=Database::query("UPDATE panic_trackings SET status=?,stopped_at=NOW() WHERE $scope AND status='activo'",$params);
        return $statement->rowCount()>0;
    }

    public static function liveFor(array $user): array
    {
        self::ensureSchema();
        Database::query("UPDATE panic_trackings pt JOIN reports r ON r.id=pt.report_id SET pt.status=IF(r.status IN ('resuelto','cerrado','rechazado'),'finalizado','expirado'),pt.stopped_at=NOW() WHERE pt.status='activo' AND (r.status IN ('resuelto','cerrado','rechazado') OR pt.started_at<DATE_SUB(NOW(),INTERVAL 2 HOUR))");
        $params=[];$scope='1=1';if(($user['role_slug']??'')!=='superadmin'){$scope='r.commune_id=?';$params[]=(int)$user['commune_id'];}
        $rows=Database::query("SELECT pt.id tracking_id,pt.report_id,pt.status,pt.started_at,pt.last_latitude,pt.last_longitude,pt.last_accuracy,pt.last_seen_at,r.public_code,r.title,r.address,r.status report_status,u.name reporter_name,u.phone reporter_phone,c.name commune_name FROM panic_trackings pt JOIN reports r ON r.id=pt.report_id JOIN users u ON u.id=pt.user_id JOIN communes c ON c.id=r.commune_id WHERE pt.status='activo' AND $scope ORDER BY pt.started_at DESC",$params)->fetchAll();
        foreach($rows as &$row){$points=Database::query('SELECT latitude,longitude,accuracy,recorded_at FROM panic_tracking_points WHERE tracking_id=? ORDER BY recorded_at DESC,id DESC LIMIT 100',[$row['tracking_id']])->fetchAll();$row['trail']=array_reverse(array_map(static fn(array $point):array=>['lat'=>(float)$point['latitude'],'lng'=>(float)$point['longitude'],'accuracy'=>$point['accuracy']!==null?(float)$point['accuracy']:null,'recorded_at'=>$point['recorded_at']],$points));}
        unset($row);return $rows;
    }

    private static function storePoint(int $trackingId, float $latitude, float $longitude, ?float $accuracy): void
    {
        $last=Database::query('SELECT latitude,longitude,recorded_at FROM panic_tracking_points WHERE tracking_id=? ORDER BY id DESC LIMIT 1',[$trackingId])->fetch();
        if($last&&abs((float)$last['latitude']-$latitude)<0.00001&&abs((float)$last['longitude']-$longitude)<0.00001&&strtotime((string)$last['recorded_at'])>time()-15)return;
        Database::query('INSERT INTO panic_tracking_points (tracking_id,latitude,longitude,accuracy,recorded_at) VALUES (?,?,?,?,NOW())',[$trackingId,$latitude,$longitude,$accuracy]);
    }
}
