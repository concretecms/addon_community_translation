<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;

class Events implements \Symfony\Component\EventDispatcher\EventSubscriberInterface,  \Concrete\Core\Application\ApplicationAwareInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'on_user_enter_group' => 'userEnterGroup',
        );
    }

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

    public function userEnterGroup(\Concrete\Core\User\Event\UserGroup $evt)
    {
        try {
            $user = new \User();
            if ($user->isRegistered() && $user->getUserID() == $evt->getUserObject()->getUserID()) {
                $groupManager = $this->app->make('community_translation/groups');
                $locale = $groupManager->decodeAspiringTranslatorsGroup($evt->getGroupObject());
                if ($locale !== null) {
                    $this->app->make('community_translation/notify')->newAspirantTranslator($user, $locale);
                }
            }
        } catch (Exception $x) {
        }
    }
}
