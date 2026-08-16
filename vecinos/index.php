<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$scriptName=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/vecinos/index.php');
$_SERVER['SCRIPT_NAME']=rtrim(dirname(dirname($scriptName)),'/').'/index.php';
define('BASE_PATH',$root);
require $root.'/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\PanicAlert;
use App\Core\PanicTracking;
use App\Models\Report;

if(!Auth::check()){$_SESSION['_flash']['message']='Inicia sesión para abrir la aplicación vecinal.';$_SESSION['_flash']['type']='warning';header('Location: '.url('ingresar?next=vecinos'));exit;}
$user=Auth::user();
$requestedPage=(string)($_GET['page']??'inicio');
$page=in_array($requestedPage,['inicio','reportar','reportes','mascotas','seguridad','perfil'],true)?$requestedPage:'inicio';
$redirect=static function(string $target='inicio',string $message=''): never {if($message)$_SESSION['neighbor_flash']=$message;header('Location: '.url('vecinos/').($target==='inicio'?'':'?page='.$target));exit;};
$ensureContactTable=static function(): void {Database::connection()->exec("CREATE TABLE IF NOT EXISTS user_emergency_contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,relationship VARCHAR(80) NOT NULL,phone VARCHAR(30) NOT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_user_emergency_contact (user_id),CONSTRAINT fk_uec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");};
$loadReportTracking=static function(int $userId): array {return Database::query("SELECT r.*,rt.name type_name,rt.color,a.name assigned_name,
    d.id dispatch_id,d.service dispatch_service,d.unit_name dispatch_unit,d.contact_name dispatch_contact,d.status dispatch_status,d.requested_at dispatch_requested_at,d.arrived_at dispatch_arrived_at,d.finished_at dispatch_finished_at
    FROM reports r JOIN report_types rt ON rt.id=r.report_type_id LEFT JOIN users a ON a.id=r.assigned_to
    LEFT JOIN dispatches d ON d.id=(SELECT d2.id FROM dispatches d2 WHERE d2.report_id=r.id ORDER BY d2.requested_at DESC,d2.id DESC LIMIT 1)
    WHERE r.user_id=? ORDER BY r.created_at DESC LIMIT 50",[$userId])->fetchAll();};

if($_SERVER['REQUEST_METHOD']==='GET'&&($_GET['action']??'')==='tracking'){
    $serviceLabels=['seguridad_municipal'=>'Seguridad municipal','carabineros'=>'Carabineros','bomberos'=>'Bomberos','ambulancia'=>'Ambulancia / SAMU','transito'=>'Tránsito','aseo'=>'Aseo y ornato','alumbrado'=>'Alumbrado público','otro'=>'Otro servicio'];
    $statusLabels=['solicitado'=>'Solicitado','aceptado'=>'Aceptado','en_camino'=>'En camino','en_sitio'=>'En el lugar','finalizado'=>'Finalizado','cancelado'=>'Cancelado'];
    $items=array_map(static fn(array $report):array=>['id'=>(int)$report['id'],'status'=>$report['status'],'assigned_name'=>$report['assigned_name']??'',
        'dispatch'=>$report['dispatch_id']?['service'=>$report['dispatch_service'],'service_label'=>$serviceLabels[$report['dispatch_service']]??$report['dispatch_service'],'unit'=>$report['dispatch_unit']??'','contact'=>$report['dispatch_contact']??'','status'=>$report['dispatch_status'],'status_label'=>$statusLabels[$report['dispatch_status']]??$report['dispatch_status'],'requested_at'=>$report['dispatch_requested_at']]:null],$loadReportTracking((int)$user['id']));
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['ok'=>true,'reports'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_GET['action']??'';Csrf::verify($_POST['_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??null));
    if($action==='report'){
        $typeId=(int)($_POST['report_type_id']??0);$title=trim($_POST['title']??'');$description=trim($_POST['description']??'');
        $type=Database::query('SELECT * FROM report_types WHERE id=? AND active=1',[$typeId])->fetch();
        if(!$type||!$title||!$description)$redirect('reportar','Completa el tipo, título y descripción.');
        $id=(new Report())->create(['user_id'=>$user['id'],'report_type_id'=>$typeId,'commune_id'=>$user['commune_id'],'sector_id'=>(int)($_POST['sector_id']??0),'title'=>mb_substr($title,0,160),'description'=>$description,'address'=>trim($_POST['address']??''),'latitude'=>is_numeric($_POST['latitude']??null)?$_POST['latitude']:null,'longitude'=>is_numeric($_POST['longitude']??null)?$_POST['longitude']:null,'priority'=>in_array($_POST['priority']??'', ['baja','media','alta','critica'],true)?$_POST['priority']:$type['priority_default'],'is_anonymous'=>!empty($_POST['is_anonymous'])?1:0,'happened_at'=>date('Y-m-d H:i:s')]);
        $operators=Database::query("SELECT id FROM users WHERE commune_id=? AND status='activo' AND role_id IN (2,3)",[$user['commune_id']])->fetchAll();foreach($operators as $operator)Database::query("INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,'new_report',?,?,?)",[$operator['id'],'Nuevo reporte vecinal: '.$title,'Prioridad '.$type['priority_default'],url('reportes/'.$id)]);
        Audit::log('app_vecinos.reporte_creado','report',$id,null,['title'=>$title]);$redirect('reportes','Tu denuncia fue enviada correctamente.');
    }
    if($action==='panic'){
        $input=str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?(json_decode(file_get_contents('php://input'),true)?:[]):$_POST;
        $type=Database::query("SELECT id FROM report_types WHERE active=1 AND category IN ('seguridad','emergencia') ORDER BY CASE WHEN name LIKE '%Robo%' THEN 0 ELSE 1 END LIMIT 1")->fetch();
        if(!$type){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>'No existe un tipo de emergencia activo.']);exit;}
        $latitude=is_numeric($input['latitude']??null)?(float)$input['latitude']:null;$longitude=is_numeric($input['longitude']??null)?(float)$input['longitude']:null;
        if($latitude!==null&&($latitude < -90||$latitude > 90))$latitude=null;if($longitude!==null&&($longitude < -180||$longitude > 180))$longitude=null;
        $hasGps=$latitude!==null&&$longitude!==null;$accuracy=is_numeric($input['accuracy']??null)?min(100000,max(0,(int)round((float)$input['accuracy']))):null;
        $capturedAt=mb_substr(trim((string)($input['captured_at']??'')),0,40);$description='Botón de pánico activado desde la aplicación vecinal. '.($hasGps?'Ubicación GPS capturada desde el dispositivo'.($accuracy!==null?' con precisión aproximada de ±'.$accuracy.' m':'').($capturedAt!==''?' a las '.$capturedAt:'').'.':'El dispositivo no permitió obtener ubicación GPS.');
        $address=$hasGps?'Ubicación GPS: '.number_format($latitude,7,'.','').', '.number_format($longitude,7,'.',''):('GPS no disponible · '.($user['address']??'domicilio no informado'));
        $id=(new Report())->create(['user_id'=>$user['id'],'report_type_id'=>$type['id'],'commune_id'=>$user['commune_id'],'sector_id'=>$user['sector_id']?:null,'title'=>'ALERTA DE PÁNICO','description'=>$description,'address'=>$address,'latitude'=>$latitude,'longitude'=>$longitude,'priority'=>'critica','is_anonymous'=>0,'happened_at'=>date('Y-m-d H:i:s')]);
        $trackingActive=false;try{$trackingActive=PanicTracking::start($id,(int)$user['id'],$latitude,$longitude,$accuracy!==null?(float)$accuracy:null);}catch(\Throwable $error){error_log('RedVecinal seguimiento pánico: '.$error->getMessage());}
        PanicAlert::notify($user,$id);
        Audit::log('app_vecinos.panico','report',$id,null,['latitude'=>$latitude,'longitude'=>$longitude,'accuracy'=>$accuracy,'captured_at'=>$capturedAt]);header('Content-Type: application/json');http_response_code(201);echo json_encode(['ok'=>true,'id'=>$id,'location_shared'=>$hasGps,'tracking_active'=>$trackingActive,'message'=>$hasGps?($trackingActive?'Alerta enviada. Tu ubicación se está compartiendo en vivo.':'Alerta y ubicación inicial enviadas; el seguimiento continuo no está disponible.'):'Alerta enviada, pero el teléfono no entregó la ubicación GPS.']);exit;
    }
    if($action==='panic_location'){
        $input=json_decode(file_get_contents('php://input'),true)?:[];$reportId=(int)($input['report_id']??0);
        $latitude=is_numeric($input['latitude']??null)?(float)$input['latitude']:null;$longitude=is_numeric($input['longitude']??null)?(float)$input['longitude']:null;$accuracy=is_numeric($input['accuracy']??null)?min(100000,max(0,(float)$input['accuracy'])):null;
        if(!$reportId||$latitude===null||$longitude===null||$latitude < -90||$latitude > 90||$longitude < -180||$longitude > 180){header('Content-Type: application/json');http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Ubicación inválida.']);exit;}
        $result=PanicTracking::update($reportId,(int)$user['id'],$latitude,$longitude,$accuracy);header('Content-Type: application/json; charset=utf-8');http_response_code((int)$result['status']);echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    if($action==='panic_stop'){
        $input=json_decode(file_get_contents('php://input'),true)?:$_POST;$reportId=(int)($input['report_id']??0);$stopped=$reportId?PanicTracking::stop($reportId,(int)$user['id']):false;
        Audit::log('app_vecinos.panico_seguimiento_detenido','report',$reportId,null,['stopped'=>$stopped]);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'stopped'=>$stopped,'message'=>'Dejaste de compartir tu ubicación.'],JSON_UNESCAPED_UNICODE);exit;
    }
    if($action==='pet'){
        $name=trim($_POST['name']??'');$species=trim($_POST['species']??'');if(!$name||!$species)$redirect('mascotas','Completa el nombre y tipo de mascota.');
        $token=sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(6)));
        Database::query("INSERT INTO pets (user_id,commune_id,name,species,breed,color,description,qr_token,last_seen_address,status,lost_at) VALUES (?,?,?,?,?,?,?,?,?,? ,IF(?='perdida',NOW(),NULL))",[$user['id'],$user['commune_id'],$name,$species,trim($_POST['breed']??''),trim($_POST['color']??''),trim($_POST['description']??''),$token,trim($_POST['last_seen_address']??''),in_array($_POST['status']??'', ['en_casa','perdida','encontrada'],true)?$_POST['status']:'en_casa',$_POST['status']??'']);
        $petId=(int)Database::connection()->lastInsertId();Audit::log('app_vecinos.mascota_creada','pet',$petId,null,['name'=>$name]);
        $_SESSION['neighbor_flash']='Mascota registrada. Su credencial QR ya está lista para imprimir.';header('Location: '.url('mascotas/'.$petId.'/credencial'));exit;
    }
    if($action==='pet_status'){
        $petId=(int)($_POST['pet_id']??0);$status=$_POST['status']??'';
        $pet=Database::query('SELECT id,status FROM pets WHERE id=? AND user_id=?',[$petId,$user['id']])->fetch();
        if(!$pet||!in_array($status,['en_casa','perdida','encontrada'],true))$redirect('mascotas','No fue posible actualizar la mascota.');
        Database::query("UPDATE pets SET status=?,last_seen_address=?,lost_at=IF(?='perdida',COALESCE(lost_at,NOW()),lost_at) WHERE id=?",[$status,trim($_POST['last_seen_address']??''),$status,$petId]);
        Audit::log('app_vecinos.mascota_estado','pet',$petId,['status'=>$pet['status']],['status'=>$status]);$redirect('mascotas','Estado de la mascota actualizado.');
    }
    if($action==='device'){
        $name=trim($_POST['name']??'');$type=$_POST['type']??'otro';if(!$name||!in_array($type,['camara','alarma','sensor','boton_panico','otro'],true))$redirect('seguridad','Completa los datos del dispositivo.');
        Database::query("INSERT INTO devices (user_id,commune_id,name,type,location,protocol,connection_url,webhook_token,status) VALUES (?,?,?,?,?,'manual',?,?,'activo')",[$user['id'],$user['commune_id'],$name,$type,trim($_POST['location']??''),trim($_POST['connection_url']??''),bin2hex(random_bytes(32))]);
        Audit::log('app_vecinos.dispositivo_creado','device',(int)Database::connection()->lastInsertId(),null,['name'=>$name,'type'=>$type]);$redirect('seguridad','Dispositivo registrado.');
    }
    if($action==='profile'){
        $phone=trim($_POST['phone']??'');$address=trim($_POST['address']??'');$sectorId=(int)($_POST['sector_id']??0);if(strlen(preg_replace('/\D/','',$phone))<9||!$address)$redirect('perfil','Completa un teléfono y dirección válidos.');
        if($sectorId&&!Database::query("SELECT COUNT(*) FROM sectors WHERE id=? AND commune_id=? AND status='activo'",[$sectorId,$user['commune_id']])->fetchColumn())$sectorId=0;
        Database::query('UPDATE users SET phone=?,address=?,sector_id=? WHERE id=?',[$phone,$address,$sectorId?:null,$user['id']]);$ensureContactTable();
        Database::query('INSERT INTO user_emergency_contacts (user_id,name,relationship,phone) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),relationship=VALUES(relationship),phone=VALUES(phone)',[$user['id'],trim($_POST['emergency_name']??''),trim($_POST['emergency_relationship']??''),trim($_POST['emergency_phone']??'')]);
        Audit::log('app_vecinos.perfil_actualizado','user',$user['id']);$redirect('perfil','Ficha de seguridad actualizada.');
    }
}

$types=Database::query('SELECT * FROM report_types WHERE active=1 ORDER BY category,sort_order')->fetchAll();
$sectors=Database::query("SELECT * FROM sectors WHERE commune_id=? AND status='activo' ORDER BY name",[$user['commune_id']])->fetchAll();
$reports=$loadReportTracking((int)$user['id']);
$pets=Database::query('SELECT * FROM pets WHERE user_id=? ORDER BY updated_at DESC',[$user['id']])->fetchAll();
$devices=Database::query('SELECT * FROM devices WHERE user_id=? ORDER BY updated_at DESC',[$user['id']])->fetchAll();
$contacts=Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name',[$user['commune_id']])->fetchAll();
$securityContact=null;try{$securityContact=Database::query('SELECT * FROM user_emergency_contacts WHERE user_id=?',[$user['id']])->fetch()?:null;}catch(Throwable){}
$stats=['active'=>count(array_filter($reports,fn($r)=>!in_array($r['status'],['resuelto','cerrado','rechazado'],true))),'resolved'=>count(array_filter($reports,fn($r)=>in_array($r['status'],['resuelto','cerrado'],true))),'pets'=>count($pets),'devices'=>count($devices)];
$serviceLabels=['seguridad_municipal'=>'Seguridad municipal','carabineros'=>'Carabineros','bomberos'=>'Bomberos','ambulancia'=>'Ambulancia / SAMU','transito'=>'Tránsito','aseo'=>'Aseo y ornato','alumbrado'=>'Alumbrado público','otro'=>'Otro servicio'];
$dispatchStatusLabels=['solicitado'=>'Solicitado','aceptado'=>'Aceptado','en_camino'=>'En camino','en_sitio'=>'En el lugar','finalizado'=>'Finalizado','cancelado'=>'Cancelado'];
$flash=$_SESSION['neighbor_flash']??'';unset($_SESSION['neighbor_flash']);
require __DIR__.'/views/app.php';
