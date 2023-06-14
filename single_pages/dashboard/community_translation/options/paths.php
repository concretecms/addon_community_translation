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
 * @var string $onlineTranslationPath
 * @var string $apiBasePath
 * @var string $tempDir
 * @var string $defaultTempDir
 */

$menu = new Element('dashboard/options/menu', $c, 'community_translation');
$menu->render();
?>

<form method="POST" action="<?= h($view->action('submit')) ?>" onsubmit="if (this.already) return false; this.already = true">

    <?php $token->output('ct-options-save-paths') ?>

    <div class="mb-3">
        <?= $form->label('onlineTranslationPath', t('Online Translation URI')) ?>
        <?= $form->text('onlineTranslationPath', $onlineTranslationPath, ['required' => 'required']) ?>
    </div>
    <div class="mb-3">
        <?= $form->label('apiBasePath', t('API Base Path')) ?>
        <?= $form->text('apiBasePath', $apiBasePath, ['required' => 'required']) ?>
    </div>
    <div class="mb-3">
        <?= $form->label('tempDir', t('Temporary directory')) ?>
        <?= $form->text('tempDir', $tempDir) ?>
        <?php
        if ($defaultTempDir !== '') {
            ?>
            <div class="small text-muted">
                <?= t(t('Default temporary directory: %s', '<code>' . h($defaultTempDir) . '</code>')) ?>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-start"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-end btn ccm-input-submit" value="<?= t('Save') ?>">
        </div>
    </div>

</form>
