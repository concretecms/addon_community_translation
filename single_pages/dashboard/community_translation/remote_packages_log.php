<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $remotePackages
 */
?>
<div id="app" v-cloak>
    <div v-if="remotePackages.length === 0" class="alert alert-info">
        <?= t('No remote package found in the database') ?>
    </div>
    <div v-else>
        <table class="table table-striped table-hover table-small">
            <colgroup>
                <col width="1" />
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th class="text-center"><?= t('Created on') ?></th>
                    <th class="text-center"><?= t('Package') ?></th>
                    <th class="text-center"><?= t('Approved') ?></th>
                    <th class="text-center"><?= t('Details') ?></th>
                    <th class="text-center"><?= t('Origin') ?></th>
                    <th class="text-center"><?= t('Processed on') ?></th>
                    <th class="text-center"><?= t('Errors') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="remotePackage in remotePackages">
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" v-bind:disabled="busy" v-on:click.prevent="refreshRemotePackage(remotePackage)" title="<?= t('Refresh') ?>"><i class="fas fa-sync" v-bind:class="busy && refreshingRemotePackage === remotePackage ? 'fa-spin' : ''"></i></button>
                        <button type="button" class="btn btn-sm btn-primary" v-bind:disabled="busy" v-on:click.prevent="importRemotePackage(remotePackage)" title="<?= t('Import') ?>"><i class="fas fa-cog" v-bind:class="busy && importingRemotePackage === remotePackage ? 'fa-spin' : ''"></i></button>
                    </td>
                    <td>{{ remotePackage.createdOn }}</td>
                    <td>
                        <span v-if="remotePackage.name !== ''">{{ remotePackage.name }}</span>
                        <span v-if="remotePackage.version !== ''">
                            <span class="badge bg-secondary text-small p-1"><?= t('v.')?></span>{{ remotePackage.version }}
                        </span>
                        <div v-if="remotePackage.handle !== ''"><code>{{ remotePackage.handle }}</code></div>
                    </td>
                    <td class="text-center">
                        <i class="fas fa-check text-success" v-if="remotePackage.approved"></i>
                        <i class="fas fa-times text-danger" v-else></i>
                    </td>
                    <td class="text-center">
                        <i v-if="remotePackage.url === ''"><?= t('n/a') ?></i>
                        <a v-bind:href="remotePackage.url" target="_blank"><i class="fas fa-eye"></i></a>
                    </td>
                    <td class="text-center">
                        <i v-if="remotePackage.archiveUrl === ''"><?= t('n/a') ?></i>
                        <a v-bind:href="remotePackage.archiveUrl" target="_blank"><i class="fas fa-eye"></i></a>
                    </td>
                    <td>
                        <i v-if="remotePackage.processedOn === null"><?= t('Never') ?></i>
                        <span v-else>{{ remotePackage.processedOn }}</span>
                    </td>
                    <td>
                        <i v-if="remotePackage.failCount === 0 &amp;&amp; remotePackage.lastError === ''"><?= tc('Errors', 'None') ?></i>
                        <div v-else>
                            <?= t('%s errors', '{{ remotePackage.failCount }}') ?><br />
                            <?= t('Last error: %s', '<span style="white-space: pre-wrap">{{ remotePackage.lastError }}</span>') ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="noMoreRemotePackages" class="alert alert-info mt-3">
            <?= t('No more remote package found in the database') ?>
        </div>
        <div class="text-center">
            <button type="button" class="btn btn-primary" v-on:click.prevent="loadNextPage" v-bind:disabled="busy"><?= t('Load more packages') ?></button>
        </div>
    </div>
</div>
<script>$(document).ready(function() {
'use strict';

new Vue({
    el: '#app',
    data: function() {
        return {
            busy: false,
            remotePackages: <?= json_encode($remotePackages) ?>,
            refreshingRemotePackage: null,
            importingRemotePackage: null,
            noMoreRemotePackages: <?= json_encode($remotePackages === []) ?>,
        };
    },
    methods: {
        refreshRemotePackage: function(remotePackage) {
            if (this.busy) {
                return;
            }
            this.refreshingRemotePackage = remotePackage;
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-repa-refresh1'))?>,
                    id: remotePackage.id,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('refresh_remote_package'))?>
            })
            .always(() => {
                this.busy = false;
                this.refreshingRemotePackage = null;
            })
            .done((data) => {
                for (const key in data) {
                    remotePackage[key] = data[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
        importRemotePackage: function(remotePackage) {
            if (this.busy) {
                return;
            }
            this.importingRemotePackage = remotePackage;
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-repa-import1'))?>,
                    id: remotePackage.id,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('import_remote_package'))?>
            })
            .always(() => {
                this.busy = false;
                this.importingRemotePackage = null;
            })
            .done((data) => {
                for (const key in data) {
                    remotePackage[key] = data[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
        loadNextPage: function() {
            if (this.busy || this.noMoreRemotePackages) {
                return;
            }
            const lastRemotePackage = this.remotePackages[this.remotePackages.length - 1];
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-repa-nextpage'))?>,
                    id: lastRemotePackage.id,
                    createdOnDB: lastRemotePackage.createdOnDB,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('get_next_page'))?>
            })
            .always(() => {
                this.busy = false;
            })
            .done((data) => {
                if (data.length === 0) {
                    this.noMoreRemotePackages = true;
                } else {
                    data.forEach((remotePackage) => {
                        this.remotePackages.push(remotePackage);
                    });
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
    }
});

});</script>
