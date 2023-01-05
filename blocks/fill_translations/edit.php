<?php

declare(strict_types=1);

use Punic\Unit;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\FillTranslations\Controller $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $rateLimitControlUrl
 * @var string $statsFromPackage
 * @var string $postLimit
 * @var int|null $maxFileSizeValue
 * @var string $maxFileSizeUnit
 * @var int|null $maxLocalesCount
 * @var int|null $maxStringsCount
 */

$maxFileSizeUnits = [
    'b' => Unit::getName('digital/byte', 'short'),
    'KB' => Unit::getName('digital/kilobyte', 'narrow'),
    'MB' => Unit::getName('digital/megabyte', 'narrow'),
    'GB' => Unit::getName('digital/gigabyte', 'narrow'),
];
?>

<div class="mb-3">
    <?= $form->label('', t('Rate limit')) ?>
    <div class="alert alert-info">
        <a href="<?= h($rateLimitControlUrl) ?>" target="_blank"><?= t('Configurable here') ?> &#x2197;</a>
    </div>
</div>

<div class="mb-3">
    <?= $form->label('maxFileSizeValue', t('Max size of uploaded files')) ?>
    <div class="input-group">
        <?= $form->number('maxFileSizeValue', $maxFileSizeValue, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
        <?= $form->select('maxFileSizeUnit', $maxFileSizeUnits, $maxFileSizeUnit, ['class' => 'form-control']) ?>
    </div>
    <?php
    if ($postLimit !== '') {
        ?><p class="text-muted"><?= t('Current limit imposed by PHP: %s', $postLimit) ?></p><?php
    }
    ?>
</div>

<div class="mb-3">
    <?= $form->label('maxLocalesCount', t('Max number of locales')) ?>
    <?= $form->number('maxLocalesCount', $maxLocalesCount, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
</div>

<div class="mb-3">
    <?= $form->label('maxStringsCount', t('Max number of strings')) ?>
    <?= $form->number('maxStringsCount', $maxStringsCount, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
</div>

<div>
    <?= $form->label('statsFromPackage', t('Calculate "translated" languages inspecting package')) ?>
    <?= $form->text('statsFromPackage', $statsFromPackage, ['placeholder' => t('Empty to use every package')]) ?>
</div>
