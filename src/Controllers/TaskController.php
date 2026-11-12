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
        $tasks = Task::search($filters, $userId);

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
            'focus'       => Task::focus($userId),
            'pace'        => Task::weekPace($userId),
        ]);
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
        $task['assignee_id'] = (string) ($task['assignee_id'] ?? '');
        $task['due_date'] = (string) ($task['due_date'] ?? '');
        $task['description'] = (string) ($task['description'] ?? '');
        $this->form($task, [], $task);
    }

    public function update(int $id): void
    {
        $task = Task::find($id);
        if ($task === null) {
            Response::error(404);
            return;
        }
        $data = $this->input();
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
                'pace'    => Task::weekPace((int) Auth::id()),
                'message' => '«' . $task['title'] . '» → ' . Task::STATUSES[$status],
            ]);
            return;
        }

        Session::flash('success', 'Статус задачи «' . $task['title'] . '»: ' . Task::STATUSES[$status] . '.');
        redirect($back . '#task-' . $id);
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
            'errors'   => $errors,
            'users'    => User::all(),
            'back'     => Request::back(),
        ], $status);
    }
}
