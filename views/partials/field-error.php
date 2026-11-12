<?php
/** @var array $errors @var string $field */
if (!empty($errors[$field])): ?>
    <p class="field__error" id="<?= e($field) ?>-error"><?= e($errors[$field]) ?></p>
<?php endif;
