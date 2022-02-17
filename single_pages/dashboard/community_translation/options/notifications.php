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
 * @var string $defaultSenderAddress
 * @var string $senderAddress
 * @var string $defaultSenderName
 * @var string $senderName
 */

$menu = new Element('dashboard/options/menu', $c, 'community_translation');
$menu->render();
?>

<form method="POST" action="<?= h($view->action('submit')) ?>" onsubmit="if (this.already) return false; this.already = true">

    <?php $token->output('ct-options-save-notifications') ?>

    <p>
        <?= t('Here you can configure the sender of the notifications sent by CommunityTranslation') ?>
    </p>

	<div class="form-group">
        <?= $form->label('senderAddress', t('Sender email address'))?>
        <?= $form->email('senderAddress', $senderAddress) ?>
        <div class="small text-muted">
            <?= t("If not specified we'll use the default one: %s", '<code>' . h($defaultSenderAddress) . '</code>') ?>
        </div>
    </div>
	<div class="form-group">
        <?= $form->label('senderName', t('Sender name'))?>
        <?= $form->text('senderName', $senderName, ['placeholder' => t('Default sender name: %s', h($defaultSenderName))]) ?>
        <?php
        if ($defaultSenderName !== '') {
            ?>
            <div class="small text-muted">
                <?= t("If not specified we'll use the default one: %s", '<code>' . h($defaultSenderName) . '</code>') ?>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-left"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-right btn ccm-input-submit" value="<?= t('Save') ?>">
        </div>
    </div>

</form>
