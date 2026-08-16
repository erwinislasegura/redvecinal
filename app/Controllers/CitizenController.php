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
}
