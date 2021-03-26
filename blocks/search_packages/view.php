<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var int $bID */
/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Core\Block\Block $b */
/* @var Concrete\Core\Entity\Block\BlockType\BlockType $bt */
/* @var Concrete\Core\Area\Area $a */

/* @var Concrete\Package\CommunityTranslation\Block\SearchPackages\Controller $controller */

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var CommunityTranslation\Entity\Locale[] $suggestedLocales */
/* @var CommunityTranslation\Entity\Locale[] $allLocales */
/* @var CommunityTranslation\Entity\Locale[] $myLocales */

// When there's a warning:
/* @var string $showWarning */

// When viewing a package:
/* @var CommunityTranslation\Entity\Package $package */
/* @var CommunityTranslation\Entity\Package\Version[] $packageVersions */
/* @var CommunityTranslation\Entity\Package\Version $packageVersion */
/* @var bool $userIsLoggedIn */
/* @var array $localeInfos */
/* @var string $onlineTranslationPath */

//When viewing search results:
/* @var array $sticky */
/* @var CommunityTranslation\Search\Results\Packages $result */

if (isset($showWarning) && $showWarning !== '') {
    ?>
    <div class="alert alert-danger" role="alert">
        <?= $showWarning ?>
    </div>
    <?php
}

if (isset($package)) {
    ?>
    <div class="card card-primary" style="margin-top: 20px">
        <div class="card-header">
            <?= t('Translations for %s', h($packageVersion->getDisplayName())) ?>
            <div class="float-right"><a class="btn btn-sm btn-info" href="<?= h($controller->getBlockActionURL($view, 'search')) ?>"><?= t('Search other packages') ?></a></div>
        </div>
        <div class="card-body">
            <div class="card-text">
            <?php
            if ($package->getUrl() !== '') {
                ?><p><?= t('You can get more details on this package <a href="%s" target="_blank">here</a>.', h($package->getUrl())) ?></p><?php
            }
            ?>
            <form class="form-inline" onsubmit="return false">
                <div class="form-group">
                    <label for="comtra_search_packages-version"><?= t('Package version') ?></label>
                    <select class="form-control" id="comtra_search_packages-version" onchange="if (this.value) { window.location.href = this.value; this.disabled = true }">
                        <?php
                        foreach ($packageVersions as $pv) {
                            if ($pv === $packageVersion) {
                                ?><option value="" selected="selected"><?= h($pv->getDisplayVersion()) ?></option><?php
                            } else {
                                ?><option value="<?= h($controller->getBlockActionURL($view, 'package', $package->getHandle(), $pv->getVersion())) ?>"><?= h($pv->getDisplayVersion()) ?></option><?php
                            }
                        }
                        ?>
                    </select>
                </div>
            </form>
            <table class="table table-striped comtra-sortable comtra-searchpackages-languages" data-comtra-sortable-persister-key="comtra-searchpackages-languages-<?= $bID ?>">
                <col width="1" />
                <col />
                <col width="1" />
                <thead>
                    <tr>
                        <th><?= t('ID') ?></th>
                        <th class="comtra-sorted-asc"><?= t('Language') ?></th>
                        <th></th>
                        <th class="hidden-xs hidden-sm" data-sortby-default="desc"><?= t('Updated') ?></th>
                        <th class="hidden-xs" data-sortby-default="desc"><?= t('Progress') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sections = [];
                    if (empty($suggestedLocales)) {
                        $sections[''] = $allLocales;
                    } else {
                        $sections[t('My languages')] = $suggestedLocales;
                        $sections[t('Other languages')] = array_diff($allLocales, $suggestedLocales);
                    }
                    $sections = array_filter($sections);
                    $sectionIndex = 0;
                    foreach ($sections as $sectionName => $locales) {
                        if (count($sections) > 1) {
                            ?><tr><th colspan="5" class="text-center"><?= $sectionName ?></th></tr><?php
                        }
                        foreach ($locales as $locale) {
                            $localeInfo = $localeInfos[$locale->getID()];
                            $whyNotTranslatable = null;
                            if ($userIsLoggedIn) {
                                if (!in_array($locale, $myLocales, true)) {
                                    $whyNotTranslatable = t('You don\'t belong to this translation group');
                                }
                            }
                            if ($whyNotTranslatable === null) {
                                $translateLink = URL::to($onlineTranslationPath, $packageVersion->getID(), $locale->getID());
                                $translateOnclick = '';
                                $translateClass = 'btn-primary';
                            } else {
                                $translateLink = '#';
                                $translateOnclick = ' onclick="' . h('window.alert(' . json_encode($whyNotTranslatable) . '); return false') . '"';
                                $translateClass = 'btn-secondary';
                            }
                            ?>
                            <tr data-sortsection="<?= $sectionIndex ?>">
                                <td data-sortby="<?= h(mb_strtolower($locale->getID())) ?>"><span class="badge badge-default"><?= h($locale->getID()) ?></span></td>
                                <td data-sortby="<?= h(mb_strtolower($locale->getDisplayName())) ?>"><?= $locale->getDisplayName() ?></td>
                                <td class="comtra-locale-actions">
                                    <?php
                                    foreach ($localeInfo['downloadFormats'] as $adf) {
                                        ?><a class="hidden-xs btn btn-sm btn-info" style="padding: 5px 10px" href="<?= h($controller->getBlockActionURL($view, 'download_translations_file', $packageVersion->getID(), $locale->getID(), $adf->getHandle()) . '?' . $token->getParameter('comtra-download-translations-' . $packageVersion->getID() . '@' . $locale->getID() . '.' . $adf->getHandle())) ?>" title="<?= h(t('Download translations (%s)', $adf->getName())) ?>" style="padding: 5px 10px; white-space:nowrap"><i class="fa fa-cloud-download"></i> <?= h($adf->getFileExtension()) ?></a><?php
                                    }
                                    ?>
                                    <a class="btn btn-sm <?= $translateClass ?>" style="padding: 5px 10px" href="<?= h($translateLink) ?>"<?= $translateOnclick ?>><?= t('Translate') ?></a>
                                </td>
                                <td class="hidden-xs hidden-sm" data-sortby="<?= h($localeInfo['updatedOn_sort']) ?>"><?= h($localeInfo['updatedOn']) ?></td>
                                <td class="hidden-xs" data-sortby-kind="numeric" data-sortby="<?= $localeInfo['percSort'] ?>"><div class="progress" style="margin: 0" title="<?= t2('%s untranslated string', '%s untranslated strings', $localeInfo['untranslated']) ?>">
                                    <div class="progress-bar <?= $localeInfo['progressBarClass'] ?>" role="progressbar" style="width: <?= $localeInfo['perc'] ?>%">
                                        <span><?= $localeInfo['perc'] ?></span>
                                    </div>
                                </div></td>
                            </tr>
                            <?php
                        }
                        ++$sectionIndex;
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <?php
    return;
}
?>
<div class="card card-info" style="margin-top: 20px">
    <div class="card-header">
        <?= t('Search translatable packages') ?>
    </div>
    <div class="card-body">
        <div class="card-text">
        <form class="form-inline" action="<?= $controller->getBlockActionURL($view, 'search') ?>" method="POST" style="margin-bottom: 15px">
            <div class="input-group">
                <?php $token->output('communitytranslations-search_packages-' . $bID) ?>
                <div class="form-group">
                    <label class="sr-only" for="comtra_search_packages-search-text-<?= $bID ?>"><?= t('Search package') ?></label>
                    <input id="comtra_search_packages-search-text-<?= $bID ?>" type="search" name="keywords" class="form-control" value="<?= isset($sticky['keywords']) && is_string($sticky['keywords']) ? h($sticky['keywords']) : ''?>" placeholder="<?= t('Search by handle or name') ?>" />
                </div>
                <div class="form-group">
                    <label class="sr-only" for="comtra_search_packages-search-locale-<?= $bID ?>"><?= tc('Language', 'Show progress for') ?></label>
                    <?php
                    $searchLocale = '';
                    if (isset($sticky['locale']) && is_string($sticky['locale']) && $sticky['locale'] !== '') {
                        foreach ($allLocales as $locale) {
                            if ($locale->getID() === $sticky['locale']) {
                                $searchLocale = $sticky['locale'];
                                break;
                            }
                        }
                    }
                    ?>
                    <select id="comtra_search_packages-search-locale-<?= $bID ?>" name="locale" class="form-control">
                        <option value=""<?= $searchLocale === '' ? ' selected="selected"' : ''?>>** <?= tc('Language', 'Show progress for') ?></option>
                        <?php
                        if (empty($suggestedLocales)) {
                            foreach ($allLocales as $locale) {
                                ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : ''?>><?= h($locale->getDisplayName()) ?></option><?php
                            }
                        } else {
                            ?>
                            <optgroup label="<?= t('My languages') ?>">
                                <?php
                                foreach ($suggestedLocales as $locale) {
                                    ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : ''?>><?= h($locale->getDisplayName()) ?></option><?php
                                }
                                ?>
                            </optgroup>
                            <optgroup label="<?= t('Other languages') ?>">
                                <?php
                                foreach (array_diff($allLocales, $suggestedLocales) as $locale) {
                                    ?><option value="<?= h($locale->getID()) ?>"<?= $searchLocale === $locale->getID() ? ' selected="selected"' : ''?>><?= h($locale->getDisplayName()) ?></option><?php
                                }
                                ?>
                            </optgroup>
                            <?php
                        }
                        ?>
                    </select>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-outline-primary"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <?php
        $foundResults = $result->getItems();
        /* @var \CommunityTranslation\Search\Results\Item\Package[] $foundResults */
        if (empty($foundResults)) {
            ?>
            <div class="alert alert-warning" role="alert">
                <?= t('No package satisfy the search criteria') ?>
            </div>
            <?php
        } else {
            ?>
            <br />
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th><?= t('Package Name') ?></th>
                        <th class="hidden-xs"><?= t('Latest version') ?></th>
                        <th class="hidden-xs hidden-sm"><?= t('Handle') ?></th>
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
                                <a href="<?= h($controller->getBlockActionURL($view, 'package', $package->getHandle())) ?>"><?= h($package->getDisplayName()) ?></a>
                                <?php
                                if ($package->getUrl()) {
                                    ?><small><br />(<a href="<?= h($package->getUrl()) ?>" target="_blank"><?= t('more details') ?></a>)</small><?php
                                }
                                ?>
                            </td>
                            <td class="hidden-xs"><?php
                                $lv = $package->getLatestVersion();
                                if ($lv) {
                                    echo $lv->getDisplayVersion();
                                } else {
                                    echo '?';
                                }
                            ?></td>
                            <td class="hidden-xs hidden-sm"><code><?= h($package->getHandle()) ?></code>
                            <?php
                            if ($searchLocale !== '') {
                                ?><td><?php
                                $stats = $foundResult->getStats();
                                if ($stats === null) {
                                    echo t('No progress stats available (does the package have at least one version?)');
                                } else {
                                    ?>
                                    <div class="progress" style="margin: 0" title="<?= t2('%1s out of %2$s untranslated string', '%1s out of %2$s untranslated strings', $stats->getUntranslated(), $stats->getTotal()) ?>">
                                        <div class="progress-bar <?= $controller->percToProgressbarClass($stats->getPercentage(false)) ?>" role="progressbar" style="width: <?= $stats->getPercentage(true) ?>%">
                                            <span><?= $stats->getPercentage(true) ?></span>
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
            $pagination = $result->getPaginationHTML();
            if ($pagination) {
                ?>
                <div class="ccm-search-results-pagination">
                    <?= $pagination ?>
                </div>
                <?php
            }
        }
        ?>
        </div>
    </div>
</div>
