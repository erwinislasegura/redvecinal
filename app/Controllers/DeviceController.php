<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

final class DeviceController extends Controller
{
    public function index(): void
    {
        $user=Auth::user();$manage=Auth::can('devices.manage');
        $devices=Database::query('SELECT d.*,u.name AS owner_name,(SELECT COUNT(*) FROM device_events e WHERE e.device_id=d.id) AS events_count FROM devices d JOIN users u ON u.id=d.user_id WHERE '.($manage?'d.commune_id=?':'d.user_id=?').' ORDER BY d.updated_at DESC',[$manage?$user['commune_id']:$user['id']])->fetchAll();
        $events=Database::query('SELECT e.*,d.name AS device_name FROM device_events e JOIN devices d ON d.id=e.device_id WHERE '.($manage?'d.commune_id=?':'d.user_id=?').' ORDER BY e.created_at DESC LIMIT 20',[$manage?$user['commune_id']:$user['id']])->fetchAll();
        $this->view('devices/index',['title'=>'Cámaras y alarmas','devices'=>$devices,'events'=>$events]);
    }

    public function store(): void
    {
        $this->validateCsrf();$user=Auth::user();$name=trim($_POST['name']??'');$type=$_POST['type']??'';
        if(!$name||!in_array($type,['camara','alarma','sensor','boton_panico','otro'],true))$this->redirect('dispositivos','Completa los datos del dispositivo.','danger');
        $protocol=$_POST['protocol']??'manual';if(!in_array($protocol,['manual','rtsp','onvif','http','mqtt'],true))$protocol='manual';
        Database::query('INSERT INTO devices (user_id,commune_id,name,type,location,protocol,connection_url,webhook_token,status) VALUES (?,?,?,?,?,?,?,?,\'activo\')',[$user['id'],$user['commune_id'],$name,$type,trim($_POST['location']??''),$protocol,trim($_POST['connection_url']??''),bin2hex(random_bytes(32))]);
        $this->redirect('dispositivos','Dispositivo registrado.');
    }

    public function event(string $id): void
    {
        $this->validateCsrf();$user=Auth::user();$device=Database::query('SELECT * FROM devices WHERE id=?',[$id])->fetch();
        if(!$device||((int)$device['user_id']!==(int)$user['id']&&!Auth::can('devices.manage')))$this->redirect('dispositivos','Dispositivo no encontrado.','danger');
        $severity=$_POST['severity']??'info';if(!in_array($severity,['info','advertencia','critica'],true))$severity='info';
        Database::query('INSERT INTO device_events (device_id,event_type,payload_json,severity) VALUES (?,?,?,?)',[$id,trim($_POST['event_type']??'Alerta manual'),json_encode(['notes'=>trim($_POST['notes']??'')],JSON_UNESCAPED_UNICODE),$severity]);
        Database::query('UPDATE devices SET last_seen_at=NOW(),status=\'activo\' WHERE id=?',[$id]);
        $this->redirect('dispositivos','Evento registrado.');
    }

    public function webhook(string $token): void
    {
        $device=Database::query('SELECT * FROM devices WHERE webhook_token=? AND status<>\'inactivo\'',[$token])->fetch();
        if(!$device)$this->json(['ok'=>false,'message'=>'Dispositivo no autorizado'],401);
        $payload=json_decode(file_get_contents('php://input'),true)?:$_POST;
        $event=trim($payload['event_type']??'Evento externo');
        $severity=$payload['severity']??'advertencia';
        if(!in_array($severity,['info','advertencia','critica'],true))$severity='advertencia';
        Database::query('INSERT INTO device_events (device_id,event_type,payload_json,severity) VALUES (?,?,?,?)',[$device['id'],$event,json_encode($payload,JSON_UNESCAPED_UNICODE),$severity]);
        Database::query('UPDATE devices SET last_seen_at=NOW(),status=\'activo\' WHERE id=?',[$device['id']]);
        if($severity==='critica')Database::query('INSERT INTO notifications (user_id,type,title,message,action_url) VALUES (?,\'device_alert\',?,?,?)',[$device['user_id'],'Alerta crítica: '.$device['name'],$event,url('dispositivos')]);
        $this->json(['ok'=>true],201);
    }
}
