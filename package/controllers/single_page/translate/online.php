<?php

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Translate;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Concrete\Package\CommunityTranslation\Src\Service\Access;

class Online extends PageController
{
    public function view($packageID = '', $localeID = '')
    {
        $error = null;
        if ($error === null) {
            $package = $packageID ? $this->app->make('community_translation/package')->find($packageID) : null;
            if ($package === null) {
                $error = t('Invalid translated package identifier received');
            }
        }
        if ($error === null) {
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                $error = t('Invalid language identifier received');
            }
        }
        if ($error === null) {
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                $error = t("You don't belong to the %s translation group", $locale->getDisplayName());
            }
        }
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/translate');
        }
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate/online.css', 'community_translation'));
        $this->addFooterItem($hh->javascript('bootstrap.min.js', 'community_translation'));
        $this->addFooterItem($hh->javascript('translate/online.js', 'community_translation'));
        $this->requireAsset('javascript', 'jquery');
        $this->requireAsset('core/translator');
        $this->set('package', $package);
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('canApprove', $access >= Access::ADMIN);
        $this->set('locale', $locale);
        $this->set('canEditGlossary', $access >= Access::ADMIN);
        $pluralCases = array();
        foreach ($locale->getPluralForms() as $pluralForm) {
            list($pluralFormKey, $pluralFormExamples) = explode(':', $pluralForm);
            $pluralCases[$pluralFormKey] = $pluralFormExamples;
        }
        $this->set('pluralCases', $pluralCases);
        $this->set('translations', $this->app->make('community_translation/editor')->getInitialTranslations($package, $locale));
    }

    public function load_translation($localeID, $packageID = null)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-load-translation'.$localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $translatableID = $this->post('translatableID');
            $translatable = (is_string($translatableID) && $translatableID) ? $this->app->make('community_translation/translatable')->find($translatableID) : null;
            if ($translatable === null) {
                throw new UserException(t('Invalid translatable string identifier received'));
            }
            $package = null;
            $packageID = $this->post('packageID');
            if ($packageID) {
                $package = $this->app->make('community_translation/package')->find($packageID);
                if ($package === null) {
                    $error = t('Invalid translated package identifier received');
                }
            }
            return JsonResponse::create(
                $this->app->make('community_translation/editor')->getTranslatableData($locale, $translatable, $package, true)
            );
        } catch (UserException $x) {
            return JsonResponse::create(
                array(
                    'error' => $x->getMessage(),
                ),
                400
            );
        }
    }
}
