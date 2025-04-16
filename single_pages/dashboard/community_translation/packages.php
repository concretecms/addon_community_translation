<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation\Packages $controller
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 */

?>
<div id="app" v-cloak>
    <div class="input-group input-group-sm mb-3" style="max-width: 30em">
        <input type="search" class="form-control" placeholder="Search" v-model.trim="searchText" v-on:keyup.enter="search()" ref="searchText" v-bind:readonly="busy" />
        <button class="btn btn-outline-secondary" v-bind:disabled="busy" v-on:click.prevent="search()"><i class="fas fa-search"></i></button>
    </div>
    <div v-if="packages !== null">
        <div v-if="packages.length === 0" class="alert alert-info">
            <?= t('No packages found') ?>
        </div>
        <div v-else>
            <table class="table table-sm table-hover">
                <colgroup>
                    <col width="1" />
                    <col width="1" />
                </colgroup>
                <thead>
                    <tr>
                        <th></th>
                        <th class="text-nowrap"><?= t('Handle') ?></th>
                        <th><?= t('Name') ?></th>
                        <th><?= t('Versions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in packages" v-bind:key="p.id">
                        <td class="text-nowrap">
                            <a href="#" title="<?= t('Delete Package') ?>" v-on:click.prevent="askDeletePackage(p)"><i class="text-danger far fa-trash-alt"></i></a>
                        </td>
                        <td class="text-nowrap"><code>{{ p.handle }}</code></td>
                        <td>
                            {{ p.displayName }}
                            <a href="#" v-on:click.prevent="askRenamePackage(p)" title="<?= t('Rename Package') ?>"><i class="far fa-edit"></i></a>
                        </td>
                        <td>
                            <i  v-if="p.versions.length === 0" class="small text-muted">
                                <?= t('No versions ') ?>
                            </i>
                            <table v-else class="table table-sm table-hover m-0">
                                <colgroup>
                                    <col width="1" />
                                </colgroup>
                                <tr v-for="v in p.versions" v-bind:key="v.id">
                                    <td class="text-nowrap">
                                        <a href="#" title="<?= t('Delete Package Version') ?>" v-on:click.prevent="askDeletePackageVersion(v)"><i class="text-danger far fa-trash-alt"></i></a>
                                    </td>
                                    <td>
                                        {{ v.name }}
                                        <i v-if="v.isDev" class="fas fa-code" title="<?= t('Development Version') ?>"></i>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>$(document).ready(function() {
'use strict';

new Vue({
    el: '#app',
    data() {
        return {
            busy: false,
            searchText: '',
            packages: null,
        };
    },
    mounted() {
        this.$refs.searchText.focus();
    },
    methods: {
        async search() {
            if (this.busy) {
                return;
            }
            if (this.searchText === '') {
                this.$refs.searchText.focus();
                return;
            }
            this.busy = true;
            try {
                const response = await window.fetch(
                    <?= json_encode((string) $controller->action('search')) ?>,
                    {
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            Accept: 'application/json',
                        },
                        method: 'POST',
                        body: new URLSearchParams([
                            [<?= json_encode($token::DEFAULT_TOKEN_NAME) ?>, <?= json_encode($token->generate('comtra-pkgs-search')) ?>],
                            ['__ccm_consider_request_as_xhr', '1'],
                            ['searchText', this.searchText],
                        ]),
                    }
                );
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error.message ?? data.error);
                }
                if (this.packages === null) {
                    this.packages = [];
                } else {
                    this.packages.splice(0, this.packages.length);
                }
                data.forEach((p) => {
                    p.versions.forEach((v) => v.package = p);
                    this.packages.push(p);
                });
            } catch(e) {
                window.ConcreteAlert.error({
                    message: e.message || e,
                });
            } finally {
                this.busy = false;
            }
        },
        askRenamePackage(p) {
            if (this.busy) {
                return;
            }
            const newName = window.prompt(<?= json_encode(t('Enter the new package name')) ?>, p.name || '')?.replace(/\s+/, ' ')?.trim() || '';
            if (!newName || newName === p.name) {
                return;
            }
            this.renamePackage(p, newName);
        },
        async renamePackage(p, newName) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            try {
                const response = await window.fetch(
                    <?= json_encode((string) $controller->action('renamePackage')) ?>,
                    {
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            Accept: 'application/json',
                        },
                        method: 'POST',
                        body: new URLSearchParams([
                            [<?= json_encode($token::DEFAULT_TOKEN_NAME) ?>, <?= json_encode($token->generate('comtra-pkgs-renamePackage')) ?>],
                            ['__ccm_consider_request_as_xhr', '1'],
                            ['packageID', p.id],
                            ['newName', newName],
                        ]),
                    }
                );
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error.message ?? data.error);
                }
                p.name = data.name;
                p.displayName = data.displayName;
            } catch(e) {
                window.ConcreteAlert.error({
                    message: e.message || e,
                });
            } finally {
                this.busy = false;
            }
        },
        askDeletePackage(p) {
            if (this.busy) {
                return;
            }
            if (p.versions.length !== 0) {
                window.ConcreteAlert.error({
                    message: <?= json_encode(t('Please delete all the versions before deleting a package')) ?>,
                });
                return;
            }
            if (!window.confirm(<?= json_encode(t('Are you sure you want to delete the package %1$s (handle: %2$s)')) ?>.replace('%1$s', p.displayName).replace('%2$s', p.handle))) {
                return;
            }
            this.deletePackage(p);
        },
        async deletePackage(p) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            try {
                const response = await window.fetch(
                    <?= json_encode((string) $controller->action('deletePackage')) ?>,
                    {
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            Accept: 'application/json',
                        },
                        method: 'POST',
                        body: new URLSearchParams([
                            [<?= json_encode($token::DEFAULT_TOKEN_NAME) ?>, <?= json_encode($token->generate('comtra-pkgs-deletePackage')) ?>],
                            ['__ccm_consider_request_as_xhr', '1'],
                            ['packageID', p.id],
                        ]),
                    }
                );
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error.message ?? data.error);
                }
                const index = this.packages === null ? -1 : this.packages.indexOf(p);
                if (index >= 0) {
                    this.packages.splice(index, 1);
                }
            } catch(e) {
                window.ConcreteAlert.error({
                    message: e.message || e,
                });
            } finally {
                this.busy = false;
            }
        },
        askDeletePackageVersion(v) {
            if (this.busy || !window.confirm(<?= json_encode(t('Are you sure you want to delete the version %1$s of the package %2$s (handle: %3$s)')) ?>.replace('%1$s', v.name).replace('%2$s', v.package.displayName).replace('%3$s', v.package.handle))) {
                return;
            }
            this.deletePackageVersion(v);
        },
        async deletePackageVersion(v) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            try {
                const response = await window.fetch(
                    <?= json_encode((string) $controller->action('deletePackageVersion')) ?>,
                    {
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            Accept: 'application/json',
                        },
                        method: 'POST',
                        body: new URLSearchParams([
                            [<?= json_encode($token::DEFAULT_TOKEN_NAME) ?>, <?= json_encode($token->generate('comtra-pkgs-deletePackageVersion')) ?>],
                            ['__ccm_consider_request_as_xhr', '1'],
                            ['packageID', v.package.id],
                            ['versionID', v.id],
                        ]),
                    }
                );
                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error.message ?? data.error);
                }
                const index = v.package.versions.indexOf(v);
                if (index >= 0) {
                    v.package.versions.splice(index, 1);
                }
            } catch(e) {
                window.ConcreteAlert.error({
                    message: e.message || e,
                });
            } finally {
                this.busy = false;
            }
        },
    },
});

});</script>
