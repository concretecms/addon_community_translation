<?php

namespace Concrete\Package\CommunityTranslation\Src\Api;

class UserControl
{
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
}
