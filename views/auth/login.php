<?php
/** @var array $old @var array $errors */
use App\Core\View;

$invalid = static fn (string $f): string => isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-error"' : '';
?>
<section class="auth">
    <div class="auth__card card">
        <h1 class="auth__title">Вход</h1>
        <p class="auth__lead">Менеджер задач вашей команды</p>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="username">Логин</label>
                <input id="username" type="text" name="username" value="<?= e($old['username'] ?? '') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required maxlength="32" autofocus<?= $invalid('username') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'username']) ?>
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required maxlength="72"<?= $invalid('password') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'password']) ?>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>

        <p class="auth__switch">Нет аккаунта? <a href="/register">Зарегистрироваться</a></p>
    </div>
</section>
