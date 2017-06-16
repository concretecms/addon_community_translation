<?php

namespace Concrete\Package\CommunityTranslation\Block\TranslationTeamRequest;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access;
use Concrete\Core\Error\UserMessageException;
use DateTime;
use Doctrine\ORM\EntityManager;
use Gettext\Languages\Language as GettextLanguage;
use Punic\Comparer;
use Punic\Language as PunicLanguage;

class Controller extends BlockController
{
    /**
     * Never ask the territory.
     *
     * @var int
     */
    const TERRITORYREQUESTLEVEL_NEVER = 1;

    /**
     * Allow users to freely specify a territory.
     *
     * @var int
     */
    const TERRITORYREQUESTLEVEL_OPTIONAL = 2;

    /**
     * Strongly suggest users to specify a territory.
     *
     * @var int
     */
    const TERRITORYREQUESTLEVEL_NOTSOOPTIONAL = 3;

    /**
     * Always require users to specify a territory.
     *
     * @var int
     */
    const TERRITORYREQUESTLEVEL_ALWAYS = 4;

    public $helpers = [];

    protected $btTable = 'btCTTranslationTeamRequest';

    protected $btInterfaceWidth = 600;
    protected $btInterfaceHeight = 200;

    protected $btCacheBlockRecord = true;
    protected $btCacheBlockOutput = false;
    protected $btCacheBlockOutputOnPost = false;
    protected $btCacheBlockOutputForRegisteredUsers = false;

    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    protected $btSupportsInlineEdit = false;
    protected $btSupportsInlineAdd = false;

    public $territoryRequestLevel;

    public function getBlockTypeName()
    {
        return t('Translation team request');
    }

