<?php

defined('C5_EXECUTE') or die('Access denied.');

/* @var Concrete\Core\Form\Service\Form $form */

/* @var int|null $numTranslators */
/* @var array $localeOptions */
/* @var string $limitToLocale */
/* @var bool $allTranslations */

?>
<div class="form-group">
    <?= $form->label('numTranslators', t('Maximum number of translators to display')) ?>
    <?= $form->number('numTranslators', $numTranslators, ['min' => 1, 'step' => 1, 'placeholder' => t('Empty: unlimited')]) ?>
</div>

<div class="form-group">
    <?= $form->label('limitToLocale', t('Translation team')) ?>
    <?= $form->select('limitToLocale', $localeOptions, $limitToLocale) ?>
</div>
<div class="form-group">
    <?= $form->label('allTranslations', t('Translations count')) ?>
    <div class="radio">
        <label>
            <?= $form->radio('allTranslations', 0, $allTranslations ? 1 : 0) ?>
            <?= t('count only the approved translations') ?>
        </label>
    </div>
    <div class="radio">
        <label>
            <?= $form->radio('allTranslations', 1, $allTranslations ? 1 : 0) ?>
            <?= t('count any translation') ?>
        </label>
    </div>
</div>
