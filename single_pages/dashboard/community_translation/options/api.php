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
 * @var string $accessDenylistUrl
 * @var string $rateLimitDenylistUrl
 * @var string $accessControlAllowOrigin
 * @var array $accessChecks
 */

$menu = new Element('dashboard/options/menu', $c, 'community_translation');
$menu->render();
?>

<form method="POST" action="<?= h($view->action('submit')) ?>" onsubmit="if (this.already) return false; this.already = true">

    <?php $token->output('ct-options-save-api') ?>

    <div class="mb-3">
        <?= $form->label('', t('Block API access by invalid user token')) ?><br />
        <a href="<?= h($accessDenylistUrl) ?>" target="_blank" class="btn btn-info"><?= t('Configure') ?></a>
    </div>
    <div class="mb-3">
        <?= $form->label('', t('Limit API access rate')) ?><br />
        <a href="<?= h($rateLimitDenylistUrl) ?>" target="_blank" class="btn btn-info"><?= t('Configure') ?></a>
    </div>
    <div class="mb-3">
        <?= $form->label('accessControlAllowOrigin', tc(/*i18n: %s is a header name of an HTTP response*/'ResponseHeader', '%s header', 'Access-Control-Allow-Origin')) ?><br />
        <?= $form->text('accessControlAllowOrigin', $accessControlAllowOrigin, ['required' => 'required', 'style' => 'font-family: monospace']) ?>
        <div class="small text-muted">
        	<?= t(/*i18n: %s is a header name of an HTTP response*/'Set the content of the %s header added to the API request responses', '<code>Access-Control-Allow-Origin</code>') ?>
		</div>
    </div>
    <fieldset>
        <legend><?= t('Authentication') ?></legend>
        <?php
        foreach ($accessChecks as $key => $info) {
            ?>
            <div class="mb-3">
                <?= $form->label("apiAccess-{$key}", h($info['label'])) ?>
                <?= $form->select("apiAccess-{$key}", $info['values'], $info['value'], ['required' => 'required']) ?>
            </div>
            <?php
        }
        ?>
    </fieldset>


    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-start"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-end btn ccm-input-submit" value="<?= t('Save') ?>">
        </div>
    </div>

</form>
