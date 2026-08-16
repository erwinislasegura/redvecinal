<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;

final class AdminController extends Controller
{
    public function users(): void
    {
        $user=Auth::user();$users=Database::query('SELECT u.*,r.name AS role_name,c.name AS commune_name FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN communes c ON c.id=u.commune_id WHERE '.($user['role_slug']==='superadmin'?'1=1':'u.commune_id=?').' ORDER BY u.created_at DESC',$user['role_slug']==='superadmin'?[]:[$user['commune_id']])->fetchAll();
        $roles=Database::query('SELECT * FROM roles ORDER BY id')->fetchAll();$communes=Database::query("SELECT * FROM communes WHERE status='activa' ORDER BY name")->fetchAll();
        $this->view('admin/users',compact('users','roles','communes')+['title'=>'Usuarios']);
    }

    public function storeUser(): void
    {
        $this->validateCsrf();$admin=Auth::user();$name=trim($_POST['name']??'');$email=mb_strtolower(trim($_POST['email']??''));$password=(string)($_POST['password']??'');
        $communeId=$admin['role_slug']==='superadmin'?(int)($_POST['commune_id']??0):(int)$admin['commune_id'];
        if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||!$communeId)$this->redirect('administracion/usuarios','Completa todos los campos correctamente.','danger');
        try{
            Database::query("INSERT INTO users (role_id,commune_id,name,email,phone,password,status) VALUES (?,?,?,?,?,?,'activo')",[(int)$_POST['role_id'],$communeId,$name,$email,trim($_POST['phone']??''),password_hash($password,PASSWORD_DEFAULT)]);
            $userId=(int)Database::connection()->lastInsertId();
            Audit::log('usuario.creado','user',$userId,null,['name'=>$name,'email'=>$email,'role_id'=>(int)$_POST['role_id'],'commune_id'=>$communeId]);
        }catch(\Throwable){$this->redirect('administracion/usuarios','No se pudo crear. Revisa si el correo ya existe.','danger');}
        $this->redirect('administracion/usuarios','Usuario creado.');
    }

    public function userStatus(string $id): void
    {
        $this->validateCsrf();$status=$_POST['status']??'';if(!in_array($status,['activo','pendiente','suspendido'],true))$this->redirect('administracion/usuarios','Estado inválido.','danger');
        if((int)$id===(int)Auth::user()['id'])$this->redirect('administracion/usuarios','No puedes suspender tu propia cuenta.','warning');
        $old=Database::query('SELECT id,name,email,status FROM users WHERE id=?',[$id])->fetch();
        Database::query('UPDATE users SET status=? WHERE id=?',[$status,$id]);
        Audit::log('usuario.estado_actualizado','user',$id,$old?:null,['status'=>$status]);
        $this->redirect('administracion/usuarios','Estado actualizado.');
    }

    public function communes(): void
    {
        $communes=Database::query('SELECT c.*,(SELECT COUNT(*) FROM users u WHERE u.commune_id=c.id) AS users_count,(SELECT COUNT(*) FROM sectors s WHERE s.commune_id=c.id) AS sectors_count FROM communes c ORDER BY c.region,c.name')->fetchAll();
        $sectors=Database::query('SELECT s.*,c.name AS commune_name FROM sectors s JOIN communes c ON c.id=s.commune_id ORDER BY c.name,s.name')->fetchAll();
        $this->view('admin/communes',compact('communes','sectors')+['title'=>'Comunas y sectores']);
    }

    public function storeCommune(): void
    {
        $this->validateCsrf();$name=trim($_POST['name']??'');$region=trim($_POST['region']??'');if(!$name||!$region)$this->redirect('administracion/comunas','Completa comuna y región.','danger');
        Database::query('INSERT INTO communes (name,region,code) VALUES (?,?,?)',[$name,$region,trim($_POST['code']??'')]);
        $id=(int)Database::connection()->lastInsertId();Audit::log('comuna.creada','commune',$id,null,['name'=>$name,'region'=>$region]);
        $this->redirect('administracion/comunas','Comuna agregada.');
    }

    public function storeSector(): void
    {
        $this->validateCsrf();$name=trim($_POST['name']??'');$commune=(int)($_POST['commune_id']??0);if(!$name||!$commune)$this->redirect('administracion/comunas','Selecciona comuna e ingresa el sector.','danger');
        Database::query('INSERT INTO sectors (commune_id,name) VALUES (?,?)',[$commune,$name]);
        $id=(int)Database::connection()->lastInsertId();Audit::log('sector.creado','sector',$id,null,['name'=>$name,'commune_id'=>$commune]);
        $this->redirect('administracion/comunas','Sector agregado.');
    }

    public function roles(): void
    {
        $roles=Database::query('SELECT * FROM roles ORDER BY id')->fetchAll();$permissions=Database::query('SELECT * FROM permissions ORDER BY module,name')->fetchAll();$assigned=Database::query('SELECT role_id,permission_id FROM role_permissions')->fetchAll();
        $map=[];foreach($assigned as $item)$map[$item['role_id']][]=(int)$item['permission_id'];
        $this->view('admin/roles',compact('roles','permissions','map')+['title'=>'Roles y permisos']);
    }

    public function updateRole(string $id): void
    {
        $this->validateCsrf();if((int)$id===1)$this->redirect('administracion/roles','El rol principal conserva todos los permisos.','warning');
        $ids=array_map('intval',$_POST['permissions']??[]);$oldIds=array_map('intval',Database::query('SELECT permission_id FROM role_permissions WHERE role_id=?',[$id])->fetchAll(\PDO::FETCH_COLUMN));$db=Database::connection();$db->beginTransaction();try{Database::query('DELETE FROM role_permissions WHERE role_id=?',[$id]);foreach($ids as $permissionId)Database::query('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)',[$id,$permissionId]);$db->commit();Audit::log('rol.permisos_actualizados','role',$id,['permissions'=>$oldIds],['permissions'=>$ids]);}catch(\Throwable $e){$db->rollBack();$this->redirect('administracion/roles','No se pudieron guardar los permisos.','danger');}
        $this->redirect('administracion/roles','Permisos actualizados.');
    }

    public function audit(): void
    {
        $user=Auth::user();$where=['1=1'];$params=[];
        if($user['role_slug']!=='superadmin'){$where[]='(u.commune_id=? OR a.user_id IS NULL)';$params[]=$user['commune_id'];}
        $action=trim($_GET['action']??'');$entity=trim($_GET['entity']??'');$from=trim($_GET['from']??'');$to=trim($_GET['to']??'');
        if($action!==''){$where[]='a.action LIKE ?';$params[]='%'.$action.'%';}
        if($entity!==''){$where[]='a.entity_type=?';$params[]=$entity;}
        if($from!==''){$where[]='DATE(a.created_at)>=?';$params[]=$from;}
        if($to!==''){$where[]='DATE(a.created_at)<=?';$params[]=$to;}
        $logs=Database::query('SELECT a.*,u.name AS user_name,u.email AS user_email,r.name AS role_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN roles r ON r.id=u.role_id WHERE '.implode(' AND ',$where).' ORDER BY a.created_at DESC LIMIT 300',$params)->fetchAll();
        $actions=Database::query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(\PDO::FETCH_COLUMN);
        $this->view('admin/audit',compact('logs','actions','action','entity','from','to')+['title'=>'Auditoría']);
    }

    public function settings(): void
    {
        $user=Auth::user();$communeId=(int)$user['commune_id'];
        $defaults=['organization_name'=>'RedVecinal','municipal_phone'=>'','municipal_email'=>'','municipal_address'=>'','reports_anonymous'=>'1','notifications_enabled'=>'1','device_alerts_enabled'=>'1','map_center_lat'=>'-37.4689','map_center_lng'=>'-72.3527','map_zoom'=>'13','report_retention_days'=>'365'];
        $rows=Database::query('SELECT setting_key,setting_value FROM settings WHERE commune_id=?',[$communeId])->fetchAll();$settings=$defaults;foreach($rows as $row)$settings[$row['setting_key']]=$row['setting_value'];
        $contacts=Database::query('SELECT * FROM emergency_contacts WHERE commune_id IS NULL OR commune_id=? ORDER BY commune_id IS NULL DESC,name',[$communeId])->fetchAll();
        $this->view('admin/settings',compact('settings','contacts')+['title'=>'Configuración']);
    }

    public function updateSettings(): void
    {
        $this->validateCsrf();$user=Auth::user();$communeId=(int)$user['commune_id'];
        $allowed=['organization_name','municipal_phone','municipal_email','municipal_address','map_center_lat','map_center_lng','map_zoom','report_retention_days'];$new=[];
        foreach($allowed as $key)$new[$key]=trim((string)($_POST[$key]??''));
        $new['reports_anonymous']=!empty($_POST['reports_anonymous'])?'1':'0';$new['notifications_enabled']=!empty($_POST['notifications_enabled'])?'1':'0';$new['device_alerts_enabled']=!empty($_POST['device_alerts_enabled'])?'1':'0';
        if($new['municipal_email']!==''&&!filter_var($new['municipal_email'],FILTER_VALIDATE_EMAIL))$this->redirect('administracion/configuracion','El correo municipal no es válido.','danger');
        if(!is_numeric($new['map_center_lat'])||(float)$new['map_center_lat'] < -90||(float)$new['map_center_lat'] > 90)$new['map_center_lat']='-37.4689';
        if(!is_numeric($new['map_center_lng'])||(float)$new['map_center_lng'] < -180||(float)$new['map_center_lng'] > 180)$new['map_center_lng']='-72.3527';
        if(!ctype_digit($new['map_zoom'])||(int)$new['map_zoom']<5||(int)$new['map_zoom']>19)$new['map_zoom']='13';
        if(!ctype_digit($new['report_retention_days'])||(int)$new['report_retention_days']<30)$new['report_retention_days']='365';
        $oldRows=Database::query('SELECT setting_key,setting_value FROM settings WHERE commune_id=?',[$communeId])->fetchAll();$old=[];foreach($oldRows as $row)$old[$row['setting_key']]=$row['setting_value'];
        foreach($new as $key=>$value)Database::query('INSERT INTO settings (commune_id,setting_key,setting_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$communeId,$key,$value]);
        Audit::log('configuracion.actualizada','commune',$communeId,$old,$new);
        $this->redirect('administracion/configuracion','Configuración guardada correctamente.');
    }

    public function storeContact(): void
    {
        $this->validateCsrf();$user=Auth::user();$name=trim($_POST['name']??'');$service=trim($_POST['service']??'');$phone=trim($_POST['phone']??'');
        if(!$name||!$service||!$phone)$this->redirect('administracion/configuracion','Completa nombre, servicio y teléfono.','danger');
        Database::query('INSERT INTO emergency_contacts (commune_id,name,service,phone,available_24h,active) VALUES (?,?,?,?,?,1)',[$user['commune_id'],$name,$service,$phone,!empty($_POST['available_24h'])?1:0]);
        $id=(int)Database::connection()->lastInsertId();Audit::log('contacto_emergencia.creado','emergency_contact',$id,null,['name'=>$name,'service'=>$service,'phone'=>$phone]);
        $this->redirect('administracion/configuracion','Contacto de emergencia agregado.');
    }

    public function contactStatus(string $id): void
    {
        $this->validateCsrf();$user=Auth::user();$contact=Database::query('SELECT * FROM emergency_contacts WHERE id=? AND commune_id=?',[$id,$user['commune_id']])->fetch();
        if(!$contact)$this->redirect('administracion/configuracion','Solo puedes modificar contactos de tu comuna.','danger');
        $active=!empty($_POST['active'])?1:0;Database::query('UPDATE emergency_contacts SET active=? WHERE id=?',[$active,$id]);Audit::log('contacto_emergencia.estado','emergency_contact',$id,['active'=>$contact['active']],['active'=>$active]);
        $this->redirect('administracion/configuracion','Estado del contacto actualizado.');
    }
}
