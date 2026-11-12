<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    /**
     * Рендерит шаблон внутри layout.
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, ['content' => $content] + $data);
    }

    public static function partial(string $__template, array $__data = []): string
    {
        $__file = BASE_PATH . '/views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException('Шаблон не найден: ' . $__template);
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public static function show(string $template, array $data = [], int $status = 200): void
    {
        Response::html(self::render($template, $data), $status);
    }
}
