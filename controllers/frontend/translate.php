<?php
namespace Concrete\Package\CommunityTranslation\Controller\Frontend;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\Editor;
use Controller;
use View;

class Translate extends Controller
{
    const PACKAGEVERSION_UNREVIEWED = 'unreviewed';

    public function getViewObject()
    {
        $v = new View('frontend/translate');
        $v->setPackageHandle('community_translation');
        $v->setViewTheme(null);

        return $v;
    }

    public function view($packageVersionID = '', $localeID = '')
    {
        $error = null;
        if ($error === null) {
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                // @todo redirect to login
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                $error = t("You don't belong to the %s translation group", $locale->getDisplayName());
            }
        }
        if ($error === null) {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                $error = t('Invalid language identifier received');
            }
        }
        if ($error === null) {
            $packageVersion = null;
            if ($packageID === self::PACKAGEVERSION_UNREVIEWED) {
                if ($access >= Access::ADMIN) {
                    $packageVersion = self::PACKAGEVERSION_UNREVIEWED;
                }
            } else {
                $packageVersion = $this->app->make(PackageVersionRepository::class)->find($packageVersionID);
            }
            if ($packageVersion === null) {
                $error = t('Invalid translated package version identifier received');
            }
        }
        if ($error !== null) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('An unexpected error occurred.'),
                h($error)
            );
        }
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate/online.css', 'community_translation'));
        $this->addFooterItem($hh->javascript('bootstrap.min.js', 'community_translation'));
        $this->addFooterItem($hh->javascript('markdown-it.min.js', 'community_translation'));
        $this->addFooterItem($hh->javascript('translate/online.js', 'community_translation'));
        $this->requireAsset('javascript', 'jquery');
        $this->requireAsset('core/translator');
        if ($packageVersion === self::PACKAGEVERSION_UNREVIEWED) {
            $this->set('packageVersion', null);
        } else {
            $this->set('packageVersion', $packageVersion);
        }
        $this->set('token', $this->app->make('token'));
        $this->set('canApprove', $access >= Access::ADMIN);
        $this->set('locale', $locale);
        $this->set('canEditGlossary', $access >= Access::ADMIN);
        $pluralCases = [];
        foreach ($locale->getPluralForms() as $pluralForm) {
            list($pluralFormKey, $pluralFormExamples) = explode(':', $pluralForm);
            $pluralCases[$pluralFormKey] = $pluralFormExamples;
        }
        $this->set('pluralCases', $pluralCases);
        if ($packageVersion === static::PACKAGEVERSION_UNREVIEWED) {
            $this->set('translations', $this->app->make(Editor::class)->getUnreviewedInitialTranslations($locale));
            $this->set('pageTitle', t(/*i18n: %s is a language name*/'Strings awaiting review in %s', $locale->getDisplayName()));
        } else {
            $this->set('translations', $this->app->make(Editor::class)->getInitialTranslations($packageVersion, $locale));
            $this->set('pageTitle', t(/*i18n: %1$s is a package name, %2$s is a language name*/'Translating %1$s in %2$s', $packageVersion->getDisplayName(), $locale->getDisplayName()));
        }
    }
}
