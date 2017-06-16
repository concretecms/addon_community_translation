<?php

namespace CommunityTranslation\Api;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\IPControlLog;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Http\Request;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\User;
use Concrete\Core\User\UserList;
use DateTime;
use Doctrine\ORM\EntityManager;

class UserControl
{
    const ACCESSOPTION_EVERYBODY = 'everybody';
    const ACCESSOPTION_REGISTEREDUSERS = 'registered-users';
    const ACCESSOPTION_TRANSLATORS = 'translators';
    const ACCESSOPTION_TRANSLATORS_ALLLOCALES = 'translators-all-locales';
    const ACCESSOPTION_TRANSLATORS_OWNLOCALES = 'translators-own-locales';
    const ACCESSOPTION_LOCALEADMINS = 'localeadmins';
    const ACCESSOPTION_LOCALEADMINS_ALLLOCALES = 'localeadmins-all-locales';
    const ACCESSOPTION_LOCALEADMINS_OWNLOCALES = 'localeadmins-own-locales';
    const ACCESSOPTION_GLOBALADMINS = 'globaladmins';
    const ACCESSOPTION_SITEADMINS = 'siteadmins';
    const ACCESSOPTION_ROOT = 'root';
    const ACCESSOPTION_NOBODY = 'nobody';

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Application $app
     * @param Request $request
     */
    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
    }

    /**
     * @var Access|null
     */
    private $accessHelper = null;

    /**
     * @return Access
     */
    protected function getAccessHelper()
    {
        if ($this->accessHelper === null) {
            $this->accessHelper = $this->app->make(Access::class);
        }

        return $this->accessHelper;
    }

    /**
     * @var \CommunityTranslation\Entity\Locale[]|null
     */
    private $approvedLocales = null;

    /**
     * @return \CommunityTranslation\Entity\Locale[]
     */
    protected function getApprovedLocales()
    {
        if ($this->approvedLocales === null) {
            $this->approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        }

        return $this->approvedLocales;
    }

    /**
     * @var string|null
     */
    private $requestApiToken = null;

    /**
     * @return string
     */
    public function getRequestApiToken()
    {
        if ($this->requestApiToken === null) {
            $requestApiToken = '';
            if ($this->request->headers !== null) {
                if ($this->request->headers->has('API-Token')) {
                    $requestApiToken = $this->request->headers->get('API-Token');
                    if (!is_string($requestApiToken)) {
                        $requestApiToken = '';
                    }
                }
            }
            $this->requestApiToken = $requestApiToken;
        }

        return $this->requestApiToken;
    }

    /**
     * @var User|string|null
     */
    private $requestUser = null;

    /**
     * @throws AccessDeniedException
     *
     * @return User
     */
    public function getRequestUser()
    {
        if ($this->requestUser === null) {
            $token = $this->getRequestApiToken();
            if ($token === '') {
                $this->requestUser = 'no-token';
            } else {
                $ip = $this->app->make('ip');
                if ($ip->isBanned()) {
                    $this->requestUser = 'banned';
                } else {
                    $requestUser = null;
                    $list = new UserList();
                    $list->disableAutomaticSorting();
                    $list->filterByAttribute('api_token', $token);
                    $ids = $list->getResultIDs();
                    if (!empty($ids)) {
                        $u = \User::getByUserID($ids[0]);
                        if ($u->isRegistered()) {
                            $requestUser = $u;
                        }
                    }
                    if ($requestUser === null) {
                        $this->requestUser == 'invalid-token';
                        $ip->logSignupRequest();
                        if ($ip->signupRequestThreshholdReached()) {
                            $ip->createIPBan();
                        }
                    } else {
                        $this->requestUser = $requestUser;
                    }
                }
            }
        }
        if ($this->requestUser === 'no-token') {
            throw AccessDeniedException::create(t('API Access Token required'));
        }
        if ($this->requestUser === 'banned') {
            throw AccessDeniedException::create($this->app->make('ip')->getErrorMessage());
        }
        if ($this->requestUser === 'invalid-token') {
            throw AccessDeniedException::create(t('Bad API Access Token'));
        }

        return $this->requestUser;
    }

    /**
     * @return UserEntity
     */
    public function getAssociatedUserEntity()
    {
        try {
            $uID = $this->getRequestUser()->getUserID();
        } catch (AccessDeniedException $x) {
            $uID = USER_SUPER_ID;
        }

        return $this->app->make(EntityManager::class)->find(UserEntity::class, $uID);
    }

    /**
     * @param string $configKey
     *
     * @throws AccessDeniedException
     */
    public function checkGenericAccess($configKey)
    {
        $config = $this->app->make('community_translation/config');
        $level = $config->get('options.api.access.' . $configKey);
        switch ($level) {
            case self::ACCESSOPTION_EVERYBODY:
                return;
            case self::ACCESSOPTION_REGISTEREDUSERS:
                $this->getRequestUser();

                return;
            case self::ACCESSOPTION_TRANSLATORS:
                $requiredLevel = Access::TRANSLATE;
                break;
            case self::ACCESSOPTION_LOCALEADMINS:
                $requiredLevel = Access::ADMIN;
                break;
            case self::ACCESSOPTION_GLOBALADMINS:
                $requiredLevel = Access::GLOBAL_ADMIN;
                break;
            case self::ACCESSOPTION_SITEADMINS:
                $user = $this->getRequestUser();
                if ($user->getUserID() != USER_SUPER_ID) {
                    $admins = Group::getByID(ADMIN_GROUP_ID);
                    if (!$admins || !$user->inGroup($admins)) {
                        throw AccessDeniedException::create();
                    }
                }

                return;
            case self::ACCESSOPTION_ROOT:
                $user = $this->getRequestUser();
                if ($user->getUserID() != USER_SUPER_ID) {
                    throw AccessDeniedException::create();
                }

                return;
            case self::ACCESSOPTION_NOBODY:
            default:
                throw AccessDeniedException::create();
        }
        $user = $this->getRequestUser();
        $hasRequiredLevel = false;
        foreach ($this->getApprovedLocales() as $locale) {
            if ($this->getAccessHelper()->getLocaleAccess($locale, $user) >= $requiredLevel) {
                $hasRequiredLevel = true;
                break;
            } elseif ($requiredLevel >= Access::GLOBAL_ADMIN) {
                break;
            }
        }
        if ($hasRequiredLevel !== true) {
            throw AccessDeniedException::create();
        }
    }

    /**
     * @param string $configKey
     *
     * @throws AccessDeniedException
     *
     * @return \CommunityTranslation\Entity\Locale[]
     */
    public function checkLocaleAccess($configKey)
    {
        $config = $this->app->make('community_translation/config');
        $level = $config->get('options.api.access.' . $configKey);
        switch ($level) {
            case self::ACCESSOPTION_EVERYBODY:
                return $this->getApprovedLocales();
            case self::ACCESSOPTION_REGISTEREDUSERS:
                $this->getRequestUser();

                return $this->getApprovedLocales();
            case self::ACCESSOPTION_TRANSLATORS_ALLLOCALES:
                $requiredLevel = Access::TRANSLATE;
                $ownLocalesOnly = false;
                break;
            case self::ACCESSOPTION_TRANSLATORS_OWNLOCALES:
                $requiredLevel = Access::TRANSLATE;
                $ownLocalesOnly = true;
                break;
            case self::ACCESSOPTION_LOCALEADMINS_ALLLOCALES:
                $requiredLevel = Access::ADMIN;
                $ownLocalesOnly = false;
                break;
            case self::ACCESSOPTION_LOCALEADMINS_OWNLOCALES:
                $requiredLevel = Access::ADMIN;
                $ownLocalesOnly = true;
                break;
            case self::ACCESSOPTION_GLOBALADMINS:
                $requiredLevel = Access::GLOBAL_ADMIN;
                $ownLocalesOnly = false;
                break;
            case self::ACCESSOPTION_SITEADMINS:
                $user = $this->getRequestUser();
                if ($user->getUserID() != USER_SUPER_ID) {
                    $admins = Group::getByID(ADMIN_GROUP_ID);
                    if (!$admins || !$user->inGroup($admins)) {
                        throw AccessDeniedException::create();
                    }
                }

                return;
            case self::ACCESSOPTION_ROOT:
                $user = $this->getRequestUser();
                if ($user->getUserID() != USER_SUPER_ID) {
                    throw AccessDeniedException::create();
                }

                return;
            case self::ACCESSOPTION_NOBODY:
            default:
                throw AccessDeniedException::create();
        }
        $user = $this->getRequestUser();
        $ownLocales = [];
        foreach ($this->getApprovedLocales() as $locale) {
            if ($this->getAccessHelper()->getLocaleAccess($locale, $user) >= $requiredLevel) {
                $ownLocales[] = $locale;
                if ($ownLocalesOnly === false) {
                    break;
                }
            } elseif ($requiredLevel >= Access::GLOBAL_ADMIN) {
                break;
            }
        }
        if (empty($ownLocales)) {
            throw AccessDeniedException::create();
        }

        return $ownLocalesOnly ? $ownLocales : $this->getApprovedLocales();
    }

    /**
     * Returns the defined rate limit (if set).
     *
     * @return int[]|null First item is the max requests, second limit is the time window. If no rate limit is defined returns null.
     */
    public function getRateLimit()
    {
        $result = null;
        $config = $this->app->make('community_translation/config');
        $maxRequests = (int) $config->get('options.api.rateLimit.maxRequests');
        if ($maxRequests > 0) {
            $timeWindow = (int) $config->get('options.api.rateLimit.timeWindow');
            if ($timeWindow > 0) {
                $result = [$maxRequests, $timeWindow];
            }
        }

        return $result;
    }

    /**
     * Get the number of visits from the current IP address since a determined number of seconds ago.
     *
     * @param int $timeWindow
     *
     * @return int
     */
    public function getVisitsCountFromCurrentIP($timeWindow)
    {
        $timeWindow = (int) $timeWindow;
        $ipControlLog = $this->app->make(IPControlLog::class);

        return $ipControlLog->countVisits('api', new DateTime("-$timeWindow seconds"));
    }

    /**
     * Check if the API Rate limit has been reached.
     *
     * @throws UserException
     */
    public function checkRateLimit()
    {
        $rateLimit = $this->getRateLimit();
        if ($rateLimit !== null) {
            list($maxRequests, $timeWindow) = $rateLimit;
            $visits = $this->getVisitsCountFromCurrentIP($timeWindow);
            if ($visits >= $maxRequests) {
                throw new UserException(t('You reached the API rate limit (%1$s requests every %2$s seconds)', $maxRequests, $timeWindow));
            }
            $this->app->make(IPControlLog::class)->addVisit('api');
        }
    }
}
