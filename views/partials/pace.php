<?php
/** @var array $pace */
?>
<div class="pace pace--<?= e($pace['level']) ?>" id="pace" aria-labelledby="pace-title">
    <div class="pace__head">
        <h2 class="pace__title" id="pace-title">Мой темп недели</h2>
        <span class="pace__ratio" data-pace="ratio"><?= (int) $pace['done'] ?> из <?= (int) $pace['planned'] ?></span>
    </div>
    <div class="pace__bar" role="meter" aria-label="Закрыто моих задач за неделю" aria-valuemin="0"
         aria-valuemax="<?= (int) $pace['planned'] ?>" aria-valuenow="<?= (int) $pace['done'] ?>" data-pace="bar">
        <?php for ($i = 0; $i < $pace['segments']; $i++): ?>
            <span class="pace__seg<?= $i < $pace['filled'] ? ' is-on' : '' ?>"></span>
        <?php endfor; ?>
    </div>
    <p class="pace__note pace__note--<?= e($pace['note_level']) ?>" data-pace="note">
        <span class="pace__dot" aria-hidden="true"></span><span data-pace="note-text"><?= e($pace['note']) ?></span>
    </p>
</div>
