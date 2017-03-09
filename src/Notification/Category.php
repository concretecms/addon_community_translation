<?php
namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Service\Groups;
use Concrete\Core\Application\Application;
use Concrete\Core\Block\Block;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use URL;

abstract class Category implements CategoryInterface
{
    /**
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
     * @var Groups|null
     */
    protected $groupsHelper = null;

    /**
     * @return Groups
     */
    protected function getGroupsHelper()
    {
        if ($this->groupsHelper === null) {
            $this->groupsHelper = $this->app->make(Groups::class);
        }

        return $this->groupsHelper;
    }

    /**
     * {@inheritdoc}
     *
     * @see CategoryInterface::getMailTemplate()
     */
    public function getMailTemplate()
    {
        $chunks = explode('\\', get_class($this));
        $className = array_pop($chunks);

        return [
            uncamelcase($className),
            'community_translation',
        ];
    }

    /**
     * Get the recipients user IDs.
     *
     * @param NotificationEntity $notification
     *
     * @return int[]
     */
    abstract protected function getRecipientIDs(NotificationEntity $notification);

    /**
     * {@inheritdoc}
     *
     * @see CategoryInterface::getRecipients()
     */
    public function getRecipients(NotificationEntity $notification)
    {
        $ids = $this->getRecipientIDs($notification);
        // be sure we have integers
        $ids = array_map('intval', $ids);
        // remove problematic IDs
        $ids = array_filter($ids);
        // remove duplicated
        $ids = array_unique($ids, SORT_REGULAR);
        if (!empty($ids)) {
            $repo = $this->app->make(UserInfoRepository::class);
            /* @var UserInfoRepository $repo */
            foreach ($ids as $id) {
                $userInfo = $repo->getByID($id);
                if ($userInfo !== null && $userInfo->isActive()) {
                    yield $userInfo;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see CategoryInterface::getMailParameters()
     */
    abstract public function getMailParameters(NotificationEntity $notification, UserInfo $recipient);

    /**
     * @param NotificationEntity $notification
     * @param UserInfo $recipient
     *
     * @return array
     */
    protected function getCommonMailParameters(NotificationEntity $notification, UserInfo $recipient)
    {
        $site = $this->app->make('site')->getSite();
        /* @var \Concrete\Core\Entity\Site\Site $site */

        return [
            'siteName' => $site->getSiteName(),
            'siteUrl' => (string) $site->getSiteCanonicalURL(),
            'recipientName' => $recipient->getUserName(),
        ];
    }

    /**
     * @param string $blockName
     *
     * @return string
     */
    protected function getBlockPageURL($blockName)
    {
        $page = null;
        if ($blockName) {
            $block = Block::getByName($blockName);
            if ($block && $block->getBlockID()) {
                $page = $block->getOriginalCollection();
            }
        }

        return (string) ($page ? URL::to($page) : URL::to('/'));
    }
}
