<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

$form = $app->make('helper/form');
/* @var Concrete\Core\Form\Service\Form $form */
/* @var CommunityTranslation\Service\RateLimit $rateLimitHelper */

/* @var int|null $rateLimit_maxRequests */
/* @var int $rateLimit_timeWindow */
/* @var int|null $maxFileSizeValue */
/* @var string $maxFileSizeUnit */
/* @var int|null $maxLocalesCount */
/* @var int|null $maxStringsCount */
/* @var string $statsFromPackage */
?>

<fieldset>

    <legend><?= t('Options') ?></legend>

    <div class="form-group">
        <?= $form->label('', t('Rate limit')) ?>
        <?= $rateLimitHelper->getWidgetHtml('rateLimit', $rateLimit_maxRequests, $rateLimit_timeWindow) ?>
    </div>

    <div class="form-group">
        <?= $form->label('maxFileSizeValue', t('Max size of uploaded files')) ?>
        <div class="input-group" style="white-space: nowrap">
            <?= $form->number('maxFileSizeValue', $maxFileSizeValue, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
            <span class="input-group-addon">
                <?= $form->select(
                    'maxFileSizeUnit',
                    [
                        'b' => 'b',
                        'KB' => 'KB',
                        'MB' => 'MB',
                        'GB' => 'GB',
                    ],
                    $maxFileSizeUnit,
                    ['style' => 'width: 80px']
                ) ?>
            </span>
        </div>
        <?php
        if ($postLimit !== '') {
            ?><p class="text-muted"><?= t('Current limit imposed by PHP: %s', $postLimit) ?></p><?php
        }
        ?>
    </div>

    <div class="form-group">
        <?= $form->label('maxLocalesCount', t('Max number of locales')) ?>
        <?= $form->number('maxLocalesCount', $maxLocalesCount, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
    </div>

    <div class="form-group">
        <?= $form->label('maxStringsCount', t('Max number of strings')) ?>
        <?= $form->number('maxStringsCount', $maxStringsCount, ['min' => 1, 'placeholder' => t('Empty for no limit')]) ?>
    </div>

    <div class="form-group">
        <?= $form->label('statsFromPackage', t('Calculate "translated" languages inspecting package')) ?>
        <?= $form->text('statsFromPackage', $statsFromPackage, ['placeholder' => t('Empty to use every package')]) ?>
    </div>
</fieldset>
