<?php

declare(strict_types=1);

/*
 * Тестовые данные: php database/seed.php
 *   без флагов — заполняет только пустую БД (безопасно при каждом деплое);
 *   --fresh    — удаляет все данные и заполняет заново.
 *
 * Учётные записи: anna, ivan, maria — пароль password1234 (смените или удалите на боевом сервере).
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Models\Task;
use App\Models\User;

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$db = Database::connection();
Database::migrate();

$fresh = in_array('--fresh', $argv, true);
$hasUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

if ($hasUsers && !$fresh) {
    echo 'В БД уже есть данные — сид пропущен (используйте --fresh для пересоздания).' . PHP_EOL;
    exit(0);
}

$db->beginTransaction();
try {
    if ($fresh) {
        $db->exec('DELETE FROM tasks');
        $db->exec('DELETE FROM users');
        $db->exec('DELETE FROM login_attempts');
    }

    $maria = User::create('Мария Кузнецова', 'maria', 'password1234');
    $anna = User::create('Анна Смирнова', 'anna', 'password1234');
    $ivan = User::create('Иван Петров', 'ivan', 'password1234');

    $day = static fn (int $offset): string => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));

    $tasks = [
        ['Подготовить макет главной страницы', 'Собрать варианты в Figma: десктоп и мобильная версия. Показать команде в пятницу.', 'in_progress', 'high', $day(2), $anna, $maria],
        ['Настроить резервное копирование БД', 'Ежедневный бэкап storage/database.sqlite в облачное хранилище.', 'new', 'high', $day(-2), $ivan, $maria],
        ['Написать README для проекта', 'Описание, запуск, деплой, скриншоты.', 'done', 'medium', $day(-1), $maria, $anna],
        ['Провести ретроспективу спринта', null, 'new', 'medium', $day(5), $maria, $ivan],
        ['Исправить вёрстку карточек на iPhone SE', 'На ширине 320px заголовок вылезает за границы карточки.', 'in_progress', 'medium', $day(-3), $maria, $anna],
        ['Обновить зависимости Docker-образа', 'Перейти на свежий php:8.2-apache и проверить сборку.', 'new', 'low', null, null, $ivan],
        ['Созвон с заказчиком по требованиям', 'Уточнить сценарии фильтрации и роли пользователей.', 'done', 'high', $day(-6), $anna, $maria],
        ['Добавить тёмную тему', 'Через prefers-color-scheme и CSS-переменные.', 'done', 'low', $day(-4), $ivan, $ivan],
        ['Проверить доступность форм', 'Label у всех полей, видимый фокус, контраст 4.5:1.', 'new', 'medium', $day(7), $anna, $maria],
        ['Подготовить демо для команды', 'Сценарий: регистрация → задача → смена статуса → фильтры.', 'in_progress', 'high', $day(1), $ivan, $anna],
        ['Собрать обратную связь от тестировщиков', null, 'new', 'low', $day(10), null, $maria],
    ];

    $i = count($tasks);
    foreach ($tasks as [$title, $description, $status, $priority, $due, $assignee, $author]) {
        $id = Task::create([
            'title'       => $title,
            'description' => (string) $description,
            'status'      => $status,
            'priority'    => $priority,
            'due_date'    => (string) $due,
            'assignee_id' => $assignee === null ? '' : (string) $assignee,
        ], $author);
        // Разносим даты создания, чтобы сортировка была наглядной
        $stmt = $db->prepare('UPDATE tasks SET created_at = ?, updated_at = ? WHERE id = ?');
        $created = date('Y-m-d H:i:s', strtotime('-' . ($i--) . ' hours'));
        $stmt->execute([$created, $created, $id]);
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Ошибка сида: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Тестовые данные добавлены: 3 пользователя, ' . count($tasks) . ' задач. Логины: anna, ivan, maria; пароль password1234' . PHP_EOL;
