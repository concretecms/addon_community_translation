<?php

declare(strict_types=1);

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
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var CommunityTranslation\Entity\Package $package
 * @var CommunityTranslation\Entity\Package\Version[] $packageVersions
 * @var CommunityTranslation\Entity\Package\Version $packageVersion
 * @var array $localeInfos
 * @var bool $userIsLoggedIn
 * @var string $onlineTranslationPath
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
?>
<div class="card card-primary mt-3">
    <div class="card-header">
        <?= t('Translations for %s', h($packageVersion->getDisplayName())) ?>
        <div class="float-end"><a class="btn btn-sm btn-outline-info" href="<?= h($controller->getBlockActionURL('search')) ?>"><?= t('Search other packages') ?></a></div>
    </div>
    <div class="card-body">
        <div class="card-text">
            <?php
            if ($package->getUrl() !== '') {
                ?><p><?= t('You can get more details on this package <a href="%s" target="_blank">here</a>.', h($package->getUrl())) ?></p><?php
            }
            ?>
            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="comtra_search_packages-version"><?= t('Package version') ?></label>
                        </div>        
                        <select class="form-control" id="comtra_search_packages-version" onchange="if (this.value) { window.location.href = this.value; this.disabled = true }">
                            <?php
                            foreach ($packageVersions as $pv) {
                                if ($pv === $packageVersion) {
                                    ?><option value="" selected="selected"><?= h($pv->getDisplayVersion()) ?></option><?php
                                } else {
                                    ?><option value="<?= h($controller->getBlockActionURL('package', $package->getHandle(), $pv->getVersion())) ?>"><?= h($pv->getDisplayVersion()) ?></option><?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <table class="table table-striped table-striped table-sortable h-100" data-table-sortable-persister-key="comtra-searchpackages-languages-<?= $bID ?>">
                <col width="1" />
                <col />
                <col />
                <col />
                <col width="1" />
                <thead>
                    <tr>
                        <th><?= t('ID') ?></th>
                        <th class="table-sortable-sorted-asc"><?= t('Language') ?></th>
                        <th class="d-none d-md-table-cell" data-sortby-default="desc"><?= t('Updated') ?></th>
                        <th class="d-none d-sm-table-cell" data-sortby-default="desc"><?= t('Progress') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sections = [];
                    if ($suggestedLocales === []) {
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
                                $translateLink = (string) $urlResolver->resolve([$onlineTranslationPath, $packageVersion->getID(), $locale->getID()]);
                                $translateOnclick = '';
                                $translateClass = 'btn-primary';
                            } else {
                                $translateLink = '#';
                                $translateOnclick = ' onclick="' . h('window.alert(' . json_encode($whyNotTranslatable) . '); return false') . '"';
                                $translateClass = 'btn-secondary';
                            }
                            ?>
                            <tr data-sortsection="<?= $sectionIndex ?>">
                                <td data-sortby="<?= h(mb_strtolower($locale->getID())) ?>">
                                    <div class="h-100 pr-2 d-flex flex-column align-items-baseline justify-content-around">
                                        <span class="badge bg-info"><?= h($locale->getID()) ?></span>
                                    </div>
                                </td>
                                <td data-sortby="<?= h(mb_strtolower($locale->getDisplayName())) ?>">
                                    <div class="h-100 d-flex flex-column justify-content-around">
                                        <?= $locale->getDisplayName() ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell" data-sortby="<?= h($localeInfo['updatedOn_sort']) ?>">
                                    <div class="h-100 d-flex flex-column justify-content-around">
                                        <?= h($localeInfo['updatedOn']) ?>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell h-100" data-sortby-kind="numeric" data-sortby="<?= $localeInfo['percSort'] ?>">
                                    <div class="h-100 px-2 d-flex flex-column justify-content-around">
                                        <div class="progress" style="margin: 0" title="<?= t2('%s untranslated string', '%s untranslated strings', $localeInfo['untranslated']) ?>">
                                            <div class="progress-bar <?= $localeInfo['progressBarClass'] ?>" role="progressbar" style="width: <?= $localeInfo['perc'] ?>%">
                                                <span><?= $localeInfo['perc'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <span class="d-none d-sm-inline">
                                        <?php
                                        foreach ($localeInfo['downloadFormats'] as $adf) {
                                            ?><a class="btn btn-sm btn-secondary" href="<?= h($controller->getBlockActionURL('download_translations_file', $packageVersion->getID(), $locale->getID(), $adf->getHandle()) . '?' . $token->getParameter('comtra-download-translations-' . $packageVersion->getID() . '@' . $locale->getID() . '.' . $adf->getHandle())) ?>" title="<?= h(t('Download translations (%s)', $adf->getName())) ?>" style="white-space:nowrap"><i class="fa fa-cloud-download"></i> <?= h($adf->getFileExtension()) ?></a><?php
                                        }
                                        ?>
                                    </span>
                                    <a class="btn btn-sm <?= $translateClass ?>" href="<?= h($translateLink) ?>"<?= $translateOnclick ?>><?= t('Translate') ?></a>
                                    <?php
                                    if ($localeInfo['totalStrings']) {
                                        ?>
                                        <div class="d-block d-sm-none small text-muted text-center">
                                            <?= sprintf('%s / %s', $localeInfo['translatedStrings'], $localeInfo['totalStrings']) ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                        }
                        $sectionIndex++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

