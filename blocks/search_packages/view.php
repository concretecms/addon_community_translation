<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Package\CommunityTranslation\Block\SearchPackages\Controller $controller */
/* @var int $bID */

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var CommunityTranslation\Entity\Package|null $package */
/* @var CommunityTranslation\Entity\Package\Version[]|null $packageVersions */
/* @var CommunityTranslation\Entity\Package\Version|null $packageVersion */

if (isset($showWarning) && $showWarning !== '') {
    ?>
    <div class="alert alert-danger" role="alert">
        <p><?= $showWarning ?></p>
    </div>
    <?php
}

if (isset($package)) {
    ?>
    <ul class="nav-tabs nav" id="comtra_search_packages-tab-headers">
        <li class="active"><a href="#" data-tab="comtra_search_packages-tab-package"><?= h($package->getDisplayName()) ?></a></li>
        <li><a href="#" data-tab="comtra_search_packages-tab-search"><?= t('Search other packages') ?></a></li>
    </ul>
    <div class="tab-content" id="comtra_search_packages-tab-panes" style="padding-top: 20px">
        <div class="tab-pane active" id="comtra_search_packages-tab-package">
            <div class="container">
                <div class="col-sm-12">
                    <?php
                    if (empty($packageVersions)) {
                        ?>
                        <div class="alert alert-danger" role="alert">
                            <p><?= h(t('The package "%s" doesn\'t have any version', $package->getDisplayName())) ?></p>
                        </div>
                        <?php
                    } else {
                        ?>
                        <form class="form-inline" onsubmit="return false">
                            <div class="form-group">
                                <label for="comtra_search_packages-version"><?= t('Package version') ?></label>
                                <select class="form-control" id="comtra_search_packages-version" onchange="if (this.value) { window.location.href = this.value; this.disabled = true }">
                                    <?php
                                    if ($packageVersion === null) {
                                        ?><option value="" selected="selected"><?= t('Please Select') ?></option><?php
                                    }
                                    foreach ($packageVersions as $pv) {
                                        if ($pv === $packageVersion) {
                                            ?><option value="" selected="selected"><?= h($pv->getDisplayVersion()) ?></option><?php
                                        } else {
                                            ?><option value="<?= h($controller->getActionURL($view, 'package', $package->getHandle(), $pv->getVersion())) ?>"><?= h($pv->getDisplayVersion()) ?></option><?php
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </form>
                        <?php
                        if ($packageVersion !== null) {
                            /* @var CommunityTranslation\Entity\Locale[] $allLocales */
                            /* @var CommunityTranslation\Entity\Locale[] $myLocales */
                            /* @var bool $showLoginMessage */
                            /* @var array $localeInfos */
                            /* @var string $onlineTranslationPath */
                            ?>
                            <div class="panel panel-primary" style="margin-top: 20px">
                                <div class="panel-heading">
                                    <?= t('Translations for %s', h($packageVersion->getDisplayName())) ?>
                                </div>
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
                                        if (empty($myLocales) || count($myLocales) === $allLocales) {
                                            $sections[''] = $allLocales;
                                        } else {
                                            $sections[t('My languages')] = $myLocales;
                                            $sections[t('Other languages')] = array_diff($allLocales, $myLocales);
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
                                                if (!in_array($locale, $myLocales, true)) {
                                                    if ($showLoginMessage) {
                                                        $whyNotTranslatable = t('You need to log-in in order to translate');
                                                    } else {
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
                                                    $translateClass = 'btn-default';
                                                }
                                                ?>
                                                <tr data-sortsection="<?= $sectionIndex ?>">
                                                    <td data-sortby="<?= h(mb_strtolower($locale->getID())) ?>"><span class="label label-default"><?= h($locale->getID()) ?></span></td>
                                                    <td data-sortby="<?= h(mb_strtolower($locale->getDisplayName())) ?>"><?= $locale->getDisplayName() ?></td>
                                                    <td class="comtra-locale-actions">
                                                        <?php
                                                        foreach ($localeInfo['downloadFormats'] as $adf) {
                                                            ?><a class="hidden-xs btn btn-sm btn-info" style="padding: 5px 10px" href="<?= h($controller->getActionURL($view, 'download_translations_file', $packageVersion->getID(), $locale->getID(), $adf->getHandle()) . '?' . $token->getParameter('comtra-download-translations-' . $packageVersion->getID() . '@' . $locale->getID() . '.' . $adf->getHandle())) ?>" title="<?= h(t('Download translations (%s)', $adf->getName())) ?>" style="padding: 5px 10px; white-space:nowrap"><i class="fa fa-cloud-download"></i> <?= h($adf->getFileExtension()) ?></a><?php
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
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="tab-pane" id="comtra_search_packages-tab-search" style="display: none"><?php
}

?>
<form class="form-inline" action="<?= $controller->getActionURL($view, 'search') ?>" method="POST">
    <?php $token->output('comtra_search_packages-search') ?>
    <div class="form-group">
        <label for="comtra_search_packages-search-text"><?= t('Search package') ?></label>
        <input type="search" name="text" class="form-control" id="comtra_search_packages-search-text" value="<?= isset($searchText) ? h($searchText) : ''?>" placeholder="<?= t('Search by handle or name') ?>" required="required" pattern="\s*\w+.*">
        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
    </div>
</form>
<?php
if (isset($searchError) && $searchError !== '') {
    ?>
    <div class="alert alert-warning" role="alert">
        <p><?= $searchError ?></p>
    </div>
    <?php
}
if (!empty($foundPackages)) {
    /* @var CommunityTranslation\Entity\Package $foundPackages */
    ?>
    <div id="searchResults">
        <?php
        foreach ($foundPackages as $foundPackage) {
            ?>
            <div class="searchResult">
                <h3><a href="<?= h($controller->getActionURL($view, 'package', $foundPackage->getHandle())) ?>"><?= h($foundPackage->getDisplayName()) ?></a></h3>
                <p class="text-muted">
                    <?= h(t('Package handle: %s', $foundPackage->getHandle())) ?><br />
                    <?= h(t('Number of available versions: %d', count($foundPackage->getVersions()))) ?>
                </p>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}

if (isset($package)) {
    ?>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        $('#comtra_search_packages-tab-headers a').on('click', function(e) {
            e.preventDefault();
            $('#comtra_search_packages-tab-headers>li').removeClass('active');
            $('#comtra_search_packages-tab-panes>.tab-pane').hide().removeClass('active');
            var $a = $(this);
            $a.closest('li').addClass('active');
            $('#comtra_search_packages-tab-panes>#' + $a.data('tab')).show().addClass('active');
        })
    });
    </script>
    <?php
}
