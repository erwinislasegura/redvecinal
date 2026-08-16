<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Report;

final class ReportController extends Controller
{
    public function index(): void
    {
        $filters = ['status'=>$_GET['status']??'','category'=>$_GET['category']??'','search'=>trim($_GET['search']??'')];
        $reports = (new Report())->all($filters);
        $this->view('reports/index', ['title'=>'Reportes','reports'=>$reports,'filters'=>$filters]);
    }

    public function create(): void
    {
        $user = Auth::user();
        $types = Database::query('SELECT * FROM report_types WHERE active=1 ORDER BY category,sort_order')->fetchAll();
        $sectors = Database::query("SELECT * FROM sectors WHERE commune_id=? AND status='activo' ORDER BY name", [$user['commune_id']])->fetchAll();
        $allowAnonymous=setting('reports_anonymous','1',(int)$user['commune_id'])==='1';
        $this->view('reports/create', ['title'=>'Nuevo reporte','types'=>$types,'sectors'=>$sectors,'allowAnonymous'=>$allowAnonymous]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $id = $this->saveReport($_POST);
        if (!$id) { $this->rememberInput(); $this->redirect('reportes/nuevo','Completa el tipo, título y descripción del reporte.','danger'); }
        $this->storeMedia($id);
        $this->redirect('reportes/'.$id,'El reporte fue enviado a la red vecinal.');
    }

    public function offlineSync(): void
    {
        $this->validateCsrf();
        $payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = $this->saveReport($payload);
        $this->json($id ? ['ok'=>true,'id'=>$id,'url'=>url('reportes/'.$id)] : ['ok'=>false,'message'=>'Datos incompletos'], $id?201:422);
    }

    private function saveReport(array $input): int
    {
        $user = Auth::user();
        $typeId = (int)($input['report_type_id']??0); $title=trim($input['title']??''); $description=trim($input['description']??'');
        if (!$typeId || !$title || !$description) return 0;
        $type = Database::query('SELECT * FROM report_types WHERE id=? AND active=1',[$typeId])->fetch();
        if (!$type) return 0;
        $id=(new Report())->create([
            'user_id'=>$user['id'],'report_type_id'=>$typeId,'commune_id'=>$user['commune_id'],'sector_id'=>(int)($input['sector_id']??0),
            'title'=>mb_substr($title,0,160),'description'=>$description,'address'=>trim($input['address']??''),
            'latitude'=>is_numeric($input['latitude']??null)?$input['latitude']:null,'longitude'=>is_numeric($input['longitude']??null)?$input['longitude']:null,
            'priority'=>in_array($input['priority']??'', ['baja','media','alta','critica'],true)?$input['priority']:$type['priority_default'],
            'is_anonymous'=>(setting('reports_anonymous','1',(int)$user['commune_id'])==='1'&&!empty($input['is_anonymous']))?1:0,'happened_at'=>!empty($input['happened_at'])?str_replace('T',' ',$input['happened_at']):null,
        ]);
        if(setting('notifications_enabled','1',(int)$user['commune_id'])==='1'){$operators=Database::query("SELECT id FROM users WHERE commune_id=? AND status='activo' AND role_id IN (2,3)",[$user['commune_id']])->fetchAll();foreach($operators as $operator)Database::query('INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,\'new_report\',?,?,?)',[$operator['id'],'Nuevo reporte: '.$title,'Prioridad '.($input['priority']??$type['priority_default']),url('reportes/'.$id)]);}
        Audit::log('reporte.creado','report',$id,null,['title'=>$title,'type_id'=>$typeId,'priority'=>$input['priority']??$type['priority_default']]);
        return $id;
    }

    public function show(string $id): void
    {
        $report=(new Report())->find((int)$id); if(!$report){http_response_code(404);require BASE_PATH.'/app/Views/errors/404.php';return;}
        $comments=Database::query("SELECT rc.*,u.name,u.role_id FROM report_comments rc JOIN users u ON u.id=rc.user_id WHERE rc.report_id=? AND (rc.is_internal=0 OR ?=1) ORDER BY rc.created_at",[$id,Auth::can('reports.manage')?1:0])->fetchAll();
        $history=Database::query('SELECT h.*,u.name FROM report_status_history h JOIN users u ON u.id=h.user_id WHERE h.report_id=? ORDER BY h.created_at DESC',[$id])->fetchAll();
        $dispatches=Database::query('SELECT d.*,u.name AS creator_name FROM dispatches d JOIN users u ON u.id=d.created_by WHERE d.report_id=? ORDER BY d.requested_at DESC',[$id])->fetchAll();
        $media=Database::query('SELECT * FROM report_media WHERE report_id=? ORDER BY created_at',[$id])->fetchAll();
        $operators=Database::query("SELECT u.id,u.name,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.commune_id=? AND r.slug IN ('operador','respondedor','admin_municipal') AND u.status='activo'",[$report['commune_id']])->fetchAll();
        $securityContact=null;if(Auth::can('reports.manage')){try{$securityContact=Database::query('SELECT name,relationship,phone FROM user_emergency_contacts WHERE user_id=? LIMIT 1',[$report['user_id']])->fetch()?:null;}catch(\Throwable){$securityContact=null;}}
        $this->view('reports/show_wrapper',compact('report','comments','history','dispatches','operators','media','securityContact')+['title'=>$report['public_code']]);
    }

    public function comment(string $id): void
    {
        $this->validateCsrf(); $report=(new Report())->find((int)$id); if(!$report)$this->redirect('reportes','Reporte no encontrado.','danger');
        $body=trim($_POST['body']??''); if($body){$internal=(Auth::can('reports.manage')&&!empty($_POST['is_internal']))?1:0;Database::query('INSERT INTO report_comments (report_id,user_id,body,is_internal) VALUES (?,?,?,?)',[$id,Auth::user()['id'],$body,$internal]);Audit::log('reporte.comentario_agregado','report',$id,null,['internal'=>$internal]);}
        $this->redirect('reportes/'.$id,'Comentario agregado.');
    }

    public function status(string $id): void
    {
        $this->validateCsrf(); $report=(new Report())->find((int)$id); if(!$report)$this->redirect('reportes','Reporte no encontrado.','danger');
        $status=$_POST['status']??''; $allowed=['nuevo','validando','asignado','en_proceso','resuelto','cerrado','rechazado']; if(!in_array($status,$allowed,true))$this->redirect('reportes/'.$id,'Estado inválido.','danger');
        $assigned=(int)($_POST['assigned_to']??0)?:null;
        Database::query('UPDATE reports SET status=?,assigned_to=?,resolved_at=IF(? IN (\'resuelto\',\'cerrado\'),NOW(),NULL) WHERE id=?',[$status,$assigned,$status,$id]);
        Database::query('INSERT INTO report_status_history (report_id,user_id,old_status,new_status,notes) VALUES (?,?,?,?,?)',[$id,Auth::user()['id'],$report['status'],$status,trim($_POST['notes']??'')]);
        Audit::log('reporte.estado_actualizado','report',$id,['status'=>$report['status'],'assigned_to'=>$report['assigned_to']],['status'=>$status,'assigned_to'=>$assigned]);
        $this->redirect('reportes/'.$id,'Estado actualizado.');
    }

    public function dispatch(string $id): void
    {
        $this->validateCsrf(); $report=(new Report())->find((int)$id); if(!$report)$this->redirect('reportes','Reporte no encontrado.','danger');
        $service=$_POST['service']??'otro'; $allowed=['seguridad_municipal','carabineros','bomberos','ambulancia','transito','aseo','alumbrado','otro']; if(!in_array($service,$allowed,true))$service='otro';
        Database::query('INSERT INTO dispatches (report_id,created_by,service,unit_name,contact_name,notes) VALUES (?,?,?,?,?,?)',[$id,Auth::user()['id'],$service,trim($_POST['unit_name']??''),trim($_POST['contact_name']??''),trim($_POST['notes']??'')]);
        Database::query("UPDATE reports SET status='asignado' WHERE id=? AND status IN ('nuevo','validando')",[$id]);
        Audit::log('reporte.servicio_despachado','report',$id,null,['service'=>$service,'unit_name'=>trim($_POST['unit_name']??'')]);
        $this->redirect('reportes/'.$id,'Servicio despachado y registrado.');
    }

    public function media(string $id): void
    {
        $item=Database::query('SELECT m.*,r.id AS report_id FROM report_media m JOIN reports r ON r.id=m.report_id WHERE m.id=?',[$id])->fetch();
        if(!$item||(new Report())->find((int)$item['report_id'])===null){http_response_code(404);exit;}
        $file=BASE_PATH.'/storage/uploads/'.$item['file_path'];
        if(!is_file($file)){http_response_code(404);exit;}
        header('Content-Type: '.$item['mime_type']);
        header('Content-Length: '.filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private function storeMedia(int $reportId): void
    {
        if(empty($_FILES['evidence']['tmp_name'])||($_FILES['evidence']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return;
        $max=(int)config('uploads_max_mb',8)*1024*1024;
        if(($_FILES['evidence']['size']??0)>$max)return;
        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=$finfo->file($_FILES['evidence']['tmp_name']);
        $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4'];
        if(!isset($types[$mime]))return;
        $directory=date('Y/m');$targetDir=BASE_PATH.'/storage/uploads/'.$directory;
        if(!is_dir($targetDir))mkdir($targetDir,0755,true);
        $name=bin2hex(random_bytes(16)).'.'.$types[$mime];$relative=$directory.'/'.$name;
        if(move_uploaded_file($_FILES['evidence']['tmp_name'],$targetDir.'/'.$name))Database::query('INSERT INTO report_media (report_id,user_id,file_path,file_type,mime_type) VALUES (?,?,?,?,?)',[$reportId,Auth::user()['id'],$relative,str_starts_with($mime,'video/')?'video':'imagen',$mime]);
    }
}
