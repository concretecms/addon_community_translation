<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $devVersions
 */
?>
<div id="ct-app" v-cloak>
    <div v-if="devVersions.length === 0" class="alert alert-info">
        <?= t('No development version has been found.') ?>
    </div>
    <div v-else>
        <table class="table table-striped">
            <colgroup>
                <col width="1" />
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2"></th>
                    <th rowspan="2"><?= t('Package') ?></th>
                    <th rowspan="2"><?= t('Development Version') ?></th>
                    <th colspan="3" class="text-center"><?= t('Package Source') ?></th>
                </tr>
                <tr>
                    <th class="text-center"><?= t('Remote Package') ?></th>
                    <th class="text-center"><?= t('Git Repository') ?></th>
                    <th class="text-center"><?= t('API Request') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="devVersion in devVersions">
                    <td><button v-bind:disabled="busy" v-on:click.prevent="askDeleteDevVersion(devVersion)" class="btn btn-sm btn-danger">&times;</button></td>
                    <td>{{ devVersion.package }}</td>
                    <td>
                        {{ devVersion.name }}<br />
                        <code>{{ devVersion.version }}</code>
                    </td>
                    <td class="text-center">
                        <span class="text-success" v-if="devVersion.fromRemotePackage">&check;</span>
                        <span class="text-danger" v-if="devVersion.fromRemotePackage === false">&times;</span>
                    </td>
                    <td>
                        <div class="text-center text-danger" v-if="devVersion.fromGitRepositories === null">&times;</div>
                        <i v-else-if="devVersion.fromGitRepositories.length === 0"><?= tc('Repository', 'none') ?></i>
                        <ul v-else class="my-0">
                            <li v-for="gitRepository in devVersion.fromGitRepositories">
                                <span v-if="gitRepository.detailsUrl.length === 0">{{ gitRepository.name }}</span>
                                <a v-else v-bind:href="gitRepository.detailsUrl" target="_blank">{{ gitRepository.name }}</a>
                            </li> 
                        </ul>
                    </td>
                    <td class="text-center">
                        <span class="text-success" v-if="devVersion.fromApiRequest">&check;</span>
                        <span class="text-danger" v-if="devVersion.fromApiRequest === false">&times;</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<script>$(document).ready(function() {

new Vue({
    el: '#ct-app',
    data() {
        return {
            devVersions: <?= json_encode($devVersions) ?>,
            busy: false,
        };
    },
    methods: {
        askDeleteDevVersion(devVersion) {
            if (this.busy) {
                return;
            }
            ConcreteAlert.confirm(
                <?= json_encode(t('Are you sure you want to delete the development version %1$s of package %2$s?')) ?>.replace('%1$s', devVersion.version).replace('%2$s', devVersion.package),
                () => {
                    $.fn.dialog.closeTop();
                    this.deleteDevVersion(devVersion);
                },
                'btn-danger',
                <?= json_encode(t('Delete')) ?>
            );
        },
        deleteDevVersion(devVersion) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            $.ajax({
                data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-dever-delete')]) ?>, {
                    id: devVersion.id
                }),
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('deleteDevVersion')) ?>
            })
            .always(() => {
                this.busy = false;
            })
            .done((data) => {
                if (data !== true) {
                    ConcreteAlert.dialog(ccmi18n.error, <?= json_encode('Unexpected server response') ?>);
                    return;
                }
                const index = this.devVersions.indexOf(devVersion);
                if (index >= 0) {
                    this.devVersions.splice(index, 1);
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
    },
});

});</script>
