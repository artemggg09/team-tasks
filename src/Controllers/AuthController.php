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
        $data = Request::only(['username']);
        $data['username'] = User::normalize($data['username']);
        $data['password'] = Request::raw('password'); // пароль не обрезаем
        $old = ['username' => $data['username']];

        $v = new Validator($data, [
            'username' => ['required', 'max:30'],
            'password' => ['required', 'max:72'],
        ], ['username' => 'Логин', 'password' => 'Пароль']);

        if ($v->fails()) {
            View::show('auth/login', ['title' => 'Вход', 'old' => $old, 'errors' => $v->errors()], 422);
            return;
        }

        $key = LoginThrottle::key($data['username'], Request::ip());
        $wait = LoginThrottle::secondsLeft($key);
        if ($wait > 0) {
            View::show('auth/login', [
                'title'  => 'Вход',
                'old'    => $old,
                'errors' => ['form' => "Слишком много неудачных попыток. Попробуйте через {$wait} " . plural($wait, 'секунду', 'секунды', 'секунд') . '.'],
            ], 429);
            return;
        }

        $user = User::findByUsername($data['username']);
        // Проверяем хеш даже для несуществующего пользователя — одинаковое время ответа
        $hash = $user['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_DEFAULT);
        if ($user === null || !password_verify($data['password'], $hash)) {
            LoginThrottle::hit($key);
            View::show('auth/login', ['title' => 'Вход', 'old' => $old, 'errors' => ['form' => 'Неверный логин или пароль.']], 422);
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
        $data = Request::only(['name', 'username']);
        $data['username'] = User::normalize($data['username']);
        $data['password'] = Request::raw('password');
        $data['password_confirmation'] = Request::raw('password_confirmation');

        $v = new Validator($data, [
            'name'                  => ['required', 'min:2', 'max:100'],
            'username'              => ['required', 'username'],
            'password'              => ['required', 'min:8', 'max:72'],
            'password_confirmation' => ['required', 'same:password'],
        ], ['name' => 'Имя', 'username' => 'Логин', 'password' => 'Пароль', 'password_confirmation' => 'Подтверждение пароля']);

        if (!isset($v->errors()['username']) && User::usernameExists($data['username'])) {
            $v->addError('username', 'Этот логин уже занят — выберите другой.');
        }

        if ($v->fails()) {
            View::show('auth/register', [
                'title'  => 'Регистрация',
                'old'    => ['name' => $data['name'], 'username' => $data['username']],
                'errors' => $v->errors(),
            ], 422);
            return;
        }

        $id = User::create($data['name'], $data['username'], $data['password']);
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
