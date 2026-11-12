<?php
/**
 * @var array $task       введённые значения
 * @var array|null $existing  задача из БД при редактировании
 * @var array $errors
 * @var array $users
 * @var string $back
 */
use App\Core\View;
use App\Models\Task;

$isEdit = $existing !== null;
$action = $isEdit ? '/tasks/' . (int) $existing['id'] : '/tasks';
$invalid = static fn (string $f): string => isset($errors[$f]) ? ' aria-invalid="true" aria-describedby="' . $f . '-error"' : '';
?>
<div class="page-head page-head--narrow">
    <a class="back-link" href="<?= e($back) ?>">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        К списку задач
    </a>
    <h1 class="page-title"><?= $isEdit ? 'Редактирование задачи' : 'Новая задача' ?></h1>
</div>

<div class="card form-card">
    <?php if ($errors): ?>
        <div class="alert alert--error" role="alert">Проверьте поля формы: <?= count($errors) ?> <?= plural(count($errors), 'ошибка', 'ошибки', 'ошибок') ?>.</div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">

        <div class="field">
            <label for="title">Название <span class="req" aria-hidden="true">*</span></label>
            <input id="title" type="text" name="title" value="<?= e($task['title']) ?>" required maxlength="200" autofocus<?= $invalid('title') ?>>
            <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'title']) ?>
        </div>

        <div class="field">
            <label for="description">Описание</label>
            <textarea id="description" name="description" rows="5" maxlength="5000"<?= $invalid('description') ?>><?= e($task['description']) ?></textarea>
            <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'description']) ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label for="assignee_id">Ответственный</label>
                <select id="assignee_id" name="assignee_id"<?= $invalid('assignee_id') ?>>
                    <option value="">Не назначен</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"<?= (string) $task['assignee_id'] === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'assignee_id']) ?>
            </div>

            <div class="field">
                <label for="due_date">Срок</label>
                <input id="due_date" type="date" name="due_date" value="<?= e($task['due_date']) ?>"<?= $invalid('due_date') ?>>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'due_date']) ?>
            </div>

            <div class="field">
                <label for="status">Статус</label>
                <select id="status" name="status"<?= $invalid('status') ?>>
                    <?php foreach (Task::STATUSES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $task['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'status']) ?>
            </div>

            <div class="field">
                <label for="priority">Приоритет</label>
                <select id="priority" name="priority"<?= $invalid('priority') ?>>
                    <?php foreach (Task::PRIORITIES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $task['priority'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= View::partial('partials/field-error', ['errors' => $errors, 'field' => 'priority']) ?>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <p class="form-meta">
                Автор: <?= e($existing['author_name'] ?? '—') ?> · создана <?= e(format_date($existing['created_at'])) ?>
                <?php if ($existing['updated_at'] !== $existing['created_at']): ?> · изменена <?= e(format_date($existing['updated_at'])) ?><?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Сохранить' : 'Создать задачу' ?></button>
            <a class="btn btn-ghost" href="<?= e($back) ?>">Отмена</a>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <details class="confirm confirm--inline">
            <summary class="btn btn-ghost btn-danger-text">Удалить задачу</summary>
            <div class="confirm__panel">
                <p>Задача будет удалена без возможности восстановления.</p>
                <form method="post" action="/tasks/<?= (int) $existing['id'] ?>/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-danger" type="submit">Да, удалить</button>
                </form>
            </div>
        </details>
    <?php endif; ?>
</div>
