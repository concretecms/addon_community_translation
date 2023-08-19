<?php

declare(strict_types=1);

use Concrete\Core\Localization\Localization;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $aliases
 */
?>
<div id="app" v-cloak>
    <div v-if="aliases.length === 0" class="alert alert-info">
        <?= t('No package alias has been defined yet.') ?>
    </div>
    <div v-else>
        <table class="table table-hover table-striped">
            <colgroup>
                <col width="1" />
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th><?= t('Package') ?></th>
                    <th><?= t('Alias') ?></th>
                    <th><?= t('Created On') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="alias in aliases" v-bind:key="alias.handle" v-bind:class="alias.hightlight ? 'table-success' : ''">
                    <td>
                        <button v-bind:disabled="busy !== 0" class="btn btn-sm btn-danger" title="<?= t('Delete') ?>" v-on:click.prevent="askDeleteAlias(alias)">&times;</button>
                    </td>
                    <td>
                        {{ alias.package.name }}<br />
                        <code>{{ alias.package.handle }}</code>
                    </td>
                    <td>
                        <code>{{ alias.handle }}</code>
                    </td>
                    <td>
                        {{ formatDateTime(alias.createdOn) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-none">
        <div id="dialog-add-alias" title="<?= t('Add Alias') ?>">
            <div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Package') ?></label>
                    <concrete-ajax-select
                        data-source-url="<?= h((string) $view->action('getPackages')) ?>"
                        access-token="<?= h($token->generate('ct-pa-gp')) ?>"
                        v-bind:form-data="<?= h(json_encode(['__ccm_consider_request_as_xhr' => '1'])) ?>"
                        v-bind:value="newAlias.packageID"
                        v-on:change="newAlias.packageID = parseInt($event) || null"
                    ></concrete-ajax-select>
                </div>
                <div>
                    <label for="newalias-handle" class="form-label"><?= t('New handle') ?></label>
                    <input type="text" class="form-control code" v-model.trim="newAlias.handle" />
                </div>
            </div>
            <div class="dialog-buttons">
                <button v-bind:disabled="busy !== 0" class="btn btn-default pull-left" v-on:click.prevent="if (busy === 0) jQuery.fn.dialog.closeTop()"><?= t('Cancel') ?></button>
                <button v-bind:disabled="busy !== 0" class="btn btn-danger pull-right" v-on:click.prevent="doAddAlias"><?= t('Save') ?></button>
            </div>
        </div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <button v-bind:disabled="busy !== 0" class="btn btn-primary" v-on:click.prevent="showAddAliasDialog"><?= t('Add Alias') ?></button>
        </div>
    </div>

</div>
<script>$(document).ready(function() { Concrete.Vue.activateContext('cms', function (Vue, cmsConfig) {

'use strict';

const DATETIME_FORMATTER = new Intl.DateTimeFormat(<?= json_encode(Localization::activeLocale()) ?>.replaceAll('_', '-'), {dateStyle: 'long', timeStyle: 'short'});

new Vue({
    el: '#app',
    components: cmsConfig.components,
    data() {
        return {
            busy: 0,
            aliases: <?= json_encode($aliases) ?>.map((alias) => this.unserializeAlias(alias)),
            newAlias: {
                packageID: null,
                handle: '',
            },
        };
    },
    beforeMount() {
        this.sortAliases();
    },
    methods: {
        formatDateTime(date) {
            return DATETIME_FORMATTER.format(date);
        },
        unserializeAlias(alias) {
            alias.highlight = false;
            const date = new Date();
            date.setTime(alias.createdOn * 1000);
            alias.createdOn = date;
            return alias;
        },
        sortAliases() {
            const collator = new Intl.Collator(<?= json_encode(Localization::activeLanguage()) ?>, {sensitivity: 'base'});
            this.aliases.sort((a, b) => {
                return collator.compare(a.package.name, b.package.name) || collator.compare(a.package.handle, b.package.handle) || collator.compare(a.handle, b.handle);
            });
        },
        showAddAliasDialog() {
            if (this.busy !== 0) {
                return;
            }
            $('#dialog-add-alias').dialog({
                modal: true,
                width: 500,
                beforeClose: () => {
                    return this.busy === 0;
                },
            });
        },
        doAddAlias() {
            if (this.busy !== 0) {
                return;
            }
            if (!this.newAlias.packageID) {
                ConcreteAlert.error({message: <?= json_encode(t('Please select a package')) ?>});
                return;
            }
            if (this.newAlias.handle === '') {
                ConcreteAlert.error({message: <?= json_encode(t('Please specify the handle')) ?>});
                return;
            }
            this.busy++;
            $.ajax({
                data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-pa-add')]) ?>, {
                    package: this.newAlias.packageID,
                    handle: this.newAlias.handle,
                }),
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('createAlias')) ?>
            })
            .done((data) => {
                this.busy--;
                if (!data) {
                    ConcreteAlert.dialog(ccmi18n.error, <?= json_encode(t('Unexpected server response')) ?>);
                    return;
                }
                $('#dialog-add-alias').dialog('close');
                this.newAlias.handle = '';
                const alias = this.unserializeAlias(data);
                alias.highlight = true;
                setTimeout(() => alias.highlight = false, 1000);
                this.aliases.push(alias);
                this.sortAliases();
            })
            .fail((xhr, status, error) => {
                this.busy--;
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
            
        },
        askDeleteAlias(alias) {
            if (this.busy !== 0) {
                return;
            }
            ConcreteAlert.confirm(
                <?= json_encode(t('Are you sure you want to delete the alias with handle "%s"?')) ?>.replace('%s', alias.handle),
                () => {
                    $.fn.dialog.closeTop();
                    this.deleteAlias(alias);
                },
                'btn-danger',
                <?= json_encode(t('Delete')) ?>
            );
        },
        deleteAlias(alias) {
            this.busy++;
            $.ajax({
                data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-pa-del')]) ?>, {
                    handle: alias.handle,
                }),
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('deleteAlias')) ?>
            })
            .always(() => {
                this.busy--;
            })
            .done((data) => {
                if (data !== true) {
                    ConcreteAlert.dialog(ccmi18n.error, <?= json_encode(t('Unexpected server response')) ?>);
                    return;
                }
                this.aliases.splice(this.aliases.indexOf(alias), 1);
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
    },
});

}); });</script>
