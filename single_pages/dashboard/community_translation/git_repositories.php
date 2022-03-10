<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
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
    <table class="table">
        <thead>
            <tr>
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
                    <td><a href="<?= h($urlResolver->resolve(['/dashboard/community_translation/git_repositories/details', $repository->getID()])) ?>"><?= h($repository->getName()) ?></a></td>
                    <td><?= h($repository->getPackageHandle()) ?></td>
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
    <?php
}
