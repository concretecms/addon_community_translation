<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\SearchPackages\Controller $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $resultsPerPage
 * @var string $ALLOWDOWNLOADFOR_NOBODY
 * @var string $allowedDownloadFor
 * @var array $allowedDownloadForList
 * @var string[] $allowedDownloadFormats
 * @var CommunityTranslation\TranslationsConverter\ConverterInterface[] $downloadFormats
 */

?>

<div class="form-group">
    <?= $form->label('resultsPerPage', t('Number of results per page')) ?>
    <?= $form->number('resultsPerPage', $resultsPerPage, ['min' => '1', 'required' => 'required']) ?>
</div>

<div class="form-group">
    <?= $form->label('allowedDownloadFor', t('Allow downloading translations for')) ?>
    <?= $form->select('allowedDownloadFor', $allowedDownloadForList, $allowedDownloadFor, ['required' => 'required']) ?>
</div>

<div class="form-group mb-0"<?= ($allowedDownloadFor === $ALLOWDOWNLOADFOR_NOBODY) ? ' style="display: none"' : '' ?>>
    <?= $form->label('allowedDownloadFormats', t('Allowed download formats')) ?>
    <?php
    foreach ($downloadFormats as $df) {
        ?>
        <div class="form-check">
            <?= $form->checkbox('allowedDownloadFormats[]', $df->getHandle(), in_array($df->getHandle(), $allowedDownloadFormats, true), ['class' => 'form-check-control', 'id' => "allowedDownloadFormat_{$df->getHandle()}"]) ?>
            <?= $form->label("allowedDownloadFormat_{$df->getHandle()}", $df->getName(), ['class' => 'form-check-label']) ?>
        </div>
        <?php
    }
    ?>
</div>

<script>$(document).ready(function() {

$('#allowedDownloadFor').on('change', function() {
    var askFormats = $('#allowedDownloadFor').val() !== <?= json_encode($ALLOWDOWNLOADFOR_NOBODY) ?>;
    $('input[name="allowedDownloadFormats[]"]:first').closest('div.form-group')[askFormats ? 'show' : 'hide']('fast');
});

});</script>
