<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Teams;

use Concrete\Core\Page\Controller\PageController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Concrete\Package\CommunityTranslation\Src\Service\Access;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

class Create extends PageController
{
    protected function userIsGlobalAdmin()
    {
        $result = false;
        $me = new \User();
        if ($me->isRegistered()) {
            if ($me->getUserID() == USER_SUPER_ID || $me->inGroup($this->app->make('community_translation/groups')->getGlobalAdministrators())) {
                $result = true;
            }
        }

        return $result;
    }

    public function view()
    {
        $this->requireAsset('community_translation/common');
        $this->set('token', $this->app->make('helper/validation/token'));
        $me = new \User();
        if (!$me->isRegistered()) {
            $me = null;
        }
        if ($me === null) {
            $this->set('error', t('You need to sign-in in order to ask the creation of a new Translation Team'));
            $this->set('skip', true);
            return;
        }
        $this->set('canApprove', $this->userIsGlobalAdmin());
        
        $languages = array();
        foreach (\Gettext\Languages\Language::getAll() as $l) {
            if (strpos($l->id, '_') === false) {
                $languages[$l->id] = \Punic\Language::getName($l->id);
            }
        }
        id(new \Punic\Comparer())->sort($languages, true);
        $this->set('languages', $languages);
        $this->set('countries', $this->app->make('lists/countries')->getCountries());
    }

    public function get_language_countries()
    {
        $result = false;
        if ($this->app->make('helper/validation/token')->validate('comtra_get_language_countries', $this->post('token') ?: '!')) {
            $language = $this->post('language');
            if (is_string($language) && strlen($language)) {
                $list = $this->app->make('lists/countries')->getCountriesForLanguage($language);
                if (!empty($list)) {
                    $result = $list;
                }
            }
        }
        JsonResponse::create($result)->send();
        exit;
    }

    public function create_locale()
    {
        $me = new \User();
        if (!$me->isRegistered()) {
            $this->view();
            return;
        }
        $iAmGlobalAdmin = $this->userIsGlobalAdmin();
        $language = trim((string) $this->post('language') ?: '');
        $country = trim((string) $this->post('country') ?: '');
        $approve = ($iAmGlobalAdmin && $this->post('approve')) ? true : false;
        $noCountry = $this->post('no-country') ? true : false;
        $whyNoCountry = trim((string) $this->post('why-no-country') ?: '');
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_create_locale')) {
                throw new Exception($token->getErrorMessage());
            }
            if ($language === '') {
                throw new Exception(t('Please specify the language that you want to create'));
            }
            $localeID = $language;
            if ($noCountry) {
                if ($whyNoCountry === '' && !$iAmGlobalAdmin) {
                    throw new Exception(t('Please explain why this language should not be associated to a Country'));
                }
            } else {
                if ($country === '') {
                    throw new Exception(t('Please specify the Country associated to the language that you want to create'));
                }
                $localeID .= '_'.$country;
            }
            $locale = Locale::createForLocaleID($localeID);
            $already = $this->app->make('community_translation/locale')->find($locale->getID());
            if ($already !== null) {
                if ($already->isApproved()) {
                    throw new Exception(t("The language team for '%s' already exists", $already->getDisplayName()));
                } else {
                    throw new Exception(t("The language team for '%s' has already been requested", $already->getDisplayName()));
                }
            }
            $locale->setRequestedBy($me->getUserID());
            if ($approve) {
                $locale->setIsApproved(true);
            }
            $em = $this->app->make('community_translation/em');
            $em->persist($locale);
            $em->flush();
            if (!$approve) {
                try {
                    $this->app->make('community_translation/notify')->newLocaleRequested($locale, $noCountry ? $whyNoCountry : '');
                } catch (\Exceptipon $x) {
                }
            }
            $this->redirect('/teams', $approve ? 'created' : 'requested', $locale->getID());
        } catch (Exception $x) {
            $this->set('language', $language);
            $this->set('country', $country);
            $this->set('approve', $approve);
            $this->set('noCountry', $noCountry);
            $this->set('whyNoCountry', $whyNoCountry);
            $this->set('error', $x->getMessage());
            $this->view();
        }
    }
}
