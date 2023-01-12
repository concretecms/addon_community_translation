<?php

declare(strict_types=1);

use Concrete\Core\Filesystem\Element;
use Monolog\Logger;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Page\Page $c
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var bool $notify
 * @var array $notifyTo
 * @var array $handlers
 * @var array $handlerLevels
 */

$menu = new Element('dashboard/options/menu', $c, 'community_translation');
$menu->render();
?>

<form method="POST" action="<?= h($view->action('submit')) ?>" onsubmit="if (this.already) return false; this.already = true" id="comtra-app" v-cloak v-bind:disabled="busy">

    <?php $token->output('ct-options-save-cli') ?>

    <div class="mb-3">
        <div class="form-check">
            <?= $form->checkbox('notify', '1', $notify, ['v-bind:disabled' => 'busy']) ?>
            <?= $form->label('notify', t('Send notifications when running CLI commands non-interactively')) ?>
        </div>
    </div>

    <fieldset>
        <legend><?= t('Notification channels') ?></legend>
        <input type="hidden" name="numNotifyTo" v-bind:value="notifyTo.length" />
        <div>
            <button class="btn btn-success" v-for="h in HANDLERS" v-on:click.prevent="addNotifyTo(h.handle)" style="margin-right: 5px"><?= t('New handler: %s', '{{ h.name }}') ?></button>
        </div>
        <div v-for="(nto, index) in notifyTo" class="card mt-3">
            <div class="card-header">
                <input type="hidden" v-bind:name="`notifyTo${index}_handler`" v-bind:value="nto.handler" />
                <h5><?= t('Notify via %s', '{{ getHandlerName(nto.handler) }}')?></h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check">
                        <?= $form->checkbox('', '', false, ['v-bind:id' => '`notifyTo${index}_enabled`', 'v-bind:name' => '`notifyTo${index}_enabled`', 'v-bind:value' => 'true', 'v-model' => 'nto.enabled', 'v-bind:disabled' => 'busy']) ?>
                        <?= $form->label('', t('Enabled'), ['v-bind:for' => '`notifyTo${index}_enabled`']) ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= $form->label('', t('Minimum level'), ['v-bind:for' => '`notifyTo${index}_level`']) ?>
                    <select class="form-select" v-bind:id="`notifyTo${index}_level`" v-bind:name="`notifyTo${index}_level`" v-model="nto.level" required="required" v-bind:disabled="busy">
                        <option v-for="HL in HANDLER_LEVELS" v-bind:value="HL.level">{{ HL.name }}</option>
                    </select>
                </div>
                <div class="mb-3">
                    <?= $form->label('', t('Description'), ['v-bind:for' => '`notifyTo${index}_description`']) ?>
                    <?= $form->text('', '', ['v-bind:id' => '`notifyTo${index}_description`', 'v-bind:name' => '`notifyTo${index}_description`', 'v-model.trim' => 'nto.description', 'v-bind:disabled' => 'busy']) ?>
                </div>
                <div v-if="false"></div>
                <div v-else-if="nto.handler === 'slack'">
                    <div class="mb-3">
                        <?= $form->label('', t('API Token'), ['v-bind:for' => '`notifyTo${index}_apiToken`']) ?>
                        <?= $form->text('', '', ['v-bind:id' => '`notifyTo${index}_apiToken`', 'v-bind:name' => '`notifyTo${index}_apiToken`', 'v-model.trim' => 'nto.apiToken', 'required' => 'required', 'v-bind:disabled' => 'busy']) ?>
                    </div>
                    <div class="mb-3">
                        <?= $form->label('', t('Channel'), ['v-bind:for' => '`notifyTo${index}_channel`']) ?>
                        <?= $form->text('', '', ['v-bind:id' => '`notifyTo${index}_channel`', 'v-bind:name' => '`notifyTo${index}_channel`', 'v-model.trim' => 'nto.channel', 'required' => 'required', 'v-bind:disabled' => 'busy']) ?>
                    </div>
                </div>
                <div v-else-if="nto.handler === 'telegram'">
                    <div class="mb-3">
                        <?= $form->label('', t('Bot Token'), ['v-bind:for' => '`notifyTo${index}_botToken`']) ?>
                        <?= $form->text('', '', ['v-bind:id' => '`notifyTo${index}_botToken`', 'v-bind:name' => '`notifyTo${index}_botToken`', 'v-model.trim' => 'nto.botToken', 'required' => 'required', 'v-bind:disabled' => 'busy']) ?>
                    </div>
                    <div class="mb-3">
                        <?= $form->label('', t('Chat ID'), ['v-bind:for' => '`notifyTo${index}_chatID`']) ?>
                        <?= $form->text('', '', ['v-bind:id' => '`notifyTo${index}_chatID`', 'v-bind:name' => '`notifyTo${index}_chatID`', 'v-model.trim' => 'nto.chatID', 'required' => 'required', 'v-bind:disabled' => 'busy']) ?>
                    </div>
                </div>
                <div v-else class="alert alert-danger">
                    <?= t('Unrecognized handler: %s', '{{ nto.handler }}') ?>
                </div>
            </div>
            <div class="card-footer">
                <button v-on:click.prevent="testHandler(nto)" class="btn btn-sm btn-info" v-bind:disabled="busy"><?= t('Test Handler') ?></button>
                <button v-on:click.prevent="notifyTo.splice(index, 1)" class="btn btn-sm btn-danger" v-bind:disabled="busy"><?= t('Remove Handler') ?></button>
            </div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation'])) ?>" class="btn btn-secondary float-start" v-bind:disabled="busy"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary float-end btn ccm-input-submit" value="<?= t('Save') ?>" v-bind:disabled="busy" />
        </div>
    </div>

</form>
<script>
$(document).ready(function() {

new Vue({
    el: '#comtra-app',
    data: function() {
        return {
            busy: false,
            HANDLERS: <?= json_encode($handlers) ?>,
            HANDLER_LEVELS: <?= json_encode($handlerLevels) ?>,
            notifyTo: <?= json_encode($notifyTo) ?>,
        };
    },
    methods: {
        addNotifyTo: function(handle) {
            const data = {
                handler: handle,
                enabled: true,
                level: <?= json_encode(Logger::ERROR) ?>,
                description: '',
            };
            switch (handle) {
                case 'slack':
                    data.apiToken = '';
                    data.channel = '';
                    break;
                case 'telegram':
                    data.botToken = '';
                    data.chatID = '';
                    break;
                default:
                    window.alert(<?= json_encode('Unrecognized handler: %s') ?>.replace(/\%s/g, handle));
                    return;
            }
            this.notifyTo.push(data);
        },
        getHandlerName: function(handle) {
            for (const h of this.HANDLERS) {
                if (h.handle === handle) {
                    return h.name;
                }
            }
            return `?${handle}?`;
        },
        testHandler: function(handler) {
            this.busy = true;
            $.ajax({
                data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-options-cli-testhandler')]) ?>, handler),
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('test_handler')) ?>
            })
            .always(() => {
                this.busy = false;
            })
            .done(function(data) {
                if (data === true) {
                    ConcreteAlert.dialog(<?= json_encode(t('Message sent'))?>, <?= json_encode(t('The message should have been sent: please check if you received it.')) ?>);
                } else {
                    ConcreteAlert.dialog(ccmi18n.error, <?= json_encode('Unexpected server response') ?>);
                }
            })
            .fail(function(xhr, status, error) {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
    },
});

});
</script>