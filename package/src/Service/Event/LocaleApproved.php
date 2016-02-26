<?php
namespace Concrete\Package\CommunityTranslation\Src\Service\Event;

use Symfony\Component\EventDispatcher\Event as AbstractEvent;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Core\User\User;

class LocaleApproved extends AbstractEvent
{
    /**
     * @var Locale
     */
    protected $locale;

    /**
     * @var User|null
     */
    protected $approver;

    public function __construct(Locale $locale, User $approver = null)
    {
        $this->locale = $locale;
        $this->approver = $approver;
        if ($this->approver === null) {
            $me = new \User();
            if ($me->isRegistered()) {
                $this->approver = $me;
            }
        }
    }

    /**
     * @return Locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return User|null
     */
    public function getApprover()
    {
        return $this->approver;
    }
}
