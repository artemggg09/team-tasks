<?php
/**
 * @var array $filters
 * @var array $tasks
 * @var array $counts
 * @var array $users
 * @var int $activeCount
 * @var string $backUrl
 * @var array|null $focus
 * @var array $pace
 */
use App\Core\View;
use App\Models\Task;

$total = count($tasks);
$isBoard = $filters['view'] === 'board';
$statLabels = ['new' => 'Новых', 'in_progress' => 'В работе', 'done' => 'Завершено'];
?>
<header class="page-head">
    <div class="page-head__text">
        <p class="eyebrow">Рабочее пространство · Команда</p>
        <h1 class="page-title">Задачи</h1>
        <p class="page-lead">Планируйте работу и следите за прогрессом команды</p>
    </div>
    <nav class="stats" aria-label="Задачи по статусам">
        <?php foreach ($statLabels as $code => $label):
            $isActive = $filters['status'] === $code; ?>
            <a class="stat stat--<?= e($code) ?><?= $isActive ? ' is-active' : '' ?>"
               href="<?= e(tasks_url($filters, ['status' => $isActive ? '' : $code])) ?>"
               <?= $isActive ? 'aria-current="true"' : '' ?> title="<?= $isActive ? 'Показать все' : 'Показать только: ' . e(Task::STATUSES[$code]) ?>">
                <span class="stat__dot" aria-hidden="true"></span><?= e($label) ?>:
                <strong class="stat__count" data-count="<?= e($code) ?>"><?= (int) $counts[$code] ?></strong>
            </a>
        <?php endforeach; ?>
        <span class="visually-hidden">Всего: <span data-count="all"><?= (int) $counts['all'] ?></span></span>
    </nav>
</header>

<section class="hero" aria-label="Фокус дня и темп недели">
    <?= View::partial('partials/focus', ['focus' => $focus, 'backUrl' => $backUrl]) ?>
    <?= View::partial('partials/pace', ['pace' => $pace]) ?>
</section>

