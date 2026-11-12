<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\Task;
use App\Models\User;

final class TaskController
{
    public function index(): void
    {
        $filters = Task::normalizeFilters($_GET);
        $userId = (int) Auth::id();
        $tasks = array_map(static fn (array $t) => self::withRights($t, $userId), Task::search($filters, $userId));
        $owner = $this->paceOwner($filters);
        $focus = $this->focusFor($owner, $userId);

        $active = array_filter([
            $filters['q'], $filters['status'], $filters['assignee'], $filters['priority'],
            $filters['mine'], $filters['overdue'],
        ], static fn ($v) => $v !== '');

        View::show('tasks/index', [
            'title'       => 'Задачи',
            'filters'     => $filters,
            'tasks'       => $tasks,
            'counts'      => Task::statusCounts($filters, $userId),
            'users'       => User::all(),
            'activeCount' => count($active),
            'backUrl'     => tasks_url($filters),
            'focus'       => $focus,
            'pace'        => Task::weekPace($owner['id']),
            'owner'       => $owner,
        ]);
    }

    /**
     * Чей «фокус дня» и «темп недели» показывать: выбранного в фильтре исполнителя или текущего пользователя.
     *
     * @return array{id:int, name:string, self:bool}
     */
    private function paceOwner(array $filters): array
    {
        $me = (int) Auth::id();
        if (ctype_digit($filters['assignee']) && (int) $filters['assignee'] !== $me) {
            $user = User::find((int) $filters['assignee']);
            if ($user !== null) {
                return ['id' => (int) $user['id'], 'name' => $user['name'], 'self' => false];
            }
        }
        return ['id' => $me, 'name' => (string) (Auth::user()['name'] ?? ''), 'self' => true];
    }

    public function create(): void
    {
        $this->form([
            'title' => '', 'description' => '', 'status' => 'new', 'priority' => 'medium',
            'due_date' => '', 'assignee_id' => '',
        ]);
    }

    public function store(): void
    {
        $data = $this->input();
        $v = $this->validate($data);
        if ($v->fails()) {
            $this->form($data, $v->errors(), null, 422);
            return;
        }
        Task::create($data, (int) Auth::id());
        Session::flash('success', 'Задача «' . $data['title'] . '» создана.');
        redirect('/tasks');
    }

    public function edit(int $id): void
    {
        $task = Task::find($id);
        if ($task === null) {
            Response::error(404);
            return;
        }
        $existing = $task;
        $task['assignee_id'] = (string) ($task['assignee_id'] ?? '');
        $task['due_date'] = (string) ($task['due_date'] ?? '');
        $task['description'] = (string) ($task['description'] ?? '');
        $this->form($task, [], $existing);
    }

    public function update(int $id): void
    {
        $task = Task::find($id);
        if ($task === null) {
            Response::error(404);
            return;
        }
        if (!$this->authorize($task)) {
            return;
        }
        $data = $this->input();
        if (!Task::canChangeStatus($task, (int) Auth::id())) {
            $data['status'] = $task['status']; // статус меняет только ответственный
        }
        $v = $this->validate($data);
        if ($v->fails()) {
            $this->form($data, $v->errors(), $task, 422);
            return;
        }
        Task::update($id, $data);
        Session::flash('success', 'Задача «' . $data['title'] . '» сохранена.');
        redirect(Request::back());
    }

    public function destroy(int $id): void
    {
        $task = Task::find($id);
        if ($task === null) {
            Response::error(404);
            return;
        }
        if (!$this->authorize($task)) {
            return;
        }
        Task::delete($id);
        Session::flash('success', 'Задача «' . $task['title'] . '» удалена.');
        redirect(Request::back());
    }

