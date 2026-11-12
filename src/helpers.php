<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Экранирование вывода в HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Доступ к конфигурации через точку: config('db.dsn').
 */
function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/config.php';
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_production(): bool
{
    return config('env') === 'production';
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // За прокси (Render, Railway) протокол приходит в заголовке
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function redirect(string $path, int $status = 303): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

/**
 * Дата из БД (Y-m-d или Y-m-d H:i:s) в формат дд.мм.гггг.
 */
function format_date(?string $date): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? '' : date('d.m.Y', $ts);
}

/**
 * Дата словами: «12 ноября».
 */
function ru_date(?string $date = null): string
{
    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $ts = $date === null ? time() : (strtotime($date) ?: time());
    return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1];
}

/**
 * Русское склонение: plural(5, 'задача', 'задачи', 'задач').
 */
function plural(int $n, string $one, string $few, string $many): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return $many;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $few;
    }
    if ($n1 === 1) {
        return $one;
    }
    return $many;
}

/**
 * Инициалы по имени: «Анна Смирнова» → «АС».
 */
function initials(?string $name): string
{
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}

/**
 * Стабильный цвет аватара по id пользователя (8 вариантов в CSS).
 */
function avatar_class(int $id): string
{
    return 'avatar--c' . ($id % 8);
}

/**
 * Текущий URL /tasks с изменёнными параметрами запроса.
 */
function tasks_url(array $filters, array $override = []): string
{
    $params = array_filter(
        array_merge($filters, $override),
        static fn ($v) => $v !== '' && $v !== null
    );
    // Значения по умолчанию в URL не пишем
    if (($params['sort'] ?? '') === 'created') {
        unset($params['sort']);
    }
    if (($params['view'] ?? '') === 'list') {
        unset($params['view']);
    }
    $query = http_build_query($params);
    return '/tasks' . ($query !== '' ? '?' . $query : '');
}
