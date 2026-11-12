<?php
/**
 * @var array $task
 * @var string $backUrl
 */
use App\Models\Task;

$id = (int) $task['id'];
$editUrl = '/tasks/' . $id . '/edit?back=' . rawurlencode($backUrl);
$canManage = !empty($task['can_manage']);
$canStatus = !empty($task['can_status']);
?>
<article class="task-card status-<?= e($task['status']) ?><?= $task['overdue'] ? ' is-overdue' : '' ?><?= $canManage ? '' : ' is-readonly' ?>" id="task-<?= $id ?>" data-id="<?= $id ?>" data-status="<?= e($task['status']) ?>">
    <div class="task-card__top">
        <?php if ($canStatus): ?>
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
        <?php else: ?>
            <span class="tag tag--<?= e($task['status']) ?>" title="Статус меняет только ответственный"><?= e(Task::STATUSES[$task['status']]) ?></span>
        <?php endif; ?>
        <span class="priority priority--<?= e($task['priority']) ?>" title="Приоритет">
            <span class="priority__dot" aria-hidden="true"></span><?= e(Task::PRIORITIES[$task['priority']] ?? $task['priority']) ?>
        </span>
    </div>

    <h3 class="task-card__title"><a href="<?= e($editUrl) ?>"><?= e($task['title']) ?></a></h3>

    <?php if (!empty($task['description'])): ?>
        <p class="task-card__desc"><?= e(mb_strimwidth($task['description'], 0, 180, '…')) ?></p>
    <?php endif; ?>

    <div class="task-card__meta">
        <?php if ($task['due_date']): ?>
            <span class="due<?= $task['overdue'] ? ' due--overdue' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                <span class="visually-hidden">Срок:</span>
                <time datetime="<?= e($task['due_date']) ?>"><?= e(format_date($task['due_date'])) ?></time>
                <span class="due__label"><?= $task['overdue'] ? 'просрочена' : '' ?></span>
            </span>
        <?php else: ?>
            <span class="due due--none">Без срока</span>
        <?php endif; ?>
        <span class="author" title="Автор">от <?= e($task['author_name'] ?? '—') ?></span>
    </div>

    <div class="task-card__foot">
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

        <?php if ($canManage): ?>
        <div class="task-card__menu">
            <a class="icon-btn" href="<?= e($editUrl) ?>" title="Изменить" aria-label="Изменить задачу «<?= e($task['title']) ?>»">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg>
            </a>
            <details class="confirm">
                <summary class="icon-btn icon-btn--danger" title="Удалить" aria-label="Удалить задачу «<?= e($task['title']) ?>»">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                </summary>
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
        <?php else: ?>
            <span class="readonly-mark" title="Только просмотр: менять задачу могут автор или ответственный">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                <span class="visually-hidden">Только просмотр</span>
            </span>
        <?php endif; ?>
    </div>
</article>