<details class="filters" id="filters"<?= $activeCount > 0 ? ' data-active="1"' : '' ?>>
    <summary class="filters__summary">
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
        Поиск и фильтры
        <?php if ($activeCount > 0): ?><span class="badge" aria-label="Активных фильтров: <?= $activeCount ?>"><?= $activeCount ?></span><?php endif; ?>
    </summary>

    <form class="filters__form" id="filter-form" method="get" action="/tasks">
        <div class="filters__row">
            <div class="field field--search">
                <label class="visually-hidden" for="f-q">Поиск</label>
                <svg class="icon field__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="f-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Поиск по задачам" maxlength="100">
            </div>
            <div class="field">
                <label class="visually-hidden" for="f-status">Статус</label>
                <select id="f-status" name="status">
                    <option value="">Все статусы</option>
                    <?php foreach (Task::STATUSES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $filters['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="visually-hidden" for="f-assignee">Ответственный</label>
                <select id="f-assignee" name="assignee">
                    <option value="">Все исполнители</option>
                    <option value="none"<?= $filters['assignee'] === 'none' ? ' selected' : '' ?>>Не назначен</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"<?= $filters['assignee'] === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="visually-hidden" for="f-priority">Приоритет</label>
                <select id="f-priority" name="priority">
                    <option value="">Любой приоритет</option>
                    <?php foreach (Task::PRIORITIES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $filters['priority'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a class="btn btn-ghost filters__reset" href="<?= e(tasks_url(['view' => $filters['view']])) ?>">Сбросить</a>
        </div>

        <div class="filters__row filters__row--secondary">
            <div class="chips">
                <label class="chip">
                    <input type="checkbox" name="mine" value="1"<?= $filters['mine'] === '1' ? ' checked' : '' ?>>
                    <span>Мои задачи</span>
                </label>
                <label class="chip">
                    <input type="checkbox" name="overdue" value="1"<?= $filters['overdue'] === '1' ? ' checked' : '' ?>>
                    <span>Просроченные</span>
                </label>
            </div>
            <div class="field field--sort">
                <label for="f-sort">Сортировка</label>
                <select id="f-sort" name="sort">
                    <?php foreach (Task::SORTS as $code => $sort): ?>
                        <option value="<?= e($code) ?>"<?= $filters['sort'] === $code ? ' selected' : '' ?>><?= e($sort['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isBoard): ?><input type="hidden" name="view" value="board"><?php endif; ?>
            <button class="btn btn-primary apply-btn" type="submit">Применить</button>
        </div>
    </form>
</details>

<div class="results-bar">
    <p class="results-bar__count" id="results-count" aria-live="polite">
        Найдено: <strong><?= $total ?></strong> <?= plural($total, 'задача', 'задачи', 'задач') ?>
        <?php if ($activeCount > 0): ?>
            · <a href="<?= e(tasks_url(['view' => $filters['view']])) ?>">сбросить фильтры</a>
        <?php endif; ?>
    </p>
    <div class="results-bar__tools">
        <div class="view-toggle" role="group" aria-label="Вид">
            <a class="view-toggle__btn<?= !$isBoard ? ' is-active' : '' ?>" href="<?= e(tasks_url($filters, ['view' => 'list'])) ?>"<?= !$isBoard ? ' aria-current="true"' : '' ?>>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                Карточки
            </a>
            <a class="view-toggle__btn<?= $isBoard ? ' is-active' : '' ?>" href="<?= e(tasks_url($filters, ['view' => 'board'])) ?>"<?= $isBoard ? ' aria-current="true"' : '' ?>>
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="5" height="18" rx="1.5"/><rect x="10" y="3" width="5" height="12" rx="1.5"/><rect x="17" y="3" width="4" height="15" rx="1.5"/></svg>
                Канбан
            </a>
        </div>
        <a class="btn btn-primary page-head__new" href="/tasks/create">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Новая задача
        </a>
    </div>
</div>

<?php if ($total === 0): ?>
    <section class="empty">
        <svg class="empty__icon" viewBox="0 0 64 64" aria-hidden="true"><rect x="10" y="8" width="44" height="50" rx="10"/><path d="M22 24h20M22 34h20M22 44h12"/></svg>
        <?php if ($activeCount > 0): ?>
            <h2 class="empty__title">Ничего не найдено</h2>
            <p class="empty__text">Попробуйте изменить запрос или сбросить фильтры.</p>
            <a class="btn btn-secondary" href="<?= e(tasks_url(['view' => $filters['view']])) ?>">Сбросить фильтры</a>
        <?php else: ?>
            <h2 class="empty__title">Задач пока нет</h2>
            <p class="empty__text">Создайте первую задачу и назначьте ответственного.</p>
            <a class="btn btn-primary" href="/tasks/create">Создать задачу</a>
        <?php endif; ?>
    </section>
<?php elseif ($isBoard): ?>
    <div class="board" id="board">
        <?php foreach (Task::STATUSES as $code => $label):
            $column = array_filter($tasks, static fn ($t) => $t['status'] === $code); ?>
            <section class="board__col board__col--<?= e($code) ?>" data-status="<?= e($code) ?>" aria-labelledby="col-<?= e($code) ?>">
                <h2 class="board__title" id="col-<?= e($code) ?>">
                    <span class="stat__dot" aria-hidden="true"></span><?= e($label) ?>
                    <span class="board__count"><?= count($column) ?></span>
                </h2>
                <div class="board__list" data-status="<?= e($code) ?>">
                    <?php foreach ($column as $task): ?>
                        <?= View::partial('partials/task-card', ['task' => $task, 'backUrl' => $backUrl]) ?>
                    <?php endforeach; ?>
                    <p class="board__empty">Перетащите задачу сюда</p>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="task-grid" id="task-list">
        <?php foreach ($tasks as $task): ?>
            <?= View::partial('partials/task-card', ['task' => $task, 'backUrl' => $backUrl]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
