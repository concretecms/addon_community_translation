<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Block\TranslationTeamRequest;

use CommunityTranslation\Controller\BlockController;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Service\Access as AccessService;
use CommunityTranslation\Service\User as UserService;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\PostLoginLocation;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Gettext\Languages\Language as GettextLanguage;
use Punic\Comparer;
use Punic\Language as PunicLanguage;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    /**
     * Never ask the territory.
     *
     * @var int
     */
    private const TERRITORYREQUESTLEVEL_NEVER = 1;

    /**
     * Allow users to freely specify a territory.
     *
     * @var int
     */
    private const TERRITORYREQUESTLEVEL_OPTIONAL = 2;

    /**
     * Strongly suggest users to specify a territory.
     *
     * @var int
     */
    private const TERRITORYREQUESTLEVEL_NOTSOOPTIONAL = 3;

    /**
     * Always require users to specify a territory.
     *
     * @var int
     */
    private const TERRITORYREQUESTLEVEL_ALWAYS = 4;

    /**
     * @var int|string|null
     */
    public $territoryRequestLevel;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$helpers
     */
    protected $helpers = [];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btTable
     */
    protected $btTable = 'btCTTranslationTeamRequest';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 600;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 250;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockRecord
     */
    protected $btCacheBlockRecord = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutput
     */
    protected $btCacheBlockOutput = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputOnPost
     */
    protected $btCacheBlockOutputOnPost = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputForRegisteredUsers
     */
    protected $btCacheBlockOutputForRegisteredUsers = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputLifetime
     */
    protected $btCacheBlockOutputLifetime = 0; // 86400 = 1 day

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineEdit
     */
    protected $btSupportsInlineEdit = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btSupportsInlineAdd
     */
    protected $btSupportsInlineAdd = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeName()
     */
    public function getBlockTypeName()
    {
        return t('Translation team request');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getBlockTypeDescription()
     */
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
        $this->set('form', $this->app->make('helper/form'));
        $this->set('territoryRequestLevel', $this->territoryRequestLevel ? (int) $this->territoryRequestLevel : self::TERRITORYREQUESTLEVEL_OPTIONAL);
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
     * @see \Concrete\Core\Block\BlockController::registerViewAssets()
     */
    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('javascript', 'jquery');
    }

    public function view(): ?Response
    {
        return $this->action_language();
    }

    public function action_login(): ?Response
    {
        if ($this->getUserService()->isLoggedIn()) {
            return $this->buildRedirect([$this->getCollectionObject()]);
        }
        $this->app->make(PostLoginLocation::class)->setSessionPostLoginUrl($this->getCollectionObject());

        return $this->buildRedirect('/login');
    }

    public function action_language(): ?Response
    {
        if (!$this->startStep()) {
            return null;
        }
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
        $this->set('step', 'language');
        $language = $this->get('language');
        $language = is_string($language) ? $language : '';
        if ($language === '' || !isset($languages[$language])) {
            $languages = array_merge(['' => t('Please select')], $languages);
            $language = '';
        }
        $this->set('language', $language);
        $this->set('languages', $languages);

        return null;
    }

    public function action_language_set(): ?Response
    {
        if (!$this->startStep('comtra-ttr-language_set')) {
            return null;
        }
        $languageID = $this->request->request->get('language');
        $languageID = is_string($languageID) ? trim($languageID) : '';
        $language = $languageID === '' ? null : GettextLanguage::getById($languageID);
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));

            return $this->action_language();
        }
        $this->set('language', $language->id);
        if ((int) $this->territoryRequestLevel === self::TERRITORYREQUESTLEVEL_NEVER) {
            $err = $this->checkExistingLocale($language->id);
            if ($err !== '') {
                $this->set('showError', $err);

                return $this->action_language();
            }
            $this->set('territory', '');
            $this->preparePreviewStep();

            return null;
        }
        $ch = $this->app->make('helper/localization/countries');
        $localeRepo = $this->app->make(LocaleRepository::class);
        $sameLanguages = $localeRepo->
            createQueryBuilder('l')
                ->where('l.id LIKE :like')->setParameter('like', $language->id . '%')
                ->getQuery()->getResult();
        $countryNames = $ch->getCountries();
        $suggestedCountryCodes = $ch->getCountriesForLanguage($language->id);
        $existingLocales = [];
        foreach ($sameLanguages as $l) {
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
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('languageName', PunicLanguage::getName($language->id));
        $this->set('existingLocales', $existingLocales);
        $this->set('suggestedCountries', $suggestedCountries);
        $this->set('otherCountries', $otherCountries);
        $this->set('allowNoTerrory', (int) $this->territoryRequestLevel !== self::TERRITORYREQUESTLEVEL_ALWAYS);
        $this->set('step', 'territory');

        return null;
    }

    public function action_territory_set(): ?Response
    {
        if (!$this->startStep('comtra-ttr-territory_set')) {
            return null;
        }
        $languageID = $this->request->request->get('language');
        $languageID = is_string($languageID) ? trim($languageID) : '';
        $language = $languageID === '' ? null : GettextLanguage::getById($languageID);
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));

            return $this->action_language();
        }
        $this->set('language', $language->id);
        if ((int) $this->territoryRequestLevel !== self::TERRITORYREQUESTLEVEL_ALWAYS && $this->request->request->get('no-territory')) {
            $localeID = $language->id;
            $territory = '';
        } else {
            $territory = $this->request->request->get('territory');
            $territory = is_string($territory) ? trim($territory) : '';
            $ch = $this->app->make('helper/localization/countries');
            $countryNames = $ch->getCountries();
            if ($territory === '' || !isset($countryNames[$territory])) {
                $this->set('showError', t('Invalid Country received'));

                return $this->action_language();
            }
            $localeID = "{$language->id}_{$territory}";
        }
        $err = $this->checkExistingLocale($localeID);
        if ($err !== '') {
            $this->set('showError', $err);

            return $this->action_language();
        }
        $this->set('territory', $territory);
        $this->preparePreviewStep();

        return null;
    }

    public function action_submit(): ?Response
    {
        if (!$this->startStep('comtra-ttr-submit')) {
            return null;
        }
        $languageID = $this->request->request->get('language');
        $languageID = is_string($languageID) ? trim($languageID) : '';
        $language = $languageID === '' ? null : GettextLanguage::getById($languageID);
        if ($language === null) {
            $this->set('showError', t('Please select the language you would like to add'));

            return $this->action_language();
        }
        $approve = false;
        if ($this->getAccessService()->getLocaleAccess('') >= AccessService::GLOBAL_ADMIN) {
            if ($this->request->request->get('approve')) {
                $approve = true;
            }
        }
        $notes = '';
        $territory = $this->request->request->get('territory');
        $territory = is_string($territory) ? trim($territory) : '';
        if ($territory === '') {
            $localeID = $language->id;
            switch ((int) $this->territoryRequestLevel) {
                case self::TERRITORYREQUESTLEVEL_ALWAYS:
                    $this->set('showError', t('Invalid Country received'));

                    return $this->action_language();
                case self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL:
                    if (!$approve) {
                        $notes = $this->request->request->get('notes');
                        $notes = is_string($notes) ? trim($notes) : '';
                        if ($notes === '') {
                            $this->set('showError', t('Please specify why you need to create a language without an associated Country'));

                            return $this->action_language();
                        }
                    }
                    break;
            }
        } else {
            switch ((int) $this->territoryRequestLevel) {
                case self::TERRITORYREQUESTLEVEL_NEVER:
                    $this->set('showError', t('Invalid Country received'));

                    return $this->action_language();
            }
            $ch = $this->app->make('helper/localization/countries');
            $countryNames = $ch->getCountries();
            if (!isset($countryNames[$territory])) {
                $this->set('showError', t('Invalid Country received'));

                return $this->action_language();
            }
            $localeID = "{$language->id}_{$territory}";
        }
        $err = $this->checkExistingLocale($localeID);
        if ($err !== '') {
            $this->set('showError', $err);

            return $this->action_language();
        }
        try {
            $locale = $this->createLocale($localeID, $notes, $approve);
        } catch (UserMessageException $x) {
            $this->set('showError', $err);

            return $this->action_language();
        }
        $this->set('step', 'submitted');
        $this->set('urlResolver', $this->app->make(ResolverManagerInterface::class));
        $this->set('localeID', $localeID);
        $this->set('localeName', $locale->getDisplayName());
        $this->set('approved', $approve);

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Controller\BlockController::normalizeArgs()
     */
    protected function normalizeArgs(array $args)
    {
        $error = $this->app->make('helper/validation/error');
        $normalized = [
            'territoryRequestLevel' => null,
        ];
        if (is_numeric($args['territoryRequestLevel'] ?? null)) {
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
     * @see \CommunityTranslation\Controller\BlockController::isControllerTaskInstanceSpecific()
     */
    protected function isControllerTaskInstanceSpecific(string $method): bool
    {
        return true;
    }

    private function preparePreviewStep(): void
    {
        $this->set('step', 'preview');
        $localeID = $this->get('language');
        $territory = (string) $this->get('territory');
        if ($territory !== '') {
            $localeID .= '_' . $territory;
        }
        $this->set('localeID', $localeID);
        $this->set('localeName', PunicLanguage::getName($localeID));
        if ($this->getAccessService()->getLocaleAccess($localeID) === AccessService::GLOBAL_ADMIN) {
            $this->set('askApprove', true);
            $this->set('askWhyNoCountry', false);
        } else {
            $this->set('askApprove', false);
            if ($territory === '' && (int) $this->territoryRequestLevel === self::TERRITORYREQUESTLEVEL_NOTSOOPTIONAL) {
                $this->set('askWhyNoCountry', true);
            } else {
                $this->set('askWhyNoCountry', false);
            }
        }
    }

    private function checkExistingLocale(string $localeID): string
    {
        $localeRepo = $this->app->make(LocaleRepository::class);
        $existing = $localeRepo->find($localeID);
        if ($existing === null) {
            return '';
        }
        if ($existing->isSource()) {
            return t("There couldn't be a language team for '%s' since it's the source language", $existing->getDisplayName());
        }
        if ($existing->isApproved()) {
            return t("The language team for '%s' already exists", $existing->getDisplayName());
        }
        if ($existing->getRequestedOn() !== null) {
            return t(
                "The language team for '%1\$s' has already been requested on %2\$s",
                $existing->getDisplayName(),
                $this->app->make('date')->formatDateTime($existing->getRequestedOn(), true)
            );
        }

        return t("The language team for '%s' has already been requested", $existing->getDisplayName());
    }

    private function createLocale(string $localeID, string $notes, bool $approve): LocaleEntity
    {
        $locale = new LocaleEntity($localeID);
        $locale
            ->setRequestedBy($this->getUserService()->getUserEntity(UserService::CURRENT_USER_KEY))
            ->setRequestedOn(new DateTimeImmutable())
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

    private function startStep(string $checkToken = ''): bool
    {
        if (!$this->getUserService()->isLoggedIn()) {
            $this->render('view.notloggedin');

            return false;
        }
        $token = $this->app->make('token');
        if ($checkToken !== '' && !$token->validate($checkToken)) {
            $this->set('showError', $token->getErrorMessage());
            $this->action_language();

            return false;
        }
        $this->set('token', $token);
        $this->set('form', $this->app->make('helper/form'));

        return true;
    }
}
