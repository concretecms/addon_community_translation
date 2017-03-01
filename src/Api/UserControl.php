<?php
namespace CommunityTranslation\Api;

use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use Concrete\Core\Http\Request;
use Concrete\Core\User\User;
use Concrete\Core\User\UserList;

class UserControl
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string|null
     */
    protected $requestApiToken = null;

    /**
     * @var User|false|null
     */
    protected $requestUser = false;

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
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param string $requestApiToken
     */
    public function setRequestApiToken($requestApiToken)
    {
        $this->requestApiToken = (string) $requestApiToken;
    }

    /**
     * @return string
     */
    public function getRequestApiToken()
    {
        if ($this->requestApiToken === null) {
            if ($this->request->headers !== null) {
                $this->requestApiToken = (string) $this->request->headers->get('API-Token', '');
            } else {
                $this->requestApiToken = '';
            }
        }

        return $this->requestApiToken;
    }

    /**
     * @param \Concrete\Core\User\User $requestUser
     */
    public function setRequestUser(User $requestUser)
    {
        $this->requestUser = $requestUser;
    }

    /**
     * @return User|null
     */
    public function getRequestUser()
    {
        if ($this->requestUser === false) {
            $this->requestUser = null;
            $token = $this->getRequestApiToken();
            if ($token !== '') {
                $list = new UserList();
                $list->disableAutomaticSorting();
                $list->filterByAttribute('api_token', $token);
                $ids = $list->getResultIDs();
                if (!empty($ids)) {
                    $u = \User::getByUserID($ids[0]);
                    if ($u->isRegistered()) {
                        $this->requestUser = $u;
                    }
                }
            }
        }

        return ($this->requestUser === false) ? null : $this->requestUser;
    }

    /**
     * @param int|empty $needGroupID
     *
     * @throws AccessDeniedException
     */
    public function checkRequest($needGroupID)
    {
        $ip = $this->app->make('ip');
        if ($ip->isBanned()) {
            throw AccessDeniedException::create($ip->getErrorMessage());
        }
        if ($needGroupID && $needGroupID != GUEST_GROUP_ID) {
            $ok = false;
            $user = $this->getRequestUser();
            if ($user !== null) {
                if ($needGroupID == REGISTERED_GROUP_ID) {
                    $ok = true;
                } else {
                    $group = \Group::getByID($needGroupID);
                    if ($group === null) {
                        throw new UserException('Group with ID "' . $needGroupID . '" has not been found!');
                    }
                    if ($user->getUserID() == USER_SUPER_ID || $user->inGroup($group)) {
                        $ok = true;
                    }
                }
            }
            if ($ok === false) {
                $ip->logSignupRequest();
                if ($ip->signupRequestThreshholdReached()) {
                    $ip->createIPBan();
                }
                if ($this->getRequestApiToken() === '') {
                    $message = t('No access token received');
                } elseif ($user === null) {
                    $message = t('Invalid access token received');
                } else {
                    $message = t('Access denied for the user associated to the access token');
                }
                throw AccessDeniedException::create($message);
            }
        }
    }
}
