<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

$form = $app->make('helper/form');
/* @var Concrete\Core\Form\Service\Form $form */

/* @var Concrete\Package\CommunityTranslation\Block\SearchPackages\Controller $controller */

/* @var int $resultsPerPage */
/* @var string $allowedDownloadFor */
/* @var array $allowedDownloadForList */
/* @var string[] $allowedDownloadFormats */
/* @var CommunityTranslation\TranslationsConverter\ConverterInterface[] $downloadFormats */

?>

<fieldset>

    <legend><?= t('Options') ?></legend>

    <div class="form-group">
        <?= $form->label('resultsPerPage', t('Number of results per page')) ?>
        <?= $form->number('resultsPerPage', $resultsPerPage, ['min' => '1', 'required' => 'required']) ?>
    </div>

    <div class="form-group">
        <?= $form->label('allowedDownloadFor', t('Allow downloading translations for')) ?>
        <?= $form->select('allowedDownloadFor', $allowedDownloadForList, $allowedDownloadFor, ['required' => 'required']) ?>
    </div>

    <div class="form-group"<?= ($allowedDownloadFor === $controller::ALLOWDOWNLOADFOR_NOBODY) ? ' style="display: none"' : '' ?>>
        <?= $form->label('allowedDownloadFormats', t('Allowed download formats')) ?>
        <?php
        foreach ($downloadFormats as $df) {
            ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('allowedDownloadFormats[]', $df->getHandle(), in_array($df->getHandle(), $allowedDownloadFormats)) ?>
                    <?= h($df->getName()) ?>
                </label>
            </div>
            <?php
        }
        ?>
    </div>

</fieldset>
<script>
$(document).ready(function() {
    $('#allowedDownloadFor').on('change', function() {
        var askFormats = $('#allowedDownloadFor').val() !== <?= json_encode($controller::ALLOWDOWNLOADFOR_NOBODY) ?>;
        $('input[name="allowedDownloadFormats[]"]:first').closest('div.form-group')[askFormats ? 'show' : 'hide']('fast');
    });
});
</script>