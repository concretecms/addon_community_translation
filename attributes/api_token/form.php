<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\CommunityTranslation\Attribute\ApiToken\Controller $controller
 * @var Concrete\Core\Attribute\View $this
 * @var Concrete\Core\Attribute\View $view
 * @var int $akID
 * @var string $value
 * @var string $valueHash
 */

$id = preg_replace('/\W+/', '_', $view->field('operation'));

?>
<div class="mb-3">
    <?php
    if ($value !== '') {
        ?>
        <code><?= h($value) ?></code>
        <input type="hidden" name="<?= h($view->field('current-token-hash')) ?>" value="<?= h($valueHash) ?>" />
        <input type="hidden" name="<?= h($view->field('current-token')) ?>" value="<?= h($value) ?>" />
        <?php
    }
    ?>
    <div class="btn-group">
        <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>keep" value="keep" autocomplete="off" checked="checked" />
        <label class="btn btn-outline-primary" for="<?= $id ?>keep"><?= $value === '' ? t('Keep no API Token') : t('Keep API Token') ?></label>

        <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>generate" value="generate" autocomplete="off" />
        <label class="btn btn-outline-primary" for="<?= $id ?>generate"><?= $value === '' ? t('Generate API Token') : t('Generate new API Token') ?></label>

        <?php
        if ($value !== '') {
            ?>
            <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>remove" value="remove" autocomplete="off" />
            <label class="btn btn-outline-primary" for="<?= $id ?>remove"><?= t('Remove API Token') ?></label>
            <?php
        }
        ?>
    </div>
</div>
