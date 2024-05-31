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

<form method="POST" action="<?= h($view->action('submit')) ?>" id="comtra-form" v-on:submit="return askSubmit()" v-cloak>

    <?php $token->output('ct-options-save-paths') ?>

    <div class="mb-3">
        <?= $form->label('onlineTranslationPath', t('Online Translation URI')) ?>
        <?= $form->text('onlineTranslationPath', $onlineTranslationPath, ['required' => 'required', 'v-bind:readonly' => 'busy']) ?>
    </div>
    <div class="mb-3">
        <?= $form->label('apiBasePath', t('API Base Path')) ?>
        <?= $form->text('apiBasePath', $apiBasePath, ['required' => 'required', 'v-bind:readonly' => 'busy']) ?>
    </div>
    <div class="mb-3">
        <?= $form->label('tempDir', t('Temporary directory')) ?>
        <?= $form->text('tempDir', $tempDir, ['v-bind:readonly' => 'busy']) ?>
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
    <div class="mb-3">
        <?= $form->label('', t('Clear')) ?>
        <div>
            <div class="btn-group">
                <button class="btn btn-secondary" v-bind:disabled="busy" v-on:click.prevent="clearTranslationsCache"><?= t('Translations Cache') ?></button>
            </div>
        </div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-start"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-end btn ccm-input-submit" value="<?= t('Save') ?>" v-bind:disabled="busy" />
        </div>
    </div>

</form>
<script>(function() {

function initialize()
{
    new Vue({
        el: '#comtra-form',
        data() {
            return {
                busy: false,
            };
        },
        methods: {
            clearTranslationsCache() {
                if (this.busy) {
                    return;
                }
                this.busy = true;
                $.ajax({
                    method: 'POST',
                    url: <?= json_encode((string) $view->action('clearTranslationsCache')) ?>,
                    data: {
                        <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('ct-clear-trca')) ?>,
                    },
                    dataType: 'json',
                })
                .always(() => {
                    this.busy = false;
                })
                .done(() => {
                    ConcreteAlert.info({
                        plainTextMessage: true,
                        message: <?= json_encode(t('Cache cleared successfully.')) ?>,
                    });
                })
                .fail((xhr, status, error) => {
                    ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
                });
            },
            askSubmit() {
                if (this.busy) {
                    return false;
                }
                this.busy = true;
                return true;
            },
        },
    });
}

if (document.readyState === 'complete') {
    initialize();
} else {
    document.addEventListener("DOMContentLoaded", initialize);
}

})();</script>
