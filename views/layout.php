<?php
/**
 * @var string $content
 * @var string $title
 * @var array|null $filters
 */
use App\Core\Auth;
use App\Core\Session;

try {
    $currentUser = Auth::user();
} catch (Throwable) {
    $currentUser = null; // например, недоступна БД на странице ошибки
}
$flashes = Session::pullFlash();
$filters ??= null;
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Задачи') ?> · <?= e(config('name')) ?></title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css?v=7">
    <noscript><link rel="stylesheet" href="/assets/noscript.css?v=7"></noscript>
    <script src="/assets/app.js?v=7" defer></script>
</head>
<body class="<?= $currentUser ? 'is-auth' : 'is-guest' ?>">
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="topbar">
    <div class="topbar__inner container">
        <a class="brand" href="/" aria-label="<?= e(config('name')) ?> — на главную">
            <svg class="brand__logo" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="currentColor"/><path d="M9 16.5l4.5 4.5L23 11.5" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="brand__name"><?= e(config('name')) ?></span>
        </a>

        <?php if ($currentUser && $filters !== null): ?>
            <form class="topbar__search" method="get" action="/tasks" role="search">
                <label class="visually-hidden" for="top-search">Поиск задач</label>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="top-search" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Поиск по названию и описанию" maxlength="100">
                <?php foreach ($filters as $key => $value): ?>
                    <?php if ($key !== 'q' && $value !== '' && !($key === 'sort' && $value === 'created') && !($key === 'view' && $value === 'list')): ?>
                        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>

        <?php if ($currentUser): ?>
            <div class="topbar__user">
                <span class="avatar <?= avatar_class((int) $currentUser['id']) ?>" aria-hidden="true"><?= e(initials($currentUser['name'])) ?></span>
                <span class="topbar__name"><?= e($currentUser['name']) ?></span>
                <form method="post" action="/logout">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-icon-sm" type="submit" title="Выйти">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h11"/></svg>
                        <span class="btn-label">Выйти</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>

<main id="main" class="container main" tabindex="-1">
    <?php if ($flashes): ?>
        <div class="flashes" aria-live="polite">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash--<?= e($flash['type']) ?>" role="status">
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="flash__close" aria-label="Закрыть уведомление" hidden>×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<?php if ($currentUser): ?>
    <a class="fab" href="/tasks/create" aria-label="Новая задача" title="Новая задача">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    </a>
<?php endif; ?>

<div class="toasts" id="toasts" aria-live="polite" aria-atomic="false"></div>
</body>
</html>
