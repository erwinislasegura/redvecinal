<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;

final class AuthController extends Controller
{
    public function home(): void
    {
        if (Auth::check()) { $this->redirect('panel'); }
        $contacts = Database::query('SELECT * FROM emergency_contacts WHERE active=1 ORDER BY available_24h DESC,name')->fetchAll();
        $this->view('home', ['title' => 'Tu comunidad conectada', 'contacts' => $contacts, 'publicPage' => true]);
    }

    public function loginForm(): void
    {
        $this->view('auth/login', ['title' => 'Ingresar', 'publicPage' => true]);
    }

    public function login(): void
    {
        $this->validateCsrf();
        if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            Audit::log('sesion.iniciada','user',Auth::user()['id']);
            $this->redirect('panel', 'Bienvenido/a a RedVecinal.');
        }
        $this->rememberInput();
        $this->redirect('ingresar', 'Correo o contraseña incorrectos.', 'danger');
    }

    public function registerForm(): void
    {
        $this->redirect('vecinos#registro');
    }

    public function register(): void
    {
        $this->validateCsrf();
        $name = trim($_POST['name'] ?? '');
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $communeId = (int)($_POST['commune_id'] ?? 0);
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$communeId) {
            $this->rememberInput();
            $this->redirect('registro', 'Completa todos los datos. La contraseña debe tener al menos 8 caracteres.', 'danger');
        }
        if (Database::query('SELECT COUNT(*) FROM users WHERE email=?', [$email])->fetchColumn()) {
            $this->rememberInput();
            $this->redirect('registro', 'Ese correo ya está registrado.', 'danger');
        }
        Database::query(
            "INSERT INTO users (role_id,commune_id,name,email,phone,address,password,status) VALUES (6,?,?,?,?,?,?,'activo')",
            [$communeId,$name,$email,trim($_POST['phone'] ?? ''),trim($_POST['address'] ?? ''),password_hash($password,PASSWORD_DEFAULT)]
        );
        $userId=(int)Database::connection()->lastInsertId();
        Auth::attempt($email,$password);
        Audit::log('usuario.registrado','user',$userId,null,['name'=>$name,'email'=>$email,'commune_id'=>$communeId],$userId);
        $this->redirect('panel','Tu cuenta fue creada correctamente.');
    }

    public function logout(): void
    {
        $this->validateCsrf();
        $user=Auth::user();Audit::log('sesion.cerrada','user',$user['id']??null,null,null,$user['id']??null);
        Auth::logout();
        header('Location: ' . url('ingresar'));
        exit;
    }
}