    public function getBlockTypeDescription()
    {
        return t('Allow users to ask the creation of a new translation team.');
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->set('territoryRequestLevel', $this->territoryRequestLevel ?: self::TERRITORYREQUESTLEVEL_OPTIONAL);
        $this->set('territoryRequestLevels', [
            self::TERRITORYREQUESTLEVEL_NEVER => t('Never ask for a territory'),
            self::TERRITORYREQUESTLEVEL_OPTIONAL => t('Users may specify a territory'),
            self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL => t('Users are strongly encouraged to specify a territory'),
            self::TERRITORYREQUESTLEVEL_ALWAYS => t('Users must specify a territory'),
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [];
        $normalized['territoryRequestLevel'] = null;
        if (isset($args['territoryRequestLevel']) && (is_int($args['territoryRequestLevel']) || (is_string($args['territoryRequestLevel']) && is_numeric($args['territoryRequestLevel'])))) {
            $i = (int) $args['territoryRequestLevel'];
            switch ($i) {
                case self::TERRITORYREQUESTLEVEL_NEVER:
                case self::TERRITORYREQUESTLEVEL_OPTIONAL:
                case self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL:
                case self::TERRITORYREQUESTLEVEL_ALWAYS:
                    $normalized['territoryRequestLevel'] = $i;
                    break;
            }
        }
        if ($normalized['territoryRequestLevel'] === null) {
            $error->add(t('Please specify if and how the territory should be required'));
        }

        return $error->has() ? $error : $normalized;
    }

    /**
     * {@inheritdoc}
     *
     * @see BlockController::isControllerTaskInstanceSpecific($method)
     */
    protected function isControllerTaskInstanceSpecific($method)
    {
        return true;
    }

    /**
     * @param null|string $checkToken
     *
     * @return bool
     */
    private function startStep($checkToken = null)
    {
        if ($this->getAccess()->isLoggedIn()) {
            $result = true;
            $token = $this->app->make('token');
            if ($checkToken !== null && !$token->validate($checkToken)) {
                $this->set('showError', $token->getErrorMessage());
                $this->action_language();
                $result = false;
            } else {
                $this->set('token', $this->app->make('token'));
                $this->set('form', $this->app->make('helper/form'));
            }
        } else {
            $result = false;
            $this->set('step', null);
            $this->set('showError', t('You need to sign-in in order to ask the creation of a new Translation Team'));
        }

        return $result;
    }

    public function view()
    {
        $this->action_language();
    }

    public function action_language()
    {
        if (!$this->startStep()) {
            return;
        }
        $this->set('step', 'language');
        $languages = [];
        $punicLanguages = PunicLanguage::getAll(true, true);
        foreach (GettextLanguage::getAll() as $l) {
            if (strpos($l->id, '_') === false) {
                if (isset($punicLanguages[$l->id])) {
                    $languages[$l->id] = $punicLanguages[$l->id];
                }
            }
        }
        (new Comparer())->sort($languages, true);
        $language = $this->get('language');
        if (!$language || !isset($languages[$language])) {
            $languages = array_merge(['' => t('Please select')], $languages);
            $this->set('language', '');
        }
        $this->set('languages', $languages);
    }

    public function action_language_set()
    {
        if (!$this->startStep('comtra-ttr-language_set')) {
            return;
        }
        $language = GettextLanguage::getById((string) $this->request->request->get('language'));
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));
            $this->action_language();

            return;
        }
        $this->set('language', $language->id);
        if ($this->territoryRequestLevel == self::TERRITORYREQUESTLEVEL_NEVER) {
            $err = $this->checkExistingLocale($language->id);
            if ($err !== null) {
                $this->set('showError', $err);
                $this->action_language();

                return;
            }
            $this->set('territory', '');
            $this->preparePreviewStep();

            return;
        }
        $ch = $this->app->make('helper/localization/countries');
        /* @var \Concrete\Core\Localization\Service\CountryList $ch */
        $localeRepo = $this->app->make(LocaleRepository::class);
        /* @var LocaleRepository $localeRepo */
        $sameLanguages = $localeRepo->
            createQueryBuilder('l')
                ->where('l.id LIKE :like')->setParameter('like', $language->id . '%')
                ->getQuery()->getResult();
        $countryNames = $ch->getCountries();
        $suggestedCountryCodes = $ch->getCountriesForLanguage($language->id);
        $existingLocales = [];
        foreach ($sameLanguages as $l) {
            /* @var \CommunityTranslation\Entity\Locale $l */
            $existingLocales[$l->getID()] = $l->getDisplayName();
            $chunks = explode('_', $l->getID());
            if (isset($chunks[1])) {
                $c = $chunks[1];
                unset($countryNames[$c]);
                $p = array_search($c, $suggestedCountryCodes);
                if ($p !== false) {
                    unset($suggestedCountryCodes[$p]);
                }
            }
        }
        $suggestedCountries = [];
        foreach ($suggestedCountryCodes as $c) {
            $suggestedCountries[$c] = $countryNames[$c];
        }
        $otherCountries = [];
        foreach ($countryNames as $c => $n) {
            if (!isset($suggestedCountries[$c])) {
                $otherCountries[$c] = $n;
            }
        }
        $this->set('languageName', PunicLanguage::getName($language->id));
        $this->set('existingLocales', $existingLocales);
        $this->set('suggestedCountries', $suggestedCountries);
        $this->set('otherCountries', $otherCountries);
        $this->set('allowNoTerrory', $this->territoryRequestLevel != self::TERRITORYREQUESTLEVEL_ALWAYS);
        $this->set('step', 'territory');
    }

    public function action_territory_set()
    {
        if (!$this->startStep('comtra-ttr-territory_set')) {
            return;
        }
        $language = GettextLanguage::getById((string) $this->request->request->get('language'));
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));
            $this->action_language();

            return;
        }
        $this->set('language', $language->id);
        if ($this->territoryRequestLevel != self::TERRITORYREQUESTLEVEL_ALWAYS && $this->request->request->get('noTerritory')) {
            $localeID = $language->id;
            $territory = '';
        } else {
            $territory = (string) $this->request->request->get('territory');
            $ch = $this->app->make('helper/localization/countries');
            /* @var \Concrete\Core\Localization\Service\CountryList $ch */
            $countryNames = $ch->getCountries();
            if (!isset($countryNames[$territory])) {
                $this->set('showError', t('Invalid Country received'));
                $this->action_language();

                return;
            }
            $localeID = $language->id . '_' . $territory;
        }
        $err = $this->checkExistingLocale($localeID);
        if ($err !== null) {
            $this->set('showError', $err);
            $this->action_language();

            return;
        }
        $this->set('territory', $territory);
        $this->preparePreviewStep();
    }

    public function action_submit()
    {
        if (!$this->startStep('comtra-ttr-submit')) {
            return;
        }
        $language = GettextLanguage::getById((string) $this->request->request->get('language'));
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));
            $this->action_language();

            return;
        }
        $approve = false;
        if ($this->getAccess()->getLocaleAccess('') >= Access::GLOBAL_ADMIN) {
            if ($this->request->request->get('approve')) {
                $approve = true;
            }
        }
        $notes = '';
        $territory = (string) $this->request->request->get('territory');
        if ($territory === '') {
            $localeID = $language->id;
            switch ($this->territoryRequestLevel) {
                case self::TERRITORYREQUESTLEVEL_ALWAYS:
                    $this->set('showError', t('Invalid Country received'));
                    $this->action_language();

                    return;
                case self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL:
                    if (!$approve) {
                        $notes = $this->request->request->get('notes');
                        $notes = is_string($notes) ? trim($notes) : '';
                        if ($notes === '') {
                            $this->set('showError', t('Please specify why you need to create a language without an associated Country'));
                            $this->action_language();

                            return;
                        }
                    }
                    break;
            }
        } else {
            switch ($this->territoryRequestLevel) {
                case self::TERRITORYREQUESTLEVEL_NEVER:
                    $this->set('showError', t('Invalid Country received'));
                    $this->action_language();

                    return;
            }
            $ch = $this->app->make('helper/localization/countries');
            /* @var \Concrete\Core\Localization\Service\CountryList $ch */
            $countryNames = $ch->getCountries();
            if (!isset($countryNames[$territory])) {
                $this->set('showError', t('Invalid Country received'));
                $this->action_language();

                return;
            }
            $localeID = $language->id . '_' . $territory;
        }
        $err = $this->checkExistingLocale($localeID);
        if ($err !== null) {
            $this->set('showError', $err);
            $this->action_language();

            return;
        }
        try {
            $locale = $this->createLocale($localeID, $notes, $approve);
        } catch (UserMessageException $x) {
            $this->set('showError', $err);
            $this->action_language();

            return;
        }
        $this->set('step', 'submitted');
        $this->set('localeID', $localeID);
        $this->set('localeName', $locale->getDisplayName());
        $this->set('approved', $approve);
    }

    private function preparePreviewStep()
    {
        $this->set('step', 'preview');
        $localeID = $this->get('language');
        $territory = (string) $this->get('territory');
        if ($territory !== '') {
            $localeID .= '_' . $territory;
        }
        $this->set('localeID', $localeID);
        $this->set('localeName', PunicLanguage::getName($localeID));
        if ($this->getAccess()->getLocaleAccess($localeID) === Access::GLOBAL_ADMIN) {
            $this->set('askApprove', true);
            $this->set('askWhyNoCountry', false);
        } else {
            $this->set('askApprove', false);
            if ($territory === '' && $this->territoryRequestLevel == self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL) {
                $this->set('askWhyNoCountry', true);
            } else {
                $this->set('askWhyNoCountry', false);
            }
        }
    }

    /**
     * @param string $localeID
     *
     * @return string|null
     */
    private function checkExistingLocale($localeID)
    {
        $result = null;
        $localeRepo = $this->app->make(LocaleRepository::class);
        $existing = $localeRepo->find($localeID);
        if ($existing !== null) {
            if ($existing->isSource()) {
                $result = t("There couldn't be a language team for '%s' since it's the source language", $existing->getDisplayName());
            } elseif ($existing->isApproved()) {
                $result = t("The language team for '%s' already exists", $existing->getDisplayName());
            } elseif ($existing->getRequestedOn() !== null) {
                $result = t(
                    "The language team for '%1\$s' has already been requested on %2\$s",
                    $existing->getDisplayName(),
                    $this->app->make('date')->formatDateTime($existing->getRequestedOn(), true)
                );
            } else {
                $result = t("The language team for '%s' has already been requested", $existing->getDisplayName());
            }
        }

        return $result;
    }

    /**
     * @param string $localeID
     * @param string $notes
     * @param bool $approve
     *
     * @return LocaleEntity
     */
    private function createLocale($localeID, $notes, $approve)
    {
        $locale = LocaleEntity::create($localeID)
            ->setRequestedBy($this->getAccess()->getUserEntity('current'))
            ->setRequestedOn(new DateTime())
            ->setIsApproved($approve)
        ;
        $em = $this->app->make(EntityManager::class);
        $em->persist($locale);
        $em->flush($locale);
        if (!$approve) {
            $this->app->make(NotificationRepository::class)->newLocaleRequested($locale, $notes);
        }

        return $locale;
    }
}
