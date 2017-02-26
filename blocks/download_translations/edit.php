<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

/* @var Concrete\Core\Form\Service\Form $form */
/* @var array[] $allowedUsersOptions */
/* @var string $allowedUsers */
/* @var string $packageHandle */
/* @var bool $packageHandleFixed */
/* @var string $packageVersion */
/* @var bool $packageVersionFixed */
/* @var string[] $allowedFormats */
/* @var array $allowedFormatList */
?>

<fieldset>

    <legend><?php echo t('Options'); ?></legend>

    <div class="form-group">
        <?= $form->label('allowedUsers', t('Allowed users')) ?>
        <?= $form->select('allowedUsers', $allowedUsersOptions, $allowedUsers, ['required' => 'required']) ?>
    </div>

    <div class="form-group">
        <?= $form->label('packageHandle', t('Package handle')) ?>
        <?= $form->text('packageHandle', $packageHandle, ['placeholder' => t('Leave empty to allow users to select a package')]) ?>
    </div>

    <div class="form-group only-with-packageHandle">
        <label class="control-label"><?=t('Users may choose another package?') ?></label>
        <div class="radio">
            <label>
                <?= $form->radio('packageHandleFixed', '1', $packageHandleFixed) ?>
                <span><?= t('No, they can download only the above package') ?></span>
            </label>
        </div>
        <div class="radio">
            <label>
                <?= $form->radio('packageHandleFixed', '0', $packageHandleFixed) ?>
                <span><?= t('Yes, the above package is only a suggestion') ?></span>
            </label>
        </div>
    </div>

    <div class="form-group only-with-packageHandle">
        <?= $form->label('packageVersion', t('Package version')) ?>
        <?= $form->text('packageVersion', $packageVersion, ['placeholder' => t('Leave empty to allow users to select a version')]) ?>
    </div>

    <div class="form-group only-with-packageVersion">
        <label class="control-label"><?=t('Users may choose another version?') ?></label>
        <div class="radio">
            <label>
                <?= $form->radio('packageVersionFixed', '1', $packageVersionFixed) ?>
                <span><?= t('No, they can download only the above version') ?></span>
            </label>
        </div>
        <div class="radio">
            <label>
                <?= $form->radio('packageVersionFixed', '0', $packageVersionFixed) ?>
                <span><?= t('Yes, the above version is only a suggestion') ?></span>
            </label>
        </div>
    </div>

    <div class="form-group">
        <label for="allowedFormats" class="control-label"><?= t('Allowed download formats') ?></label>
        <?php
        foreach ($allowedFormatList as $formatID => $formatName) {
            ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('allowedFormats[]', $formatID, in_array($formatID, $allowedFormats)) ?>
                    <?= h($formatName) ?>
                </label>
            </div>
            <?php
        }
        ?>
    </div>

</fieldset>
<script>
$(document).ready(function() {
    function update(immediate) {
        var ph = $('#packageHandle').val().length !== 0,
            pv = ph && $('#packageVersion').val().length !== 0 && $('input[name="packageHandleFixed"][value="1"]').is(':checked');
        $('.only-with-packageHandle')[ph ? 'show' : 'hide'](immediate ? null : 'fast');
        $('.only-with-packageVersion')[pv ? 'show' : 'hide'](immediate ? null : 'fast');
    }
    $('#packageHandle,input[name="packageHandleFixed"],#packageVersion').on('change click keydown keyup blur', function() {
        update();
    });
    update(true);
});
</script>