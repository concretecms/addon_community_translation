<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var CommunityTranslation\Entity\GitRepository[] $repositories
 */

?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation/git_repositories/details', 'new'])) ?>" class="btn btn-primary"><?= t('Add Git Repository') ?></a>
</div>

<?php
if ($repositories === []) {
    ?>
    <div class="alert alert-info">
        <?= t('No Git Repository has been defined.') ?>
    </div>
    <?php
} else {
    ?>
    <table class="table" id="ct-list" v-cloak>
        <colgroup>
            <col width="1" />
        </colgroup>
        <thead>
            <tr>
                <th></th>
                <th><?= t('Mnemonic name') ?></th>
                <th><?= t('Package') ?></th>
                <th><?= t('URL') ?></th>
                <th><?= t('Root directory') ?></th>
                <th><?= t('Tags') ?></th>
                <th><?= t('Dev branches') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($repositories as $repository) {
                ?>
                <tr>
                    <td><button v-bind:disabled="busy" type="button" class="btn btn-sm btn-secondary" title="<?= t('Import strings') ?>" v-on:click.prevent="importGit(<?= $repository->getID() ?>)"><i class="fas fa-sync-alt" v-bind:class="{'fa-spin': importingID === <?= $repository->getID() ?>}"></i></button></td>
                    <td><a v-bind:disabled="busy" href="<?= h($urlResolver->resolve(['/dashboard/community_translation/git_repositories/details', $repository->getID()])) ?>"><?= h($repository->getName()) ?></a></td>
                    <td>
                        <?= h($repository->getPackageName()) ?><br />
                        <code><?= h($repository->getPackageHandle()) ?></code>
                    </td>
                    <td><a href="<?= h($repository->getURL()) ?>" target="_blank"><?= h($repository->getURL()) ?></a></td>
                    <td><code>/<?= h($repository->getDirectoryToParse()) ?></code></td>
                    <td><?= h($repository->getTagFiltersDisplayName()) ?></td>
                    <td>
                        <?php
                        $devBranches = $repository->getDevBranches();
                        if ($devBranches === []) {
                            ?><i><?= tc('Branch', 'none') ?></i><?php
                        } else {
                            foreach ($devBranches as $branch => $version) {
                                echo t('Branch %s &rarr; version %s', '<code>' . h($branch) . '</code>', '<code>' . h($version) . '</code>'), '<br />';
                            }
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <script>
    $(document).ready(() => {
        new Vue({
            el: '#ct-list',
            data() {
                return {
                    importingID: null,
                    busy: false,
                };
            },
            mounted() {
                $(window).on('beforeunload', (e) => {
                    if (!this.busy) {
                        return;
                    }
                    return e.returnValue = 'confirm';
                });
            },
            methods: {
                importGit(id) {
                    if (this.busy) {
                        return;
                    }
                    this.busy = true;
                    this.importingID = id;
                    $.ajax({
                        data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-gitrepositories-import')]) ?>, {id: id}),
                        dataType: 'json',
                        method: 'POST',
                        url: <?= json_encode((string) $view->action('import')) ?>,
                    })
                    .always(() => {
                        this.importingID = null;
                        this.busy = false;
                    })
                    .done(function(data) {
                        if (data === true) {
                            ConcreteAlert.dialog(<?= json_encode(t('Completed')) ?>, <?= json_encode(t('The translatable strings have been imported.')) ?>);
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
    <?php
}
