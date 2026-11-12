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
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required maxlength="190" autofocus<?= $invalid('email') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'email']) ?>
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required maxlength="72"<?= $invalid('password') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'password']) ?>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>

        <p class="auth__switch">Нет аккаунта? <a href="/register">Зарегистрироваться</a></p>
        <p class="auth__demo">Демо-доступ: <code>demo@example.com</code> / <code>demo12345</code></p>
    </div>
</section>
