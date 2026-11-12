<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Response
{
    private const ERRORS = [
        403 => ['Доступ запрещён', 'Изменять задачу может только её автор или ответственный.'],
        404 => ['Страница не найдена', 'Такой страницы нет. Возможно, задачу удалили или ссылка устарела.'],
        405 => ['Метод не поддерживается', 'Этот адрес не принимает такой запрос.'],
        419 => ['Сессия устарела', 'Форма устарела или отправлена не из приложения. Обновите страницу и попробуйте ещё раз.'],
        429 => ['Слишком много запросов', 'Подождите немного и попробуйте снова.'],
        500 => ['Что-то пошло не так', 'На сервере произошла ошибка. Мы уже записали её в лог.'],
    ];

    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public static function error(int $code, ?Throwable $exception = null, ?string $customMessage = null): void
    {
        [$title, $message] = self::ERRORS[$code] ?? self::ERRORS[500];
        $message = $customMessage ?? $message;

        if (Request::wantsJson()) {
            if (!headers_sent()) {
                self::json(['ok' => false, 'error' => $message], $code);
            }
            return;
        }

        $debug = $exception !== null && !is_production() ? $exception : null;
        try {
            $html = View::render('errors/error', [
                'title'   => $title,
                'code'    => $code,
                'message' => $message,
                'debug'   => $debug,
            ]);
        } catch (Throwable $e) {
            // Шаблон тоже упал (например, нет БД) — отдаём простой HTML
            error_log('Error page failed: ' . $e);
            $html = '<!doctype html><html lang="ru"><meta charset="utf-8"><title>' . e($title)
                . '</title><body style="font-family:system-ui;padding:2rem"><h1>' . $code . ' — ' . e($title)
                . '</h1><p>' . e($message) . '</p><p><a href="/">На главную</a></p></body></html>';
        }

        if (headers_sent()) {
            echo $html;
            return;
        }
        self::html($html, $code);
    }
}