    /**
     * Быстрая смена статуса: JSON для fetch, редирект для обычной формы.
     */
    public function status(int $id): void
    {
        $task = Task::find($id);
        if ($task === null) {
            Response::error(404);
            return;
        }

        if (!$this->authorize($task, Task::canChangeStatus($task, (int) Auth::id()), 'Статус меняет только ответственный за задачу.')) {
            return;
        }

        $status = Request::input('status');
        if (!array_key_exists($status, Task::STATUSES)) {
            if (Request::wantsJson()) {
                Response::json(['ok' => false, 'error' => 'Неизвестный статус.'], 422);
                return;
            }
            Session::flash('error', 'Неизвестный статус.');
            redirect(Request::back());
        }

        Task::updateStatus($id, $status);
        $task['status'] = $status;
        $back = Request::back();

        if (Request::wantsJson()) {
            // Счётчики считаем с теми же фильтрами, что и на странице пользователя
            parse_str((string) parse_url($back, PHP_URL_QUERY), $query);
            $filters = Task::normalizeFilters($query);
            Response::json([
                'ok'   => true,
                'task' => [
                    'id'           => $id,
                    'status'       => $status,
                    'status_label' => Task::STATUSES[$status],
                    'overdue'      => Task::isOverdue($task),
                ],
                'counts'  => Task::statusCounts($filters, (int) Auth::id()),
                'pace'    => Task::weekPace($this->paceOwner($filters)['id']),
                // «Фокус дня» мог смениться — отдаём готовый HTML блока
                'focus_html' => View::partial('partials/focus', [
                    'focus'   => $this->focusFor($this->paceOwner($filters), (int) Auth::id()),
                    'backUrl' => $back,
                    'owner'   => $this->paceOwner($filters),
                ]),
                'message' => '«' . $task['title'] . '» → ' . Task::STATUSES[$status],
            ]);
            return;
        }

        Session::flash('success', 'Статус задачи «' . $task['title'] . '»: ' . Task::STATUSES[$status] . '.');
        redirect($back . '#task-' . $id);
    }

    /**
     * Проверка прав на изменение задачи; при отказе отдаёт 403 (JSON для fetch).
     */
    private function authorize(array $task, ?bool $allowed = null, string $message = 'Менять задачу может только её автор или ответственный.'): bool
    {
        if ($allowed ?? Task::canManage($task, (int) Auth::id())) {
            return true;
        }
        if (Request::wantsJson()) {
            Response::json(['ok' => false, 'error' => $message], 403);
            return false;
        }
        Response::error(403, null, $message);
        return false;
    }

    /** Флаги прав для шаблонов: can_manage — редактировать/удалять, can_status — менять статус. */
    private static function withRights(array $task, int $userId): array
    {
        $task['can_manage'] = Task::canManage($task, $userId);
        $task['can_status'] = Task::canChangeStatus($task, $userId);
        return $task;
    }

    private function focusFor(array $owner, int $userId): ?array
    {
        $focus = Task::focus($owner['id'], $owner['self']);
        return $focus === null ? null : self::withRights($focus, $userId);
    }

    /** @return array<string, string> */
    private function input(): array
    {
        $data = Request::only(['title', 'description', 'status', 'priority', 'due_date', 'assignee_id']);
        // Нормализуем переводы строк, чтобы длина считалась одинаково
        $data['description'] = str_replace("\r\n", "\n", $data['description']);
        return $data;
    }

    private function validate(array $data): Validator
    {
        $v = new Validator($data, [
            'title'       => ['required', 'max:200'],
            'description' => ['max:5000'],
            'status'      => ['required', 'in:' . implode(',', array_keys(Task::STATUSES))],
            'priority'    => ['required', 'in:' . implode(',', array_keys(Task::PRIORITIES))],
            'due_date'    => ['date'],
        ], ['title' => 'Название', 'status' => 'Статус', 'priority' => 'Приоритет']);

        $assignee = $data['assignee_id'];
        if ($assignee !== '' && (!ctype_digit($assignee) || !User::exists((int) $assignee))) {
            $v->addError('assignee_id', 'Выберите пользователя из списка.');
        }
        return $v;
    }

    private function form(array $task, array $errors = [], ?array $existing = null, int $status = 200): void
    {
        View::show('tasks/form', [
            'title'    => $existing ? 'Редактирование задачи' : 'Новая задача',
            'task'     => $task,
            'existing' => $existing,
            'readonly' => $existing !== null && !Task::canManage($existing, (int) Auth::id()),
            'statusLocked' => $existing !== null && !Task::canChangeStatus($existing, (int) Auth::id()),
            'errors'   => $errors,
            'users'    => User::all(),
            'back'     => Request::back(),
        ], $status);
    }
}
