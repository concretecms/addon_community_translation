<?php
$id = preg_replace('/\W/', '_', $view->field('operation'));
?>
<div class="checkbox">
    <?php
    if ($value !== '') {
        ?>
        <code><?= h($value) ?></code>
        <input type="hidden" name="<?= $view->field('current-token-hash') ?>" value="<?= h($valueHash); ?>" />
        <input type="hidden" name="<?= $view->field('current-token') ?>" value="<?= h($value); ?>" />
        <?php
    }
    ?>
    <div class="btn-group" id="<?= $id ?>">
        <button type="button" data-value="keep" class="btn btn-sm btn-primary"><?= t('Keep API Token') ?></button>
        <button type="button" data-value="generate" class="btn btn-sm btn-default"><?= t('Generate new API Token') ?></button>
        <button type="button" data-value="remove" class="btn btn-sm btn-default"><?= t('Remove API Token') ?></button>
        <input type='hidden' name="<?= $view->field('operation') ?>" value="keep" />
    </div>
</div>
<script>
$(document).ready(function() {
    var $div = $(<?= json_encode('#' . $id) ?>);
    $div.find('button').on('click', function() {
        var $me = $(this);
        $div.find('input').val($me.data('value'));
        $div.find('button').removeClass('btn-primary').removeClass('btn-default');
        $me.toggleClass('btn-primary btn-default');
    });
});
</script>
