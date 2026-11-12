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
        $stmt = $db->prepare('INSERT INTO tasks (title, description, status, priority, due_date, assignee_id, author_id, created_at, updated_at)
            VALUES (:title, :description, :status, :priority, :due_date, :assignee_id, :author_id, :created_at, :updated_at)');
        $stmt->execute(self::bind($data) + ['author_id' => $authorId, 'created_at' => $now, 'updated_at' => $now]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET title = :title, description = :description, status = :status,
            priority = :priority, due_date = :due_date, assignee_id = :assignee_id, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(self::bind($data) + ['updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE tasks SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$status, date('Y-m-d H:i:s'), $id]);
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
