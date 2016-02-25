<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Teams;

use Concrete\Core\Page\Controller\PageController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Concrete\Package\CommunityTranslation\Src\Service\Access;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

class Create extends PageController
{
    public function view()
    {
        $this->requireAsset('community_translation/common');
        $this->set('token', $this->app->make('helper/validation/token'));
        $me = new \User();
        if (!$me->isLoggedIn()) {
            $me = null;
        }
        if ($me === null) {
            $this->set('error', t('You need to sign-in in order to ask the creation of a new Translation Team'));
            $this->set('skip', true);
            return;
        }
        $this->set('me', $me);
        $iAmGlobalAdmin = $me ? ($me->getUserID() == USER_SUPER_ID || $me->inGroup($this->app->make('community_translation/groups')->getGlobalAdministrators())) : false;
        $this->set('canApprove', $iAmGlobalAdmin);
        
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
        $language = $this->post('language') ?: '';
        $country = $this->post('country') ?: '';
        $noCountry = $this->post('no-country') ? true : false;
        $approve = $this->post('approve') ? true : false;
        try {
            $token = $this->app->make('helper/validation/token');
            if (!$token->validate('comtra_create_locale')) {
                throw new Exception($token->getErrorMessage());
            }
            if (!$language) {
                throw new Exception(t('Please specify the language that you want to create'));
            }
            $localeID = $language;
            if (!$noCountry) {
                if (!$country) {
                    throw new Exception(t('Please specify the country associated to the language that you want to create'));
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
            throw new Exception('@todo');
        } catch (Exception $x) {
            $this->set('preselectLanguage', $language);
            $this->set('preselectCountry', $country);
            $this->set('precheckNoCountry', $noCountry);
            $this->set('precheckApprove', $approve);
            $this->set('error', $x->getMessage());
            $this->view();
        }
    }
}
