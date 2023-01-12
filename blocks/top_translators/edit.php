<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\TopTranslators\Controller $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var int|null $numTranslators
 * @var string[] $localeOptions
 * @var string $limitToLocale
 * @var bool $allTranslations
 */

?>
<div class="mb-3">
    <?= $form->label('numTranslators', t('Maximum number of translators to display')) ?>
    <?= $form->number('numTranslators', (string) $numTranslators, ['min' => 1, 'step' => 1, 'placeholder' => t('Empty: unlimited')]) ?>
</div>

<div class="mb-3">
    <?= $form->label('limitToLocale', t('Translation team')) ?>
    <?= $form->select('limitToLocale', $localeOptions, $limitToLocale) ?>
</div>
<div class="mb-0">
    <?= $form->label('allTranslations', t('Translations count')) ?>

    <div class="form-check">
        <?= $form->radio('allTranslations', 0, $allTranslations ? 1 : 0, ['id' => 'allTranslations0', 'class' => 'form-check-input']) ?>
        <?= $form->label('allTranslations0', t('count only the approved translations'), ['class' => 'form-check-label']) ?>
    </div>

    <div class="form-check">
        <?= $form->radio('allTranslations', 1, $allTranslations ? 1 : 0, ['id' => 'allTranslations1', 'class' => 'form-check-input']) ?>
        <?= $form->label('allTranslations1', t('count any translation'), ['class' => 'form-check-label']) ?>
    </div>
</div>
