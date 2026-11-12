<?php
/** @var array $old @var array $errors */
use App\Core\View;

$invalid = static fn (string $f): string => isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-error"' : '';
?>
<section class="auth">
    <div class="auth__card card">
        <h1 class="auth__title">Регистрация</h1>
        <p class="auth__lead">Создайте аккаунт, чтобы работать с задачами команды</p>

        <form method="post" action="/register" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Имя</label>
                <input id="name" type="text" name="name" value="<?= e($old['name'] ?? '') ?>" autocomplete="name" required maxlength="100" autofocus<?= $invalid('name') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'name']) ?>
            </div>
            <div class="field">
                <label for="username">Логин</label>
                <input id="username" type="text" name="username" value="<?= e($old['username'] ?? '') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]{3,32}"<?= $invalid('username') ?>>
                <p class="field__hint">3–32 символа: латинские буквы, цифры, подчёркивание. Регистр не важен.</p>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'username']) ?>
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" type="password" name="password" autocomplete="new-password" required minlength="12" maxlength="72"<?= $invalid('password') ?>>
                <p class="field__hint">Не короче 12 символов и не длиннее 72 байт (≈ 36 символов кириллицей).</p>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'password']) ?>
            </div>
            <div class="field">
                <label for="password_confirmation">Подтверждение пароля</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required maxlength="72"<?= $invalid('password_confirmation') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'password_confirmation']) ?>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Создать аккаунт</button>
        </form>

        <p class="auth__switch">Уже есть аккаунт? <a href="/login">Войти</a></p>
    </div>
</section>
