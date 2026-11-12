<?php

declare(strict_types=1);

/*
 * Тесты регистрации пользователя без зависимостей:
 *   php tests/run.php                       — на SQLite в памяти
 *   TEST_DB_DSN="mysql:host=…;dbname=пустая_тестовая_бд" TEST_DB_USER=root php tests/run.php
 * На MySQL используйте отдельную пустую базу: тест создаёт в ней пользователей.
 */

putenv('DB_DSN=' . (getenv('TEST_DB_DSN') ?: 'sqlite::memory:'));
putenv('DB_USER=' . (getenv('TEST_DB_USER') ?: ''));
putenv('DB_PASS=' . (getenv('TEST_DB_PASS') ?: ''));
putenv('APP_LOG=');

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\Validator;
use App\Exceptions\UsernameTakenException;
use App\Models\User;

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line); // любой warning — провал
});

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ {$name}\n      " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

function check(bool $condition, string $message = 'условие не выполнено'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @param class-string<Throwable> $class */
function expectThrows(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        check($e instanceof $class, 'ожидалось ' . $class . ', получено ' . get_class($e) . ': ' . $e->getMessage());
        return $e;
    }
    throw new RuntimeException('ожидалось исключение ' . $class);
}

Database::migrate();
echo 'Драйвер БД: ' . Database::driver() . "\n";

$pw = 'correct-horse'; // 13 байт

test('создание: возвращает id, логин нормализован, created_at проставила БД', function () use ($pw) {
    $id = User::create('  Анна  ', '  Anna_Test ', $pw);
    check($id > 0, 'id не получен');
    $user = User::find($id);
    check($user['username'] === 'anna_test', 'username = ' . $user['username']);
    check($user['name'] === 'Анна', 'имя не обрезано: «' . $user['name'] . '»');
    check(!empty($user['created_at']), 'created_at пустой — DEFAULT не сработал');
});

test('пароль сохраняется хешем и проходит password_verify', function () use ($pw) {
    $row = User::findByUsername('anna_test');
    check($row['password_hash'] !== $pw && password_verify($pw, $row['password_hash']));
});

test('дубликат логина (другой регистр и пробелы) → UsernameTakenException с PDOException 23000', function () use ($pw) {
    $e = expectThrows(UsernameTakenException::class, fn () => User::create('Другая', ' ANNA_TEST', $pw));
    check($e->username === 'anna_test');
    check($e->getPrevious() instanceof PDOException && $e->getPrevious()->getCode() === '23000', 'нет исходной PDOException 23000');
});

test('после отказа по дубликату пользователь не создан повторно', function () {
    $count = (int) Database::connection()->query("SELECT COUNT(*) FROM users WHERE username = 'anna_test'")->fetchColumn();
    check($count === 1, "записей: {$count}");
});

test('другая ошибка 23000 (не дубликат) не выдаётся за «логин занят»', function () {
    $make = static fn (int $driverCode, string $msg): PDOException => new class ($driverCode, $msg) extends PDOException {
        public function __construct(int $driverCode, string $msg)
        {
            parent::__construct("SQLSTATE[23000]: Integrity constraint violation: {$driverCode} {$msg}");
            $this->code = '23000'; // как у настоящей PDOException: SQLSTATE строкой
            $this->errorInfo = ['23000', $driverCode, $msg];
        }
    };
    $m = new ReflectionMethod(User::class, 'isDuplicateKey');
    check($m->invoke(null, $make(1452, 'Cannot add or update a child row')) === false, 'FK-ошибка распознана как дубликат');
    check($m->invoke(null, $make(1048, "Column 'name' cannot be null")) === false, 'NOT NULL распознан как дубликат');
    check($m->invoke(null, $make(1062, "Duplicate entry 'x' for key 'users_username_unique'")) === true, 'MySQL 1062 не распознан');
});

test('имя: пустое и длиннее 100 символов отклоняются; ровно 100 кириллицей — можно', function () use ($pw) {
    expectThrows(InvalidArgumentException::class, fn () => User::create('   ', 'name_empty', $pw));
    expectThrows(InvalidArgumentException::class, fn () => User::create(str_repeat('я', 101), 'name_long', $pw));
    check(User::create(str_repeat('я', 100), 'name_100', $pw) > 0);
    check(User::create('Я', 'name_1', $pw) > 0, 'имя из 1 символа должно проходить');
});

