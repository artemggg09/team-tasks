<?php
/**
 * @var array $task
 * @var string $backUrl
 */
use App\Models\Task;

$id = (int) $task['id'];
?>
<article class="task-card status-<?= e($task['status']) ?><?= $task['overdue'] ? ' is-overdue' : '' ?>" id="task-<?= $id ?>" data-id="<?= $id ?>" data-status="<?= e($task['status']) ?>">
    <div class="task-card__top">
        <span class="priority priority--<?= e($task['priority']) ?>" title="Приоритет">
            <span class="priority__dot" aria-hidden="true"></span><?= e(Task::PRIORITIES[$task['priority']] ?? $task['priority']) ?>
        </span>
        <?php if ($task['due_date']): ?>
            <span class="due<?= $task['overdue'] ? ' due--overdue' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                <span class="visually-hidden">Срок:</span>
                <time datetime="<?= e($task['due_date']) ?>"><?= e(format_date($task['due_date'])) ?></time>
                <span class="due__label"><?= $task['overdue'] ? '· просрочена' : '' ?></span>
            </span>
        <?php endif; ?>
    </div>

    <h3 class="task-card__title"><a href="/tasks/<?= $id ?>/edit?back=<?= e(rawurlencode($backUrl)) ?>"><?= e($task['title']) ?></a></h3>

    <?php if (!empty($task['description'])): ?>
        <p class="task-card__desc"><?= e(mb_strimwidth($task['description'], 0, 180, '…')) ?></p>
    <?php endif; ?>

    <div class="task-card__people">
        <?php if ($task['assignee_id']): ?>
            <span class="person" title="Ответственный">
                <span class="avatar avatar--sm <?= avatar_class($task['assignee_id']) ?>" aria-hidden="true"><?= e(initials($task['assignee_name'])) ?></span>
                <span><span class="visually-hidden">Ответственный: </span><?= e($task['assignee_name']) ?></span>
            </span>
        <?php else: ?>
            <span class="person person--empty" title="Ответственный">
                <span class="avatar avatar--sm avatar--empty" aria-hidden="true">?</span>
                <span><span class="visually-hidden">Ответственный: </span>Не назначен</span>
            </span>
        <?php endif; ?>
        <span class="author">Автор: <?= e($task['author_name'] ?? '—') ?></span>
    </div>

    <div class="task-card__actions">
        <form class="status-form" method="post" action="/tasks/<?= $id ?>/status">
            <?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
            <label class="visually-hidden" for="status-<?= $id ?>">Статус задачи «<?= e($task['title']) ?>»</label>
            <span class="status-pill">
                <span class="status-pill__dot" aria-hidden="true"></span>
                <select class="status-select" id="status-<?= $id ?>" name="status">
                    <?php foreach (Task::STATUSES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $code === $task['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
            <button class="btn btn-secondary btn-sm nojs-only" type="submit">Сменить</button>
        </form>

        <div class="task-card__menu">
            <a class="btn btn-ghost btn-sm" href="/tasks/<?= $id ?>/edit?back=<?= e(rawurlencode($backUrl)) ?>">Изменить</a>
            <details class="confirm">
                <summary class="btn btn-ghost btn-sm btn-danger-text">Удалить</summary>
                <div class="confirm__panel" role="dialog" aria-label="Подтверждение удаления">
                    <p>Удалить задачу без возможности восстановления?</p>
                    <form method="post" action="/tasks/<?= $id ?>/delete">
                        <?= csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Да, удалить</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</article>
