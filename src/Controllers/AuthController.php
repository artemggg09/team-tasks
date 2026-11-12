<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\LoginThrottle;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;

final class AuthController
{
    public function showLogin(): void
    {
        View::show('auth/login', ['title' => 'Вход', 'old' => [], 'errors' => []]);
    }

    public function login(): void
    {
        $data = Request::only(['email', 'password']);
        $data['password'] = (string) ($_POST['password'] ?? ''); // пароль не обрезаем
        $old = ['email' => $data['email']];

        $v = new Validator($data, [
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'max:72'],
        ], ['email' => 'Email', 'password' => 'Пароль']);

        if ($v->fails()) {
            View::show('auth/login', ['title' => 'Вход', 'old' => $old, 'errors' => $v->errors()], 422);
            return;
        }

        $key = LoginThrottle::key($data['email'], Request::ip());
        $wait = LoginThrottle::secondsLeft($key);
        if ($wait > 0) {
            View::show('auth/login', [
                'title'  => 'Вход',
                'old'    => $old,
                'errors' => ['form' => "Слишком много неудачных попыток. Попробуйте через {$wait} " . plural($wait, 'секунду', 'секунды', 'секунд') . '.'],
            ], 429);
            return;
        }

        $user = User::findByEmail($data['email']);
        // Проверяем хеш даже для несуществующего пользователя — одинаковое время ответа
        $hash = $user['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        if ($user === null || !password_verify($data['password'], $hash)) {
            LoginThrottle::hit($key);
            View::show('auth/login', ['title' => 'Вход', 'old' => $old, 'errors' => ['form' => 'Неверный email или пароль.']], 422);
            return;
        }

        LoginThrottle::clear($key);
        unset($user['password_hash']);
        Auth::login($user);
        Session::flash('success', 'С возвращением, ' . $user['name'] . '!');
        redirect('/tasks');
    }

    public function showRegister(): void
    {
        View::show('auth/register', ['title' => 'Регистрация', 'old' => [], 'errors' => []]);
    }

    public function register(): void
    {
        $data = Request::only(['name', 'email']);
        $data['password'] = (string) ($_POST['password'] ?? '');
        $data['password_confirmation'] = (string) ($_POST['password_confirmation'] ?? '');

        $v = new Validator($data, [
            'name'                  => ['required', 'min:2', 'max:100'],
            'email'                 => ['required', 'email', 'max:190'],
            'password'              => ['required', 'min:8', 'max:72'],
            'password_confirmation' => ['required', 'same:password'],
        ], ['name' => 'Имя', 'email' => 'Email', 'password' => 'Пароль', 'password_confirmation' => 'Подтверждение пароля']);

        if (!isset($v->errors()['email']) && User::emailExists($data['email'])) {
            $v->addError('email', 'Пользователь с таким email уже зарегистрирован.');
        }

        if ($v->fails()) {
            View::show('auth/register', [
                'title'  => 'Регистрация',
                'old'    => ['name' => $data['name'], 'email' => $data['email']],
                'errors' => $v->errors(),
            ], 422);
            return;
        }

        $id = User::create($data['name'], $data['email'], $data['password']);
        Auth::login(User::find($id) ?? []);
        Session::flash('success', 'Аккаунт создан. Добро пожаловать, ' . $data['name'] . '!');
        redirect('/tasks');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('info', 'Вы вышли из аккаунта.');
        redirect('/login');
    }
}
