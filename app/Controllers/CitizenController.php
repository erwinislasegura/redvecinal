<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Report;

final class CitizenController extends Controller
{
    public function landing(): void
    {
        if(Auth::check())$this->redirect('mi-app');
        $communes=Database::query("SELECT * FROM communes WHERE status='activa' ORDER BY region,name")->fetchAll();
        $sectors=Database::query("SELECT s.* FROM sectors s JOIN communes c ON c.id=s.commune_id WHERE s.status='activo' AND c.status='activa' ORDER BY s.name")->fetchAll();
        $this->view('citizen/landing',compact('communes','sectors')+['title'=>'Descargar app para vecinos','publicPage'=>true]);
    }

    public function registerNeighbor(): void
    {
        $this->validateCsrf();
        $name=trim((string)($_POST['name']??''));$rut=$this->normalizeRut((string)($_POST['rut']??''));
        $email=mb_strtolower(trim((string)($_POST['email']??'')));$phone=trim((string)($_POST['phone']??''));
        $address=trim((string)($_POST['address']??''));$communeId=(int)($_POST['commune_id']??0);$sectorId=(int)($_POST['sector_id']??0);
        $contactName=trim((string)($_POST['emergency_name']??''));$contactPhone=trim((string)($_POST['emergency_phone']??''));$relationship=trim((string)($_POST['emergency_relationship']??''));
        $password=(string)($_POST['password']??'');$confirmation=(string)($_POST['password_confirmation']??'');
        $commune=$communeId?Database::query("SELECT id FROM communes WHERE id=? AND status='activa'",[$communeId])->fetch():false;
        $sector=$sectorId?Database::query("SELECT id FROM sectors WHERE id=? AND commune_id=? AND status='activo'",[$sectorId,$communeId])->fetch():null;
        $validPhone=static fn(string $value): bool => strlen(preg_replace('/\D/','',$value))>=9;
        if(!$name||!$this->validRut($rut)||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$validPhone($phone)||!$address||!$commune||($sectorId&&!$sector)||!$contactName||!$validPhone($contactPhone)||!$relationship||strlen($password)<8||$password!==$confirmation||empty($_POST['terms'])){
            $this->rememberInput();$this->redirect('vecinos#registro','Completa correctamente los datos personales, domicilio, contacto de emergencia y contraseña.','danger');
        }
        if(Database::query('SELECT COUNT(*) FROM users WHERE email=? OR rut=?',[$email,$rut])->fetchColumn()){
            $this->rememberInput();$this->redirect('vecinos#registro','El correo o RUT ya se encuentra registrado.','danger');
        }
        $this->ensureSecuritySchema();
        $roleId=(int)Database::query("SELECT id FROM roles WHERE slug='vecino' LIMIT 1")->fetchColumn();
        if(!$roleId){$this->redirect('vecinos#registro','No se encuentra configurado el rol Vecino.','danger');}
        $db=Database::connection();$db->beginTransaction();
        try{
            Database::query("INSERT INTO users (role_id,commune_id,sector_id,name,rut,email,phone,address,password,status) VALUES (?,?,?,?,?,?,?,?,?,'activo')",[$roleId,$communeId,$sectorId?:null,$name,$rut,$email,$phone,$address,password_hash($password,PASSWORD_DEFAULT)]);
            $userId=(int)$db->lastInsertId();
            Database::query('INSERT INTO user_emergency_contacts (user_id,name,relationship,phone) VALUES (?,?,?,?)',[$userId,$contactName,$relationship,$contactPhone]);
            $db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();$this->rememberInput();$this->redirect('vecinos#registro','No se pudo crear la cuenta. Revisa si tus datos ya están registrados.','danger');}
        Auth::attempt($email,$password);
        Audit::log('vecino.registrado','user',$userId,null,['name'=>$name,'rut'=>$rut,'commune_id'=>$communeId,'sector_id'=>$sectorId?:null],$userId);
        $this->redirect('mi-app','Tu cuenta vecinal y ficha de seguridad fueron creadas correctamente.');
    }

