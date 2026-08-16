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
use App\Models\Report;

if(!Auth::check()){$_SESSION['_flash']['message']='Inicia sesión para abrir la aplicación vecinal.';$_SESSION['_flash']['type']='warning';header('Location: '.url('ingresar?next=vecinos'));exit;}
$user=Auth::user();
$page=in_array($_GET['page']??'inicio',['inicio','reportar','reportes','mascotas','seguridad','perfil'],true)?$_GET['page']:'inicio';
$redirect=static function(string $target='inicio',string $message=''): never {if($message)$_SESSION['neighbor_flash']=$message;header('Location: '.url('vecinos/').($target==='inicio'?'':'?page='.$target));exit;};
$ensureContactTable=static function(): void {Database::connection()->exec("CREATE TABLE IF NOT EXISTS user_emergency_contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,relationship VARCHAR(80) NOT NULL,phone VARCHAR(30) NOT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_user_emergency_contact (user_id),CONSTRAINT fk_uec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");};

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
        $id=(new Report())->create(['user_id'=>$user['id'],'report_type_id'=>$type['id'],'commune_id'=>$user['commune_id'],'sector_id'=>$user['sector_id']?:null,'title'=>'ALERTA DE PÁNICO','description'=>'Botón de pánico activado desde la aplicación vecinal.','address'=>$user['address']??'','latitude'=>is_numeric($input['latitude']??null)?$input['latitude']:null,'longitude'=>is_numeric($input['longitude']??null)?$input['longitude']:null,'priority'=>'critica','is_anonymous'=>0,'happened_at'=>date('Y-m-d H:i:s')]);
        $operators=Database::query("SELECT id FROM users WHERE commune_id=? AND status='activo' AND role_id IN (2,3)",[$user['commune_id']])->fetchAll();foreach($operators as $operator)Database::query("INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,'panic','ALERTA DE PÁNICO',?,?)",[$operator['id'],'Activada por '.$user['name'],url('reportes/'.$id)]);
        Audit::log('app_vecinos.panico','report',$id,null,['latitude'=>$input['latitude']??null,'longitude'=>$input['longitude']??null]);header('Content-Type: application/json');http_response_code(201);echo json_encode(['ok'=>true,'id'=>$id,'message'=>'Alerta enviada a la central.']);exit;
    }
    if($action==='pet'){
        $name=trim($_POST['name']??'');$species=trim($_POST['species']??'');if(!$name||!$species)$redirect('mascotas','Completa el nombre y tipo de mascota.');
        $token=sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(2)),bin2hex(random_bytes(6)));
        Database::query("INSERT INTO pets (user_id,commune_id,name,species,breed,color,description,qr_token,last_seen_address,status,lost_at) VALUES (?,?,?,?,?,?,?,?,?,? ,IF(?='perdida',NOW(),NULL))",[$user['id'],$user['commune_id'],$name,$species,trim($_POST['breed']??''),trim($_POST['color']??''),trim($_POST['description']??''),$token,trim($_POST['last_seen_address']??''),in_array($_POST['status']??'', ['en_casa','perdida','encontrada'],true)?$_POST['status']:'en_casa',$_POST['status']??'']);
        Audit::log('app_vecinos.mascota_creada','pet',(int)Database::connection()->lastInsertId(),null,['name'=>$name]);$redirect('mascotas','Mascota registrada.');
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
$reports=Database::query("SELECT r.*,rt.name type_name,rt.color FROM reports r JOIN report_types rt ON rt.id=r.report_type_id WHERE r.user_id=? ORDER BY r.created_at DESC LIMIT 50",[$user['id']])->fetchAll();
$pets=Database::query('SELECT * FROM pets WHERE user_id=? ORDER BY updated_at DESC',[$user['id']])->fetchAll();
$devices=Database::query('SELECT * FROM devices WHERE user_id=? ORDER BY updated_at DESC',[$user['id']])->fetchAll();
$contacts=Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name',[$user['commune_id']])->fetchAll();
$securityContact=null;try{$securityContact=Database::query('SELECT * FROM user_emergency_contacts WHERE user_id=?',[$user['id']])->fetch()?:null;}catch(Throwable){}
$stats=['active'=>count(array_filter($reports,fn($r)=>!in_array($r['status'],['resuelto','cerrado','rechazado'],true))),'resolved'=>count(array_filter($reports,fn($r)=>in_array($r['status'],['resuelto','cerrado'],true))),'pets'=>count($pets),'devices'=>count($devices)];
$flash=$_SESSION['neighbor_flash']??'';unset($_SESSION['neighbor_flash']);
require __DIR__.'/views/app.php';
