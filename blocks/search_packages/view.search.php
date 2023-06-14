<?php

declare(strict_types=1);

use Pagerfanta\View\TwitterBootstrap4View;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\SearchPackages\Controller $controller
 * @var string|int $bID
 * @var CommunityTranslation\Entity\Locale[] $allLocales
 * @var CommunityTranslation\Entity\Locale[] $suggestedLocales
 * @var CommunityTranslation\Entity\Locale[] $myLocales
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $sticky
 * @var CommunityTranslation\Search\Results\Packages $result
 */

// When there's a warning:
/**
 * @var string $showWarning
 */

if (is_string($showWarning ?? null) && $showWarning !== '') {
    ?>
    <div class="alert alert-danger">
        <?= $showWarning ?>
    </div>
    <?php
}
$searchLocale = '';
if (is_string($sticky['locale'] ?? null) && $sticky['locale'] !== '') {
    foreach ($allLocales as $locale) {
        if ($locale->getID() === $sticky['locale']) {
            $searchLocale = $sticky['locale'];
            break;
        }
    }
}
?>
<div class="card card-info mt-3">
    <div class="card-header">
        <?= t('Search translatable packages') ?>
    </div>
    <div class="card-body">
        <div class="card-text">
            <form class="mb-3" action="<?= h($controller->getBlockActionURL('search')) ?>" method="POST">
                <?php $token->output('communitytranslations-search_packages-' . $bID) ?>
                <div class="input-group">
                    <input class="form-control" id="comtra_search_packages-search-text-<?= $bID ?>" type="search" name="keywords" value="<?= isset($sticky['keywords']) && is_string($sticky['keywords']) ? h($sticky['keywords']) : '' ?>" placeholder="<?= t('Search by handle or name') ?>" />
                    <select class="form-control" id="comtra_search_packages-search-locale-<?= $bID ?>" name="locale">
                        <option value=""<?= $searchLocale === '' ? ' selected="selected"' : '' ?>>** <?= tc('Language', 'Show progress for') ?></option>
                        <?php
                        if ($suggestedLocales === '') {
                            foreach ($allLocales as $locale) {
                                ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : '' ?>><?= h($locale->getDisplayName()) ?></option><?php
                            }
                        } else {
                            ?>
                            <optgroup label="<?= t('My languages') ?>">
                                <?php
                                foreach ($suggestedLocales as $locale) {
                                    ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : '' ?>><?= h($locale->getDisplayName()) ?></option><?php
                                }
                                ?>
                            </optgroup>
                            <optgroup label="<?= t('Other languages') ?>">
                                <?php
                                foreach (array_diff($allLocales, $suggestedLocales) as $locale) {
                                    ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : '' ?>><?= h($locale->getDisplayName()) ?></option><?php
                                }
                                ?>
                            </optgroup>
                            <?php
                        }
                        ?>
                    </select>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-sm btn-primary px-4"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </form>
            <?php
            $foundResults = $result->getItems();
            /** @var \CommunityTranslation\Search\Results\Item\Package[] $foundResults */
            if ($foundResults === []) {
                ?>
                <div class="alert alert-warning">
                    <?= t('No packages satisfy the search criteria') ?>
                </div>
                <?php
            } else {
                ?>
                <br />
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th><?= t('Package Name') ?></th>
                            <th class="d-none d-sm-table-cell"><?= t('Latest version') ?></th>
                            <th class="d-none d-md-table-cell"><?= t('Handle') ?></th>
                            <?php
                            if ($searchLocale !== '') {
                                ?><th><?= t('Progress') ?></th><?php
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($foundResults as $foundResult) {
                            $package = $foundResult->getPackage();
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= h($controller->getBlockActionURL('package', $package->getHandle())) ?>"><?= h($package->getDisplayName()) ?></a>
                                    <?php
                                    if ($package->getUrl()) {
                                        ?><small><br />(<a href="<?= h($package->getUrl()) ?>" target="_blank"><?= t('more details') ?></a>)</small><?php
                                    }
                                    ?>
                                </td>
                                <td class="d-none d-sm-table-cell"><?php
                                    $lv = $package->getLatestVersion();
                                    if ($lv) {
                                        echo $lv->getDisplayVersion();
                                    } else {
                                        echo '?';
                                    }
                                ?></td>
                                <td class="d-none d-md-table-cell"><code><?= h($package->getHandle()) ?></code>
                                <?php
                                if ($searchLocale !== '') {
                                    ?><td><?php
                                    $stats = $foundResult->getStats();
                                    if ($stats === null) {
                                        echo t('No progress stats available (does the package have at least one version?)');
                                    } else {
                                        ?>
                                        <div class="progress" style="margin: 0" title="<?= t2('%1s out of %2$s untranslated string', '%1s out of %2$s untranslated strings', $stats->getUntranslated(), $stats->getTotal()) ?>">
                                            <div class="progress-bar <?= $controller->percToProgressbarClass((int) $stats->getPercentage()) ?>" role="progressbar" style="width: <?= $stats->getRoundedPercentage() ?>%">
                                                <span><?= $stats->getRoundedPercentage() ?></span>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?></td><?php
                                }
                                ?>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <?php
                $pagination = $result->getPagination();
                if ($pagination->haveToPaginate()) {
                    ?>
                    <div class="ccm-search-results-pagination">
                        <?php
                        $paginationTemplateView = new TwitterBootstrap4View();
                        echo $paginationTemplateView->render(
                            $pagination,
                            function (int $page) use ($result): string {
                                $list = $result->getItemListObject();
                                $pageUrl = (string) $result->getBaseURL();
                                $pageUrl .= strpos($pageUrl, '?') === false ? '?' : '&';
                                $pageUrl .= ltrim($list->getQueryPaginationPageParameter(), '&') . '=' . $page;

                                return $pageUrl;
                            },
                            [
                                'prev_message' => tc('Pagination', '&larr; Previous'),
                                'next_message' => tc('Pagination', 'Next &rarr;'),
                                'active_suffix' => '<span class="sr-only">' . tc('Pagination', '(current)') . '</span>',
                            ]
                        );
                        ?>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
</div>
