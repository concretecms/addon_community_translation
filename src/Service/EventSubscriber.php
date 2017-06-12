<?php
namespace CommunityTranslation\Service;

use CommunityTranslation\Repository\LocaleStats as LocaleStatsRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\User\Event\UserGroup;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class EventSubscriber implements EventSubscriberInterface
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {inheritdoc}.
     *
     * @see \Symfony\Component\EventDispatcher\EventDispatcherInterface::getSubscribedEvents()
     */
    public static function getSubscribedEvents()
    {
        return [
            'on_user_enter_group' => 'userEnterGroup',
            'community_translation.translatableUpdated' => 'translatableUpdated',
            'community_translation.translationsUpdated' => 'translationsUpdated',
            'community_translation.newApprovalNeeded' => 'newApprovalNeeded',
        ];
    }

    /**
     * @param UserGroup $evt
     */
    public function userEnterGroup(UserGroup $evt)
    {
        $user = new \User();
        if ($user->isRegistered() && $user->getUserID() == $evt->getUserObject()->getUserID()) {
            $groupManager = $this->app->make(Groups::class);
            $locale = $groupManager->decodeAspiringTranslatorsGroup($evt->getGroupObject());
            if ($locale !== null) {
                $this->app->make(NotificationRepository::class)->newTeamJoinRequest($locale, $user->getUserID());
            }
        }
    }

    /**
     * @param GenericEvent $evt
     */
    public function translatableUpdated(GenericEvent $evt)
    {
        $packageVersion = $evt->getSubject();
        /* @var \CommunityTranslation\Entity\Package\Version $packageVersion */
        $packageIsNew = (bool) $evt->getArgument('packageIsNew');
        $numStringsAdded = (int) $evt->getArgument('numStringsAdded');
        $this->app->make(StatsRepository::class)->resetForPackageVersion($packageVersion);
        $this->app->make(LocaleStatsRepository::class)->resetAll();
    }

    /**
     * @param GenericEvent $evt
     */
    public function translationsUpdated(GenericEvent $evt)
    {
        $locale = $evt->getSubject();
        /* @var \CommunityTranslation\Entity\Locale $locale */
        $translatableIDs = $evt->getArgument('translatableIDs');
        $this->app->make(StatsRepository::class)->resetForLocaleTranslatables($locale, $translatableIDs);
        $this->app->make(LocaleStatsRepository::class)->resetForLocale($locale);
    }

    /**
     * @param GenericEvent $evt
     */
    public function newApprovalNeeded(GenericEvent $evt)
    {
        $locale = $evt->getSubject();
        /* @var \CommunityTranslation\Entity\Locale $locale */
        $number = $evt->getArgument('number');
    }
}
