<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
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
        try{Database::query("INSERT INTO users (role_id,commune_id,name,email,phone,password,status) VALUES (?,?,?,?,?,?,'activo')",[(int)$_POST['role_id'],$communeId,$name,$email,trim($_POST['phone']??''),password_hash($password,PASSWORD_DEFAULT)]);}catch(\Throwable){$this->redirect('administracion/usuarios','No se pudo crear. Revisa si el correo ya existe.','danger');}
        $this->redirect('administracion/usuarios','Usuario creado.');
    }

    public function userStatus(string $id): void
    {
        $this->validateCsrf();$status=$_POST['status']??'';if(!in_array($status,['activo','pendiente','suspendido'],true))$this->redirect('administracion/usuarios','Estado inválido.','danger');
        if((int)$id===(int)Auth::user()['id'])$this->redirect('administracion/usuarios','No puedes suspender tu propia cuenta.','warning');
        Database::query('UPDATE users SET status=? WHERE id=?',[$status,$id]);$this->redirect('administracion/usuarios','Estado actualizado.');
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
        Database::query('INSERT INTO communes (name,region,code) VALUES (?,?,?)',[$name,$region,trim($_POST['code']??'')]);$this->redirect('administracion/comunas','Comuna agregada.');
    }

    public function storeSector(): void
    {
        $this->validateCsrf();$name=trim($_POST['name']??'');$commune=(int)($_POST['commune_id']??0);if(!$name||!$commune)$this->redirect('administracion/comunas','Selecciona comuna e ingresa el sector.','danger');
        Database::query('INSERT INTO sectors (commune_id,name) VALUES (?,?)',[$commune,$name]);$this->redirect('administracion/comunas','Sector agregado.');
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
        $ids=array_map('intval',$_POST['permissions']??[]);$db=Database::connection();$db->beginTransaction();try{Database::query('DELETE FROM role_permissions WHERE role_id=?',[$id]);foreach($ids as $permissionId)Database::query('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)',[$id,$permissionId]);$db->commit();}catch(\Throwable $e){$db->rollBack();$this->redirect('administracion/roles','No se pudieron guardar los permisos.','danger');}
        $this->redirect('administracion/roles','Permisos actualizados.');
    }
}
