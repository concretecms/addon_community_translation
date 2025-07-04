<?php

declare(strict_types=1);

namespace CommunityTranslation\Api;

use CommunityTranslation\Api\Jwt\SignedWithOidc;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\Access;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Entity\Permission\IpAccessControlCategory;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\Request;
use Concrete\Core\Permission\IpAccessControlService;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use Concrete\Core\User\UserList;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Exception as JwtException;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Lcobucci\JWT\Validation\Validator;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class UserControl
{
    public const ACCESSOPTION_EVERYBODY = 'everybody';

    public const ACCESSOPTION_REGISTEREDUSERS = 'registered-users';

    public const ACCESSOPTION_TRANSLATORS = 'translators';

    public const ACCESSOPTION_TRANSLATORS_ALLLOCALES = 'translators-all-locales';

    public const ACCESSOPTION_TRANSLATORS_OWNLOCALES = 'translators-own-locales';

    public const ACCESSOPTION_LOCALEADMINS = 'localeadmins';

    public const ACCESSOPTION_LOCALEADMINS_ALLLOCALES = 'localeadmins-all-locales';

    public const ACCESSOPTION_LOCALEADMINS_OWNLOCALES = 'localeadmins-own-locales';

    public const ACCESSOPTION_GLOBALADMINS = 'globaladmins';

    public const ACCESSOPTION_SITEADMINS = 'siteadmins';

    public const ACCESSOPTION_MARKET = 'market';

    public const ACCESSOPTION_ROOT = 'root';

    public const ACCESSOPTION_NOBODY = 'nobody';

    private const REQUESTUSER_NO_TOKEN = 'no-token';

    private const REQUESTUSER_BANNED = 'banned';

    private const REQUESTUSER_INVALID_TOKEN = 'invalid-token';

    private Application $app;

    private Request $request;

    private ?Access $accessService = null;

    /**
     * @var \CommunityTranslation\Entity\Locale[]|null
     */
    private ?array $approvedLocales = null;

    private ?string $requestApiToken = null;

    private ?IpAccessControlService $ipAccessControlAccess = null;

    private ?IpAccessControlService $ipAccessControlRateLimit = null;

    /**
     * @var \Concrete\Core\User\User|string|null
     */
    private $requestUser;

    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
    }

    /**
     * @throws \CommunityTranslation\Api\AccessDeniedException
     */
    public function checkGenericAccess(string $configKey): void
    {
        $level = $this->getConfiguredAccessLevel($configKey);
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
                if (!$user->isSuperUser()) {
                    $admins = $this->app->make(GroupRepository::class)->getGroupByID(ADMIN_GROUP_ID);
                    if (!$admins || !$user->inGroup($admins)) {
                        throw new AccessDeniedException();
                    }
                }

                return;
            case self::ACCESSOPTION_MARKET:
                $this->validateMarketToken();

                return;
            case self::ACCESSOPTION_ROOT:
                $user = $this->getRequestUser();
                if (!$user->isSuperUser()) {
                    throw new AccessDeniedException();
                }

                return;
            case self::ACCESSOPTION_NOBODY:
                throw new AccessDeniedException();
            default:
                throw new RuntimeException("The API access control for {$configKey} is not configured correctly");
        }
        $user = $this->getRequestUser();
        $hasRequiredLevel = false;
        foreach ($this->getApprovedLocales() as $locale) {
            if ($this->getAccessService()->getLocaleAccess($locale, $user) >= $requiredLevel) {
                $hasRequiredLevel = true;
                break;
            }
            if ($requiredLevel >= Access::GLOBAL_ADMIN) {
                break;
            }
        }
        if ($hasRequiredLevel !== true) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @throws \CommunityTranslation\Api\AccessDeniedException
     *
     * @return \CommunityTranslation\Entity\Locale[]
     */
    public function checkLocaleAccess(string $configKey): array
    {
        $level = $this->getConfiguredAccessLevel($configKey);
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
                if (!$user->isSuperUser()) {
                    $admins = $this->app->make(GroupRepository::class)->getGroupByID(ADMIN_GROUP_ID);
                    if (!$admins || !$user->inGroup($admins)) {
                        throw new AccessDeniedException();
                    }
                }

                return $this->getApprovedLocales();
            case self::ACCESSOPTION_MARKET:
                throw new AccessDeniedException();
            case self::ACCESSOPTION_ROOT:
                $user = $this->getRequestUser();
                if (!$user->isSuperUser()) {
                    throw new AccessDeniedException();
                }

                return $this->getApprovedLocales();
            case self::ACCESSOPTION_NOBODY:
            default:
                throw new AccessDeniedException();
        }
        $user = $this->getRequestUser();
        $approvedLocales = $this->getApprovedLocales();
        $ownLocales = [];
        foreach ($approvedLocales as $locale) {
            if ($this->getAccessService()->getLocaleAccess($locale, $user) >= $requiredLevel) {
                if ($ownLocalesOnly === false) {
                    return $approvedLocales;
                }
                $ownLocales[] = $locale;
            } elseif ($requiredLevel >= Access::GLOBAL_ADMIN) {
                break;
            }
        }
        if ($ownLocales === []) {
            throw new AccessDeniedException();
        }

        return $ownLocales;
    }

    /**
     * Get the user entity to be associated with imported translations.
     * It may be the current request's user (if available), or the super user otherwise.
     */
    public function getAssociatedUserEntity(): UserEntity
    {
        try {
            $uID = (int) $this->getRequestUser()->getUserID();
        } catch (AccessDeniedException $x) {
            $uID = USER_SUPER_ID;
        }

        return $this->app->make(EntityManager::class)->find(UserEntity::class, $uID);
    }

    public function getIpAccessControlRateLimit(): IpAccessControlService
    {
        if ($this->ipAccessControlRateLimit === null) {
            $this->ipAccessControlRateLimit = $this->buildIpAccessControlService('community_translation_api_ratelimit');
        }

        return $this->ipAccessControlRateLimit;
    }

    /**
     * Check if the API Rate limit has been reached.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function checkRateLimit(): void
    {
        $rateLimit = $this->getIpAccessControlRateLimit();
        $range = $rateLimit->getRange();
        if ($range !== null) {
            if ($range->getType() & IpAccessControlService::IPRANGEFLAG_WHITELIST) {
                return;
            }
            if ($range->getType() === IpAccessControlService::IPRANGETYPE_BLACKLIST_AUTOMATIC) {
                throw new UserMessageException(t('You reached the API rate limit (%s)', $rateLimit->getCategory()->describeTimeWindow()), Response::HTTP_TOO_MANY_REQUESTS);
            }
            throw new UserMessageException(t('You have been blocked'));
        }
        if ($rateLimit->isThresholdReached()) {
            $rateLimit->addToDenylistForThresholdReached();
            throw new UserMessageException(t('You reached the API rate limit (%s)', $rateLimit->getCategory()->describeTimeWindow()), Response::HTTP_TOO_MANY_REQUESTS);
        }
        $rateLimit->registerEvent();
    }

    private function getAccessService(): Access
    {
        if ($this->accessService === null) {
            $this->accessService = $this->app->make(Access::class);
        }

        return $this->accessService;
    }

    /**
     * @return \CommunityTranslation\Entity\Locale[]
     */
    private function getApprovedLocales(): array
    {
        if ($this->approvedLocales === null) {
            $this->approvedLocales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        }

        return $this->approvedLocales;
    }

    private function getRequestApiToken(): string
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

    private function buildIpAccessControlService(string $handle): IpAccessControlService
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $repo = $em->getRepository(IpAccessControlCategory::class);
        $category = $repo->findOneBy(['handle' => $handle]);

        return $this->app->make(IpAccessControlService::class, ['category' => $category]);
    }

    private function getIpAccessControlAccess(): IpAccessControlService
    {
        if ($this->ipAccessControlAccess === null) {
            $this->ipAccessControlAccess = $this->buildIpAccessControlService('community_translation_api_access');
        }

        return $this->ipAccessControlAccess;
    }

    /**
     * @throws \CommunityTranslation\Api\AccessDeniedException
     */
    private function getRequestUser(): User
    {
        if ($this->requestUser === null) {
            $this->requestUser = $this->resolveRequestUser();
        }
        if ($this->requestUser === self::REQUESTUSER_NO_TOKEN) {
            throw new AccessDeniedException(t('API Access Token required'));
        }
        if ($this->requestUser === self::REQUESTUSER_BANNED) {
            throw new AccessDeniedException($this->getIpAccessControlAccess()->getErrorMessage());
        }
        if ($this->requestUser === self::REQUESTUSER_INVALID_TOKEN) {
            throw new AccessDeniedException(t('Bad API Access Token'));
        }

        return $this->requestUser;
    }

    /**
     * @return \Concrete\Core\User\User|string
     */
    private function resolveRequestUser()
    {
        $token = $this->getRequestApiToken();
        if ($token === '') {
            return self::REQUESTUSER_NO_TOKEN;
        }
        $ipAccessControlAccess = $this->getIpAccessControlAccess();
        $ipAccessControlRange = $ipAccessControlAccess->getRange();
        if ($ipAccessControlRange !== null && ($ipAccessControlRange->getType() && IpAccessControlService::IPRANGEFLAG_BLACKLIST)) {
            return self::REQUESTUSER_BANNED;
        }
        $requestUser = null;
        $list = new UserList();
        $list->disableAutomaticSorting();
        $list->filterByAttribute('api_token', $token);
        $ids = $list->getResultIDs();
        if (!empty($ids)) {
            $u = User::getByUserID((int) $ids[0]);
            if ($u->isRegistered()) {
                $requestUser = $u;
            }
        }
        if ($requestUser !== null) {
            return $requestUser;
        }
        if ($ipAccessControlRange === null) {
            $ipAccessControlAccess->registerEvent();
            if ($ipAccessControlAccess->isThresholdReached()) {
                $ipAccessControlAccess->addToDenylistForThresholdReached();
            }
        }

        return self::REQUESTUSER_INVALID_TOKEN;
    }

    private function getConfiguredAccessLevel(string $configKey): string
    {
        $config = $this->app->make(Repository::class);
        $level = $config->get("community_translation::api.access.{$configKey}");

        return is_string($level) ? $level : '';
    }

    /**
     * @throws AccessDeniedException
     */
    private function validateMarketToken(): void
    {
        // Extract the JWT from the request if one exists
        try {
            $bearer = (string) $this->request->headers->get('authorization');
            $jwt = substr($bearer, 7);
            $token = (new Parser(new JoseEncoder()))->parse($jwt);
        } catch (JwtException $e) {
            throw new AccessDeniedException(t('Access denied, invalid token.'));
        } catch (\Throwable) {
            throw new AccessDeniedException(t('Access denied, unable to process token.'));
        }

        // Validate the token was signed with valid OIDC, is valid now, and is permitted for translate
        try {
            // Use service locator to load SignedWithOidc so that we don't load expensive cache / config when not needed
            $signedWithOidc = $this->app->make(SignedWithOidc::class);
            (new Validator())->assert(
                $token,
                $signedWithOidc,
                new StrictValidAt(new FrozenClock(new \DateTimeImmutable())),
                new PermittedFor('https://translate.concretecms.org'),
            );
        } catch (RequiredConstraintsViolated $e) {
            throw new AccessDeniedException(t('Access denied, %s', $e->getMessage()));
        } catch (\Throwable $e) {
            throw new AccessDeniedException(t('Access denied, unable to validate token. %s', $e->getMessage()));
        }
    }
}
