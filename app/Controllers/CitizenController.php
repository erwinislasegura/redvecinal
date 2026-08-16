<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\PanicAlert;
use App\Models\Report;

final class CitizenController extends Controller
{
    public function landing(): void
    {
        if(Auth::check()){header('Location: '.url('vecinos/'));exit;}
        $communes=Database::query("SELECT * FROM communes WHERE status='activa' ORDER BY region,name")->fetchAll();
        $sectors=Database::query("SELECT s.* FROM sectors s JOIN communes c ON c.id=s.commune_id WHERE s.status='activo' AND c.status='activa' ORDER BY s.name")->fetchAll();
        $registrationErrors=$_SESSION['registration_errors']??[];unset($_SESSION['registration_errors']);
        $this->view('citizen/landing',compact('communes','sectors','registrationErrors')+['title'=>'Descargar app para vecinos','publicPage'=>true]);
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
        $errors=[];
        if(mb_strlen($name)<3)$errors['name']='Ingresa el nombre completo.';
        if(!$this->validRut($rut))$errors['rut']='El RUT no es válido. Revisa el número y su dígito verificador.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Ingresa un correo electrónico válido.';
        if(!$validPhone($phone))$errors['phone']='El teléfono debe contener al menos 9 dígitos.';
        if(mb_strlen($address)<5)$errors['address']='Ingresa una dirección completa.';
        if(!$commune)$errors['commune_id']='Selecciona una comuna activa.';
        if($sectorId&&!$sector)$errors['sector_id']='El sector seleccionado no pertenece a la comuna.';
        if(mb_strlen($contactName)<3)$errors['emergency_name']='Ingresa el nombre del contacto de emergencia.';
        if(mb_strlen($relationship)<2)$errors['emergency_relationship']='Indica la relación con tu contacto.';
        if(!$validPhone($contactPhone))$errors['emergency_phone']='El teléfono de emergencia debe contener al menos 9 dígitos.';
        if(strlen($password)<8)$errors['password']='La contraseña debe tener al menos 8 caracteres.';
        elseif($password!==$confirmation)$errors['password_confirmation']='Las contraseñas no coinciden.';
        if(empty($_POST['terms']))$errors['terms']='Debes aceptar la autorización para crear la cuenta.';
        if($errors){
            $_SESSION['registration_errors']=$errors;$this->rememberInput();
            $this->redirect('descargar-vecinos#registro','Revisa los campos indicados para completar el registro.','danger');
        }
        $existing=Database::query('SELECT email,rut FROM users WHERE email=? OR rut=? LIMIT 1',[$email,$rut])->fetch();
        if($existing){
            $duplicateErrors=[];
            if(mb_strtolower((string)$existing['email'])===$email)$duplicateErrors['email']='Este correo ya tiene una cuenta registrada.';
            if((string)$existing['rut']===$rut)$duplicateErrors['rut']='Este RUT ya tiene una cuenta registrada.';
            $_SESSION['registration_errors']=$duplicateErrors;$this->rememberInput();
            $this->redirect('descargar-vecinos#registro','Ya existe una cuenta con los datos indicados.','danger');
        }
        $this->ensureSecuritySchema();
        $roleId=(int)Database::query("SELECT id FROM roles WHERE slug='vecino' LIMIT 1")->fetchColumn();
        if(!$roleId){$this->redirect('descargar-vecinos#registro','No se encuentra configurado el rol Vecino.','danger');}
        $db=Database::connection();$db->beginTransaction();
        try{
            Database::query("INSERT INTO users (role_id,commune_id,sector_id,name,rut,email,phone,address,password,status) VALUES (?,?,?,?,?,?,?,?,?,'activo')",[$roleId,$communeId,$sectorId?:null,$name,$rut,$email,$phone,$address,password_hash($password,PASSWORD_DEFAULT)]);
            $userId=(int)$db->lastInsertId();
            Database::query('INSERT INTO user_emergency_contacts (user_id,name,relationship,phone) VALUES (?,?,?,?)',[$userId,$contactName,$relationship,$contactPhone]);
            $db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();error_log('RedVecinal registro vecino: '.$error->getMessage());$_SESSION['registration_errors']=['general'=>'No fue posible guardar la cuenta. Verifica que la base de datos esté actualizada e inténtalo nuevamente.'];$this->rememberInput();$this->redirect('descargar-vecinos#registro','No se pudo crear la cuenta vecinal.','danger');}
        Auth::attempt($email,$password);
        Audit::log('vecino.registrado','user',$userId,null,['name'=>$name,'rut'=>$rut,'commune_id'=>$communeId,'sector_id'=>$sectorId?:null],$userId);
        $_SESSION['neighbor_flash']='Tu cuenta vecinal y ficha de seguridad fueron creadas correctamente.';header('Location: '.url('vecinos/'));exit;
    }

    public function index(): void
    {
        header('Location: '.url('vecinos/'));exit;
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
        if($latitude!==null&&($latitude < -90||$latitude > 90))$latitude=null;if($longitude!==null&&($longitude < -180||$longitude > 180))$longitude=null;
        $hasGps=$latitude!==null&&$longitude!==null;$accuracy=is_numeric($input['accuracy']??null)?min(100000,max(0,(int)round((float)$input['accuracy']))):null;$capturedAt=mb_substr(trim((string)($input['captured_at']??'')),0,40);
        $note=trim((string)($input['note']??''));
        $description='Botón de pánico activado desde la aplicación vecinal. '.($hasGps?'Ubicación GPS capturada desde el dispositivo'.($accuracy!==null?' con precisión aproximada de ±'.$accuracy.' m':'').($capturedAt!==''?' a las '.$capturedAt:'').'.':'El dispositivo no permitió obtener ubicación GPS.').($note!==''?' Detalle: '.$note:'');
        $address=$hasGps?'Ubicación GPS: '.number_format($latitude,7,'.','').', '.number_format($longitude,7,'.',''):('GPS no disponible · '.($user['address']??'domicilio no informado'));
        $id=(new Report())->create(['user_id'=>$user['id'],'report_type_id'=>$type['id'],'commune_id'=>$user['commune_id'],'sector_id'=>$user['sector_id']?:null,'title'=>'ALERTA DE PÁNICO','description'=>$description,'address'=>$address,'latitude'=>$latitude,'longitude'=>$longitude,'priority'=>'critica','is_anonymous'=>0,'happened_at'=>date('Y-m-d H:i:s')]);
        PanicAlert::notify($user,$id);
        Audit::log('panico.activado','report',$id,null,['latitude'=>$latitude,'longitude'=>$longitude,'accuracy'=>$accuracy,'captured_at'=>$capturedAt]);
        $this->panicResponse(true,$id,$hasGps?'La alerta y ubicación GPS fueron enviadas a la central.':'La alerta fue enviada, pero el teléfono no entregó la ubicación GPS.');
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
