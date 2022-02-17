<?php

declare(strict_types=1);

use Concrete\Core\Filesystem\Element;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Page\Page $c
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var string $sourceLocale
 * @var int $translatedThreshold
 * @var array $parsers
 * @var string $defaultParser
 */

$menu = new Element('dashboard/options/menu', $c, 'community_translation');
$menu->render();
?>

<form method="POST" action="<?= h($view->action('submit')) ?>" onsubmit="if (this.already) return false; this.already = true">

    <?php $token->output('ct-options-save-translate') ?>

    <div class="form-group">
        <?= $form->label('sourceLocale', t('Source locale')) ?>
        <?= $form->text('sourceLocale', $sourceLocale, ['required' => 'required', 'pattern' => '[a-z]{2,3}(_([A-Z]{2}|[0-9]{3}))?']) ?>
    </div>
    <div class="form-group">
        <?= $form->label('translatedThreshold', t('Translation threshold')) ?>
        <div class="input-group">
            <?= $form->number('translatedThreshold', $translatedThreshold, ['min' => 0, 'max' => 100, 'required' => 'required']) ?>
            <div class="input-group-append">
                <span class="input-group-text">%</span>
            </div>
        </div>
        <div class="small text-muted">
            <?= t('Translations below this value will be considered as <i>not translated</i>') ?>
        </div>
    </div>
	<div class="form-group">
        <?= $form->label('parser', t('Strings Parser')) ?>
        <?= $form->select('parser', $parsers, $defaultParser, ['required' => 'required']) ?>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-left"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-right btn ccm-input-submit" value="<?= t('Save') ?>">
        </div>
    </div>

</form>
