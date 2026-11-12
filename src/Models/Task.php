<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Task
{
    public const STATUSES = [
        'new'         => 'Новая',
        'in_progress' => 'В работе',
        'done'        => 'Завершена',
    ];

    public const PRIORITIES = [
        'high'   => 'Высокий',
        'medium' => 'Средний',
        'low'    => 'Низкий',
    ];

    /** Белый список сортировок: ключ из URL → ORDER BY */
    public const SORTS = [
        'created'  => ['label' => 'Сначала новые', 'sql' => 't.created_at DESC, t.id DESC'],
        'due'      => ['label' => 'По сроку', 'sql' => 'CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date ASC, t.id DESC'],
        'priority' => ['label' => 'По приоритету', 'sql' => "CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, t.created_at DESC, t.id DESC"],
    ];

    private const SELECT = 'SELECT t.*,
            a.name AS assignee_name,
            u.name AS author_name
        FROM tasks t
        LEFT JOIN users a ON a.id = t.assignee_id
        LEFT JOIN users u ON u.id = t.author_id';

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(self::SELECT . ' WHERE t.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::hydrate($row);
    }

    /**
     * Нормализует параметры фильтра из URL (неизвестные значения отбрасываются).
     *
     * @return array{q:string, status:string, assignee:string, priority:string, mine:string, overdue:string, sort:string, view:string}
     */
    public static function normalizeFilters(array $query): array
    {
        $str = static fn (string $key): string => is_string($query[$key] ?? null) ? trim($query[$key]) : '';

        $assignee = $str('assignee');
        if ($assignee !== 'none' && !ctype_digit($assignee)) {
            $assignee = '';
        }

        return [
            'q'        => mb_substr($str('q'), 0, 100),
            'status'   => array_key_exists($str('status'), self::STATUSES) ? $str('status') : '',
            'assignee' => $assignee,
            'priority' => array_key_exists($str('priority'), self::PRIORITIES) ? $str('priority') : '',
            'mine'     => $str('mine') === '1' ? '1' : '',
            'overdue'  => $str('overdue') === '1' ? '1' : '',
            'sort'     => array_key_exists($str('sort'), self::SORTS) ? $str('sort') : 'created',
            'view'     => $str('view') === 'board' ? 'board' : 'list',
        ];
    }

    /**
     * @return array{0:string, 1:array<string, mixed>}
     */
    private static function where(array $f, int $userId, bool $withStatus = true): array
    {
        $sql = [];
        $params = [];

        if ($f['q'] !== '') {
            $lower = Database::lowerFn();
            $like = '%' . addcslashes(mb_strtolower($f['q']), '%_\\') . '%';
            $sql[] = "({$lower}(t.title) LIKE :q1 ESCAPE '\\' OR {$lower}(COALESCE(t.description, '')) LIKE :q2 ESCAPE '\\')";
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($withStatus && $f['status'] !== '') {
            $sql[] = 't.status = :status';
            $params['status'] = $f['status'];
        }
        if ($f['assignee'] === 'none') {
            $sql[] = 't.assignee_id IS NULL';
        } elseif ($f['assignee'] !== '') {
            $sql[] = 't.assignee_id = :assignee';
            $params['assignee'] = (int) $f['assignee'];
        }
        if ($f['priority'] !== '') {
            $sql[] = 't.priority = :priority';
            $params['priority'] = $f['priority'];
        }
        if ($f['mine'] === '1') {
            $sql[] = 't.assignee_id = :me';
            $params['me'] = $userId;
        }
        if ($f['overdue'] === '1') {
            $sql[] = "t.due_date IS NOT NULL AND t.due_date < :today AND t.status <> 'done'";
            $params['today'] = date('Y-m-d');
        }

        return [$sql === [] ? '' : ' WHERE ' . implode(' AND ', $sql), $params];
    }

    /** @return array<int, array> */
    public static function search(array $filters, int $userId): array
    {
        [$where, $params] = self::where($filters, $userId);
        $order = self::SORTS[$filters['sort']]['sql'] ?? self::SORTS['created']['sql'];

        $stmt = Database::connection()->prepare(self::SELECT . $where . ' ORDER BY ' . $order);
        $stmt->execute($params);
        return array_map([self::class, 'hydrate'], $stmt->fetchAll());
    }

    /**
     * Количество задач по статусам с учётом всех фильтров, кроме самого статуса.
     *
     * @return array{new:int, in_progress:int, done:int, all:int}
     */
    public static function statusCounts(array $filters, int $userId): array
    {
        [$where, $params] = self::where($filters, $userId, false);
        $stmt = Database::connection()->prepare('SELECT t.status, COUNT(*) AS cnt FROM tasks t' . $where . ' GROUP BY t.status');
        $stmt->execute($params);

        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['cnt'];
            }
        }
        $counts['all'] = array_sum($counts);
        return $counts;
    }

    public static function create(array $data, int $authorId): int
    {
        $db = Database::connection();
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare('INSERT INTO tasks (title, description, status, priority, due_date, assignee_id, author_id, created_at, updated_at, completed_at)
            VALUES (:title, :description, :status, :priority, :due_date, :assignee_id, :author_id, :created_at, :updated_at, :completed_at)');
        $stmt->execute(self::bind($data) + [
            'author_id'    => $authorId,
            'created_at'   => $now,
            'updated_at'   => $now,
            'completed_at' => $data['status'] === 'done' ? $now : null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET title = :title, description = :description, status = :status,
            priority = :priority, due_date = :due_date, assignee_id = :assignee_id, updated_at = :updated_at,
            completed_at = ' . self::completedSql() . ' WHERE id = :id');
        $stmt->execute(self::bind($data) + [
            'updated_at' => date('Y-m-d H:i:s'),
            'id'         => $id,
            'is_done'    => $data['status'] === 'done' ? '1' : '0',
            'now'        => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET status = :status, updated_at = :updated_at,
            completed_at = ' . self::completedSql() . ' WHERE id = :id');
        $stmt->execute([
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'is_done'    => $status === 'done' ? '1' : '0',
            'now'        => date('Y-m-d H:i:s'),
            'id'         => $id,
        ]);
    }

    /**
     * Дата завершения: ставится при переходе в «Завершена», сохраняется при повторном сохранении, сбрасывается при возврате в работу.
     */
    private static function completedSql(): string
    {
        // Параметры PDO приходят строками: в SQLite '1' = 1 ложно, поэтому сравниваем со строкой
        return "CASE WHEN :is_done = '1' THEN COALESCE(completed_at, :now) ELSE NULL END";
    }

    /**
     * «Фокус дня»: одна незавершённая задача, с которой стоит начать.
     * Сначала мои, затем без исполнителя; внутри — просроченные, на сегодня, высокий приоритет, ближайший срок.
     */
    public static function focus(int $userId): ?array
    {
        $today = date('Y-m-d');
        $stmt = Database::connection()->prepare(self::SELECT . "
            WHERE t.status <> 'done' AND (t.assignee_id = :me OR t.assignee_id IS NULL)
            ORDER BY
                CASE WHEN t.assignee_id = :me2 THEN 0 ELSE 1 END,
                CASE WHEN t.due_date IS NOT NULL AND t.due_date < :today THEN 0
                     WHEN t.due_date = :today2 THEN 1 ELSE 2 END,
                CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END, t.due_date,
                CASE t.status WHEN 'in_progress' THEN 0 ELSE 1 END,
                t.created_at
            LIMIT 1");
        $stmt->execute(['me' => $userId, 'me2' => $userId, 'today' => $today, 'today2' => $today]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $task = self::hydrate($row);
        $task['focus_reason'] = match (true) {
            $task['overdue']                 => 'Срок уже прошёл — закройте её в первую очередь.',
            $task['due_date'] === $today     => 'Срок сегодня — успейте до конца дня.',
            $task['priority'] === 'high'     => 'Начните с этой задачи — она сильнее всего влияет на общий темп.',
            $task['due_date'] !== null       => 'Ближайшая по сроку задача — сделайте её, пока есть запас.',
            default                          => 'Самая важная из открытых задач — хороший старт дня.',
        };
        return $task;
    }

    public const PACE_SEGMENTS = 5;

    /**
     * Личный «темп недели» по задачам, где пользователь — ответственный (согласован с «фокусом дня»):
     * закрыто за неделю / (закрыто + открытые со сроком до воскресенья, включая просроченные).
     *
     * @return array{done:int, planned:int, overdue:int, filled:int, segments:int, level:string, note:string}
     */
    public static function weekPace(int $userId): array
    {
        $db = Database::connection();
        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $today = date('Y-m-d');

        $stmt = $db->prepare("SELECT
                SUM(CASE WHEN status = 'done' AND completed_at >= :week_start THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN status <> 'done' AND due_date IS NOT NULL AND due_date <= :week_end THEN 1 ELSE 0 END) AS open_due,
                SUM(CASE WHEN status <> 'done' AND due_date IS NOT NULL AND due_date < :today THEN 1 ELSE 0 END) AS overdue
            FROM tasks WHERE assignee_id = :me");
        $stmt->execute(['week_start' => $weekStart, 'week_end' => $weekEnd, 'today' => $today, 'me' => $userId]);
        $row = $stmt->fetch() ?: [];

        $done = (int) ($row['done'] ?? 0);
        $planned = $done + (int) ($row['open_due'] ?? 0);
        $overdue = (int) ($row['overdue'] ?? 0);

        if ($planned === 0) {
            $filled = 0;
            $level = 'idle';
        } else {
            // Хотя бы одна полоса: если сделано мало — одна красная
            $filled = max(1, min(self::PACE_SEGMENTS, (int) round($done / $planned * self::PACE_SEGMENTS)));
            $level = $filled >= 4 ? 'good' : ($filled === 3 ? 'warn' : 'bad');
        }

        $note = match (true) {
            $overdue > 0      => $overdue . ' ' . plural($overdue, 'ваша задача просрочена', 'ваши задачи просрочены', 'ваших задач просрочено'),
            $level === 'good' => 'Отличный темп — так держать',
            $level === 'warn' => 'Неплохо, но можно быстрее',
            $level === 'bad'  => 'Закрыто мало задач',
            default           => 'На этой неделе у вас нет задач со сроком',
        };

        return [
            'done'      => $done,
            'planned'   => $planned,
            'overdue'   => $overdue,
            'filled'    => $filled,
            'segments'  => self::PACE_SEGMENTS,
            'level'     => $level,
            'note'      => $note,
            'note_level' => $overdue > 0 ? 'bad' : $level,
        ];
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function isOverdue(array $task): bool
    {
        return !empty($task['due_date']) && $task['status'] !== 'done' && $task['due_date'] < date('Y-m-d');
    }

    private static function bind(array $data): array
    {
        return [
            'title'       => $data['title'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'status'      => $data['status'],
            'priority'    => $data['priority'],
            'due_date'    => $data['due_date'] !== '' ? $data['due_date'] : null,
            'assignee_id' => $data['assignee_id'] !== '' ? (int) $data['assignee_id'] : null,
        ];
    }

    private static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['assignee_id'] = $row['assignee_id'] !== null ? (int) $row['assignee_id'] : null;
        $row['author_id'] = (int) $row['author_id'];
        if ($row['due_date'] !== null) {
            $row['due_date'] = substr((string) $row['due_date'], 0, 10);
        }
        $row['overdue'] = self::isOverdue($row);
        return $row;
    }
}
