<?php

declare(strict_types=1);

/*
 * Перенос данных из SQLite в текущую БД (MySQL из .env):
 *   php database/import-sqlite.php [путь к .sqlite]
 * По умолчанию берётся storage/database.sqlite. Таблицы в целевой БД должны быть пустыми.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Models\User;

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$source = $argv[1] ?? BASE_PATH . '/storage/database.sqlite';
if (!is_file($source)) {
    fwrite(STDERR, "Файл SQLite не найден: {$source}" . PHP_EOL);
    exit(1);
}

$target = Database::connection();
if (Database::driver() !== 'mysql') {
    fwrite(STDERR, 'Целевая БД должна быть MySQL — проверьте DB_DSN в .env' . PHP_EOL);
    exit(1);
}
Database::migrate();

$sqlite = new PDO('sqlite:' . $source, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

foreach (['users', 'tasks'] as $table) {
    if ((int) $target->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() > 0) {
        fwrite(STDERR, "В целевой таблице {$table} уже есть данные — импорт остановлен." . PHP_EOL);
        exit(1);
    }
}

$columns = [
    'users' => ['id', 'name', 'username', 'password_hash', 'created_at'],
    'tasks' => ['id', 'title', 'description', 'status', 'priority', 'due_date', 'assignee_id', 'author_id', 'created_at', 'updated_at', 'completed_at'],
];

$target->beginTransaction();
try {
    $report = [];
    foreach ($columns as $table => $cols) {
        $existing = array_column($sqlite->query("PRAGMA table_info({$table})")->fetchAll(), 'name');
        $selectCols = array_map(static fn ($c) => in_array($c, $existing, true) ? $c : "NULL AS {$c}", $cols);
        $rows = $sqlite->query('SELECT ' . implode(', ', $selectCols) . " FROM {$table} ORDER BY id")->fetchAll();

        // Старые файлы SQLite хранят email — логин берём из него
        if ($table === 'users' && !in_array('username', $existing, true)) {
            $emails = $sqlite->query('SELECT id, email FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['username'] = User::usernameFromEmail((string) ($emails[$row['id']] ?? 'user' . $row['id']));
            }
            unset($row);
        }

        $insert = $target->prepare("INSERT INTO {$table} (" . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        foreach ($rows as $row) {
            $insert->execute(array_values($row));
        }
        $report[] = "{$table}: " . count($rows);
    }
    $target->commit();
} catch (Throwable $e) {
    $target->rollBack();
    fwrite(STDERR, 'Ошибка импорта: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Импортировано из SQLite — ' . implode(', ', $report) . PHP_EOL;
