<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;

final class PetController extends Controller
{
    public function index(): void
    {
        $user=Auth::user();
        $sql=Auth::can('reports.commune')?'SELECT p.*,u.name AS owner_name,u.phone AS owner_phone FROM pets p JOIN users u ON u.id=p.user_id WHERE p.commune_id=? ORDER BY FIELD(p.status,\'perdida\',\'encontrada\',\'en_casa\'),p.updated_at DESC':'SELECT p.*,u.name AS owner_name,u.phone AS owner_phone FROM pets p JOIN users u ON u.id=p.user_id WHERE p.user_id=? ORDER BY p.updated_at DESC';
        $pets=Database::query($sql,[Auth::can('reports.commune')?$user['commune_id']:$user['id']])->fetchAll();
        $this->view('pets/index',['title'=>'Mascotas','pets'=>$pets]);
    }

    public function store(): void
    {
        $this->validateCsrf(); $user=Auth::user(); $name=trim($_POST['name']??''); $species=trim($_POST['species']??'');
        if(!$name||!$species)$this->redirect('mascotas','Completa el nombre y la especie.','danger');
        $token=$this->uuid();
        Database::query('INSERT INTO pets (user_id,commune_id,name,species,breed,color,description,qr_token,status,last_seen_address,lost_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$user['id'],$user['commune_id'],$name,$species,trim($_POST['breed']??''),trim($_POST['color']??''),trim($_POST['description']??''),$token,$_POST['status']??'en_casa',trim($_POST['last_seen_address']??''),!empty($_POST['lost_at'])?str_replace('T',' ',$_POST['lost_at']):null]);
        $id=(int)Database::connection()->lastInsertId();Audit::log('mascota.creada','pet',$id,null,['name'=>$name,'species'=>$species,'status'=>$_POST['status']??'en_casa']);
        $this->redirect('mascotas','Mascota registrada. Su enlace QR ya está disponible.');
    }

    public function status(string $id): void
    {
        $this->validateCsrf();$user=Auth::user();$pet=Database::query('SELECT * FROM pets WHERE id=?',[$id])->fetch();
        if(!$pet||((int)$pet['user_id']!==(int)$user['id']&&!Auth::can('reports.commune')))$this->redirect('mascotas','Mascota no encontrada.','danger');
        $status=$_POST['status']??'';if(!in_array($status,['en_casa','perdida','encontrada'],true))$this->redirect('mascotas','Estado inválido.','danger');
        Database::query('UPDATE pets SET status=?,last_seen_address=?,lost_at=IF(?=\'perdida\',COALESCE(lost_at,NOW()),lost_at) WHERE id=?',[$status,trim($_POST['last_seen_address']??$pet['last_seen_address']),$status,$id]);
        Audit::log('mascota.estado_actualizado','pet',$id,['status'=>$pet['status']],['status'=>$status]);
        $this->redirect('mascotas','Estado de la mascota actualizado.');
    }

    public function publicProfile(string $token): void
    {
        $pet=Database::query('SELECT p.*,u.name AS owner_name,u.phone AS owner_phone,c.name AS commune_name FROM pets p JOIN users u ON u.id=p.user_id JOIN communes c ON c.id=p.commune_id WHERE p.qr_token=?',[$token])->fetch();
        if(!$pet){http_response_code(404);require BASE_PATH.'/app/Views/errors/404.php';return;}
        $sightings=Database::query('SELECT * FROM pet_sightings WHERE pet_id=? ORDER BY created_at DESC LIMIT 10',[$pet['id']])->fetchAll();
        $this->view('pets/public',['title'=>$pet['name'],'pet'=>$pet,'sightings'=>$sightings,'publicPage'=>true]);
    }

    public function sighting(string $token): void
    {
        $this->validateCsrf();$pet=Database::query('SELECT * FROM pets WHERE qr_token=?',[$token])->fetch();if(!$pet)$this->redirect('','Mascota no encontrada.','danger');
        $notes=trim($_POST['notes']??'');if(!$notes)$this->redirect('mascota/qr/'.$token,'Cuéntanos dónde viste a la mascota.','danger');
        Database::query('INSERT INTO pet_sightings (pet_id,user_id,reporter_name,reporter_phone,notes,address,latitude,longitude) VALUES (?,?,?,?,?,?,?,?)',[$pet['id'],Auth::user()['id']??null,trim($_POST['reporter_name']??''),trim($_POST['reporter_phone']??''),$notes,trim($_POST['address']??''),is_numeric($_POST['latitude']??null)?$_POST['latitude']:null,is_numeric($_POST['longitude']??null)?$_POST['longitude']:null]);
        Database::query('INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,\'pet_sighting\',?,?,?)',[$pet['user_id'],'Nuevo avistamiento de '.$pet['name'],$notes,url('mascota/qr/'.$token)]);
        Audit::log('mascota.avistamiento','pet',$pet['id'],null,['address'=>trim($_POST['address']??'')],Auth::user()['id']??null);
        $this->redirect('mascota/qr/'.$token,'Gracias. El dueño recibió tu aviso.');
    }

    private function uuid(): string
    {
        $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }
}
