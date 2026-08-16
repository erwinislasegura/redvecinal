<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('Vista no encontrada: ' . $view);
        }
        require BASE_PATH . '/app/Views/layouts/header.php';
        require $viewFile;
        require BASE_PATH . '/app/Views/layouts/footer.php';
        unset($_SESSION['_old']);
    }

    protected function redirect(string $path, ?string $message = null, string $type = 'success'): never
    {
        if ($message) {
            $_SESSION['_flash']['message'] = $message;
            $_SESSION['_flash']['type'] = $type;
        }
        header('Location: ' . url($path));
        exit;
    }

    protected function validateCsrf(): void
    {
        Csrf::verify($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
    }

    protected function rememberInput(): void
    {
        $_SESSION['_old'] = array_diff_key($_POST, ['password' => true, 'password_confirmation' => true, '_token' => true]);
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