test('логин: формат /^[a-z0-9_]{3,32}$/ после нормализации', function () use ($pw) {
    foreach (['ab', 'a.bc', 'a-bc', 'анна', 'a b c', str_repeat('a', 33), ''] as $bad) {
        expectThrows(InvalidArgumentException::class, fn () => User::create('Тест', $bad, $pw));
    }
    check(User::create('Тест', str_repeat('b', 32), $pw) > 0, '32 символа должны проходить');
    check(User::create('Тест', 'ABC', $pw) > 0, 'ABC → abc должен проходить');
});

test('пароль: 12–72 байта (не символа)', function () {
    expectThrows(InvalidArgumentException::class, fn () => User::create('Тест', 'pw_11', str_repeat('x', 11)));
    expectThrows(InvalidArgumentException::class, fn () => User::create('Тест', 'pw_73', str_repeat('x', 73)));
    expectThrows(InvalidArgumentException::class, fn () => User::create('Тест', 'pw_cyr_37', str_repeat('я', 37))); // 74 байта
    check(User::create('Тест', 'pw_12', str_repeat('x', 12)) > 0);
    check(User::create('Тест', 'pw_72', str_repeat('x', 72)) > 0);
    check(User::create('Тест', 'pw_cyr_6', 'пароль') > 0, '6 кириллических символов = 12 байт');
});

test('нормализация: mb_strtolower UTF-8, одна и та же при регистрации и входе', function () {
    check(User::normalize('  ÄBC_Ü ') === 'äbc_ü', 'mb_strtolower не применён');
    check(User::findByUsername('  ANNA_TEST  ') !== null, 'вход с другим регистром не находит пользователя');
});

test('rehashIfNeeded: устаревший хеш (bcrypt cost 4) пересчитывается, актуальный — нет', function () use ($pw) {
    $db = Database::connection();
    $old = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 4]);
    $db->prepare('INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)')->execute(['Старый', 'old_hash', $old]);
    $user = User::findByUsername('old_hash');

    check(User::rehashIfNeeded((int) $user['id'], $old, $pw) === true, 'устаревший хеш не обновлён');
    $new = User::findByUsername('old_hash')['password_hash'];
    check($new !== $old && !password_needs_rehash($new, PASSWORD_DEFAULT), 'новый хеш не соответствует PASSWORD_DEFAULT');
    check(password_verify($pw, $new), 'после пересчёта пароль не подходит');

    check(User::rehashIfNeeded((int) $user['id'], $new, $pw) === false, 'актуальный хеш пересчитан повторно');
    check(User::findByUsername('old_hash')['password_hash'] === $new, 'актуальный хеш изменился');
});

test('rehashIfNeeded не перезаписывает хеш, если он сменился параллельно', function () use ($pw) {
    $db = Database::connection();
    $old = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 4]);
    $db->prepare('INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)')->execute(['Гонка', 'race_hash', $old]);
    $id = (int) User::findByUsername('race_hash')['id'];
    $changed = password_hash('другой-пароль-123', PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$changed, $id]);

    check(User::rehashIfNeeded($id, $old, $pw) === false, 'перезаписан уже сменённый пароль');
    check(User::findByUsername('race_hash')['password_hash'] === $changed);
});

test('валидатор формы: правило bytes совпадает с моделью', function () {
    $v = new Validator(['p' => str_repeat('я', 6)], ['p' => ['bytes:12,72']]);
    check(!$v->fails(), '12 байт кириллицей отклонены');
    $v = new Validator(['p' => str_repeat('x', 11)], ['p' => ['bytes:12,72']]);
    check($v->fails(), '11 байт пропущены');
    $v = new Validator(['p' => str_repeat('я', 37)], ['p' => ['bytes:12,72']]);
    check($v->fails(), '74 байта пропущены');
});

echo "\nИтого: {$passed} пройдено, {$failed} провалено\n";
exit($failed > 0 ? 1 : 0);
