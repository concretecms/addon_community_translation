<?php
namespace Concrete\Package\CommunityTranslation\Src\Api;

use Concrete\Core\Application\Application;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Package\CommunityTranslation\Src\UserException;

class UserControl implements ApplicationAwareInterface
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @var \Concrete\Core\Http\Request|null
     */
    protected $request = null;

    /**
     * @param \Concrete\Core\Http\Request $request
     */
    public function setRequest(\Concrete\Core\Http\Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return \Concrete\Core\Http\Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = \Request::getInstance();
        }

        return $this->request;
    }

    /**
     * @var string|null
     */
    protected $requestApiToken = null;

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
            $request = $this->getRequest();
            if ($request->headers !== null) {
                $this->requestApiToken = (string) $request->headers->get('API-Token', '');
            } else {
                $this->requestApiToken = '';
            }
        }

        return $this->requestApiToken;
    }

    /**
     * @var \Concrete\Core\User\User|false|null
     */
    protected $requestUser = null;

    /**
     * @param \Concrete\Core\User\User $requestUser
     */
    public function setRequestUser(\Concrete\Core\User\User $requestUser)
    {
        $this->requestUser = $requestUser;
    }

    /**
     * @return \User|null
     */
    public function getRequestUser()
    {
        if ($this->requestUser === null) {
            $this->requestUser = false;
            $token = $this->getRequestApiToken();
            if ($token !== '') {
                $list = new \UserList();
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
                        throw new UserException('Group with ID "'.$needGroupID.'" has not been found!');
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
