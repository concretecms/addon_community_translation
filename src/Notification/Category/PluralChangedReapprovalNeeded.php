<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\UserInfo;
use Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Notification category: some translations need approval.
 */
class PluralChangedReapprovalNeeded extends Category
{
    /**
     * @var int
     */
    public const PRIORITY = 1;

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\CategoryInterface::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient): array
    {
        $notificationData = $notification->getNotificationData();
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }

        return [
            'localeName' => $locale->getDisplayName(),
            'numTranslations' => (int) $notificationData['numTranslations'],
            'approvalURL' => (string) $this->app->make(ResolverManagerInterface::class)->resolve([
                (string) $this->app->make(Repository::class)->get('community_translation::paths.onlineTranslation'),
                OnlineTranslation::PACKAGEVERSION_UNREVIEWED,
                $locale->getID(),
            ]),
        ] + $this->getCommonMailParameters($notification, $recipient);
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification): array
    {
        $result = [];
        $notificationData = $notification->getNotificationData();
        $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
        if ($locale === null) {
            throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
        }
        $group = $this->getGroupService()->getAdministrators($locale);
        $result = array_merge($result, $group->getGroupMemberIDs());
        if ($result === []) {
            $group = $this->getGroupService()->getGlobalAdministrators();
            $result = $group->getGroupMemberIDs();
        }

        return $result;
    }
}
