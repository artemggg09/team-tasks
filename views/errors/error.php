<?php
/** @var int $code @var string $title @var string $message @var Throwable|null $debug */
?>
<section class="empty empty--page">
    <p class="empty__code"><?= (int) $code ?></p>
    <h1 class="empty__title"><?= e($title) ?></h1>
    <p class="empty__text"><?= e($message) ?></p>
    <a class="btn btn-primary" href="/">На главную</a>
    <?php if ($debug !== null): ?>
        <details class="debug" open>
            <summary><?= e(get_class($debug)) ?>: <?= e($debug->getMessage()) ?></summary>
            <pre><?= e($debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) ?></pre>
        </details>
    <?php endif; ?>
</section>
