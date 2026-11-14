<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\User;

final class AuthController extends Controller
{
    private ?User $users = null;

    public function loginForm(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect($this->authenticatedHome());
        }

        $this->render('auth/login', [
            'title' => 'Đăng nhập | Pet House',
            'errors' => [],
            'email' => '',
        ]);
    }

    public function login(): void
    {
        if (!valid_csrf_token($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->render('auth/login', [
                'title' => 'Đăng nhập | Pet House',
                'errors' => ['Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.'],
                'email' => '',
            ]);
            return;
        }

        $email = $this->postText('email');
        $password = $this->postText('password', false);
        $normalizedEmail = mb_strtolower(trim($email));

        if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false
            || $password === ''
            || strlen($password) > 72) {
            $this->render('auth/login', [
                'title' => 'Đăng nhập | Pet House',
                'errors' => ['Email hoặc mật khẩu không chính xác.'],
                'email' => $email,
            ]);
            return;
        }

        $user = $this->users()->authenticate($normalizedEmail, $password);

        if ($user === null) {
            $this->render('auth/login', [
                'title' => 'Đăng nhập | Pet House',
                'errors' => ['Email hoặc mật khẩu không chính xác.'],
                'email' => $email,
            ]);
            return;
        }

        session_regenerate_id(true);
        unset($_SESSION['_csrf_token']);
        $_SESSION['user'] = $user;
        $this->redirect($user['role'] === 'admin' ? '/admin' : '/account');
    }

    public function registerForm(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirect('/account');
        }

        $this->render('auth/register', [
            'title' => 'Tạo tài khoản | Pet House',
            'errors' => [],
            'name' => '',
            'email' => '',
        ]);
    }

    public function register(): void
    {
        if (!valid_csrf_token($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->render('auth/register', [
                'title' => 'Tạo tài khoản | Pet House',
                'errors' => ['Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.'],
                'name' => '',
                'email' => '',
            ]);
            return;
        }

        $name = trim($this->postText('name'));
        $email = mb_strtolower(trim($this->postText('email')));
        $password = $this->postText('password', false);
        $passwordConfirmation = $this->postText('password_confirmation', false);
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Tên cần có từ 2 đến 100 ký tự.';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            $errors[] = 'Vui lòng nhập địa chỉ email hợp lệ.';
        }

        if (strlen($password) < 10 || strlen($password) > 72) {
            $errors[] = 'Mật khẩu cần dài từ 10 đến 72 byte.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'Mật khẩu xác nhận chưa khớp.';
        }

        if ($errors !== []) {
            $this->render('auth/register', [
                'title' => 'Tạo tài khoản | Pet House',
                'errors' => $errors,
                'name' => $name,
                'email' => $email,
            ]);
            return;
        }

        if (!$this->users()->createCustomer($name, $email, $password)) {
            $this->render('auth/register', [
                'title' => 'Tạo tài khoản | Pet House',
                'errors' => ['Email này đã được đăng ký. Hãy đăng nhập hoặc dùng email khác.'],
                'name' => $name,
                'email' => $email,
            ]);
            return;
        }

        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Tài khoản đã được tạo. Hãy đăng nhập để tiếp tục.',
        ];
        $this->redirect('/login');
    }

    public function account(): void
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Vui lòng đăng nhập để xem tài khoản.',
            ];
            $this->redirect('/login');
        }

        if (($_SESSION['user']['role'] ?? '') === 'admin') {
            $this->redirect('/admin');
        }

        $this->render('auth/account', [
            'title' => 'Tài khoản | Pet House',
            'user' => $_SESSION['user'],
        ]);
    }

    public function logout(): void
    {
        if (!valid_csrf_token($_POST['_csrf_token'] ?? null)) {
            http_response_code(403);
            $this->render('auth/error', [
                'title' => 'Yêu cầu không hợp lệ | Pet House',
                'message' => 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang và thử lại.',
            ]);
            return;
        }

        unset($_SESSION['user'], $_SESSION['_csrf_token']);
        session_regenerate_id(true);
        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Bạn đã đăng xuất.',
        ];
        $this->redirect('/');
    }

    private function users(): User
    {
        if ($this->users === null) {
            $this->users = new User(Database::connection());
        }

        return $this->users;
    }

    private function isAuthenticated(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    private function authenticatedHome(): string
    {
        return ($_SESSION['user']['role'] ?? '') === 'admin' ? '/admin' : '/account';
    }

    private function postText(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';

        if (!is_string($value)) {
            return '';
        }

        return $trim ? trim($value) : $value;
    }
}
