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
    <div class="input-group input-group-sm">
        <?php
        if ($value !== '') {
            ?>
            <input type="text" class="form-control" readonly="readonly" id="<?= $id ?>display"  value="<?= h($value) ?>" />
            <input type="hidden" name="<?= h($view->field('current-token-hash')) ?>" value="<?= h($valueHash) ?>" />
            <input type="hidden" name="<?= h($view->field('current-token')) ?>" value="<?= h($value) ?>" />
            <button type="button" class="btn btn-outline-primary" id="<?= $id ?>copy">
                <?= t('Copy')?>
            </button>
            <?php
        }
        ?>
        <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>keep" value="keep" autocomplete="off" checked="checked" />
        <label class="btn btn-outline-primary" for="<?= $id ?>keep"><?= $value === '' ? t('Keep no API Token') : t('Keep') ?></label>
        <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>generate" value="generate" autocomplete="off" />
        <label class="btn btn-outline-primary" for="<?= $id ?>generate"><?= $value === '' ? t('Generate') : t('Generate') ?></label>
        <?php
        if ($value !== '') {
            ?>
            <input type="radio" class="btn-check" name="<?= h($view->field('operation')) ?>" id="<?= $id ?>remove" value="remove" autocomplete="off" />
            <label class="btn btn-outline-primary" for="<?= $id ?>remove"><?= t('Remove') ?></label>
            <?php
        }
        ?>
    </div>
</div>
<?php
if ($value !== '') {
    ?>
    <script>
    $(document).ready(function() {
        $('input[name="<?= $view->field('operation') ?>"]')
            .on('change', function() {
                $(<?= json_encode("#{$id}display,#{$id}copy") ?>).prop('disabled', $(<?= json_encode("#{$id}keep") ?>).is(':checked') ? false : true);
            })
            .first().trigger('change')
        ;
        $(<?= json_encode("#{$id}copy") ?>).on('click', function(e) {
            if (window.navigator && window.navigator.clipboard) {
                window.navigator.clipboard.writeText(<?= json_encode($value) ?>);
                return;
            }
            var $i = $('<input type="text" />').val(<?= json_encode($value) ?>);
            $(document.body).append($i);
            $i.focus();
            $i.select()
            document.execCommand('copy');
            $i.remove();
        });
    });
    </script>
    <?php
}
