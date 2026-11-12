<?php
/**
 * @var array|null $focus
 * @var string $backUrl
 * @var array $owner
 */
use App\Models\Task;
?>
<div class="focus">
    <div class="focus__icon" aria-hidden="true">
        <svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="12"/><circle cx="16" cy="16" r="7.5"/><circle cx="16" cy="16" r="3" class="focus__core"/></svg>
    </div>
    <div class="focus__body">
        <p class="focus__eyebrow"><span>Фокус дня</span><?= $owner['self'] ? '' : ' · ' . e($owner['name']) ?> · <?= e(ru_date()) ?></p>
        <?php if ($focus !== null): ?>
            <h2 class="focus__title">
                <a href="/tasks/<?= (int) $focus['id'] ?>/edit?back=<?= e(rawurlencode($backUrl)) ?>"><?= e($focus['title']) ?></a>
            </h2>
            <p class="focus__text"><?= e($focus['focus_reason']) ?></p>
            <p class="focus__meta">
                <span class="tag tag--<?= e($focus['status']) ?>"><?= e(Task::STATUSES[$focus['status']]) ?></span>
                <span class="priority priority--<?= e($focus['priority']) ?>"><span class="priority__dot" aria-hidden="true"></span><?= e(Task::PRIORITIES[$focus['priority']]) ?></span>
                <?php if ($focus['due_date']): ?>
                    <span class="due<?= $focus['overdue'] ? ' due--overdue' : '' ?>">срок <?= e(format_date($focus['due_date'])) ?></span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <h2 class="focus__title">Все задачи закрыты</h2>
            <p class="focus__text"><?= $owner['self'] ? 'Открытых задач для вас нет — возьмите новую или создайте задачу.' : 'У этого исполнителя нет открытых задач.' ?></p>
        <?php endif; ?>
    </div>
    <div class="focus__action">
        <?php if ($focus !== null): ?>
            <form method="post" action="/tasks/<?= (int) $focus['id'] ?>/status">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="done">
                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                <button class="btn btn-secondary" type="submit">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    Отметить готовой
                </button>
            </form>
        <?php else: ?>
            <a class="btn btn-secondary" href="/tasks/create">Создать задачу</a>
        <?php endif; ?>
    </div>
</div>