    public function index(): void
    {
        $user=Auth::user();
        $reports=array_slice((new Report())->all(),0,6);
        $contacts=Database::query('SELECT * FROM emergency_contacts WHERE active=1 AND (commune_id IS NULL OR commune_id=?) ORDER BY available_24h DESC,name',[$user['commune_id']])->fetchAll();
        $pets=(int)Database::query('SELECT COUNT(*) FROM pets WHERE user_id=?',[$user['id']])->fetchColumn();
        $devices=(int)Database::query('SELECT COUNT(*) FROM devices WHERE user_id=?',[$user['id']])->fetchColumn();
        $this->view('citizen/index',compact('reports','contacts','pets','devices')+['title'=>'Mi RedVecinal']);
    }

    public function panic(): void
    {
        $this->validateCsrf();
        $input=str_contains($_SERVER['CONTENT_TYPE']??'','application/json')?(json_decode(file_get_contents('php://input'),true)?:[]):$_POST;
        $user=Auth::user();
        $type=Database::query("SELECT id FROM report_types WHERE active=1 AND category IN ('seguridad','emergencia') ORDER BY CASE WHEN name LIKE '%Robo%' THEN 0 ELSE 1 END,FIELD(priority_default,'critica','alta','media','baja') LIMIT 1")->fetch();
        if(!$type){$this->panicResponse(false,0,'No existe un tipo de emergencia activo.');}
        $latitude=is_numeric($input['latitude']??null)?(float)$input['latitude']:null;
        $longitude=is_numeric($input['longitude']??null)?(float)$input['longitude']:null;
        $note=trim((string)($input['note']??''));
        $description='Botón de pánico activado desde la aplicación vecinal.' . ($note!==''?' Detalle: '.$note:'');
        $id=(new Report())->create(['user_id'=>$user['id'],'report_type_id'=>$type['id'],'commune_id'=>$user['commune_id'],'sector_id'=>$user['sector_id']?:null,'title'=>'ALERTA DE PÁNICO','description'=>$description,'address'=>trim((string)($input['address']??$user['address']??'')),'latitude'=>$latitude,'longitude'=>$longitude,'priority'=>'critica','is_anonymous'=>0,'happened_at'=>date('Y-m-d H:i:s')]);
        if(setting('notifications_enabled','1',(int)$user['commune_id'])==='1'){
            $operators=Database::query("SELECT id FROM users WHERE commune_id=? AND status='activo' AND role_id IN (2,3)",[$user['commune_id']])->fetchAll();
            foreach($operators as $operator)Database::query("INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,'panic','ALERTA DE PÁNICO',?,?)",[$operator['id'],'Activada por '.$user['name'],url('reportes/'.$id)]);
        }
        Audit::log('panico.activado','report',$id,null,['latitude'=>$latitude,'longitude'=>$longitude]);
        $this->panicResponse(true,$id,'La alerta fue enviada a la central.');
    }

    private function panicResponse(bool $ok,int $id,string $message): never
    {
        if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json'))$this->json(['ok'=>$ok,'id'=>$id,'message'=>$message,'url'=>$id?url('reportes/'.$id):null],$ok?201:422);
        $this->redirect('mi-app',$message,$ok?'success':'danger');
    }

    private function ensureSecuritySchema(): void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS user_emergency_contacts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,relationship VARCHAR(80) NOT NULL,phone VARCHAR(30) NOT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_user_emergency_contact (user_id),CONSTRAINT fk_uec_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function normalizeRut(string $rut): string
    {
        $clean=strtoupper(preg_replace('/[^0-9Kk]/','',$rut));
        return strlen($clean)>1?substr($clean,0,-1).'-'.substr($clean,-1):$clean;
    }

    private function validRut(string $rut): bool
    {
        $clean=str_replace('-','',$rut);if(!preg_match('/^(\d{7,8})([0-9K])$/',$clean,$match))return false;
        $sum=0;$multiplier=2;for($i=strlen($match[1])-1;$i>=0;$i--){$sum+=(int)$match[1][$i]*$multiplier;$multiplier=$multiplier===7?2:$multiplier+1;}
        $value=11-($sum%11);$expected=$value===11?'0':($value===10?'K':(string)$value);
        return $match[2]===$expected;
    }
}
