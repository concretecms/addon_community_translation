<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Service\Group as GroupService;
use CommunityTranslation\Service\User as UserService;
use Concrete\Core\Application\Application;
use Concrete\Core\Block\Block;
use Concrete\Core\Site\Service as SiteService;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Generator;
use RuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Category implements CategoryInterface
{
    protected Application $app;

    private ?GroupService $groupService = null;

    private array $blockPageURLs = [];

    private ?array $commonMailParameters = null;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->blockPageURLs = [];
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\CategoryInterface::getMailTemplate()
     */
    public function getMailTemplate(): array
    {
        $chunks = explode('\\', get_class($this));
        $className = array_pop($chunks);

        return [
            uncamelcase($className),
            'community_translation',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Notification\CategoryInterface::getRecipients()
     */
    public function getRecipients(NotificationEntity $notification): Generator
    {
        $ids = $this->getRecipientIDs($notification);
        // be sure we have integers
        $ids = array_map('intval', $ids);
        // remove problematic IDs
        $ids = array_filter($ids);
        // remove duplicated
        $ids = array_unique($ids, SORT_REGULAR);
        if ($ids === []) {
            return;
        }
        $repo = $this->app->make(UserInfoRepository::class);
        foreach ($ids as $id) {
            $userInfo = $repo->getByID($id);
            if ($userInfo !== null && $userInfo->isActive()) {
                yield $userInfo;
            }
        }
    }

    /**
     * Get the recipients user IDs.
     *
     * @param NotificationEntity $notification
     *
     * @return int[]
     */
    abstract protected function getRecipientIDs(NotificationEntity $notification): array;

    protected function getGroupService(): GroupService
    {
        if ($this->groupService === null) {
            $this->groupService = $this->app->make(GroupService::class);
        }

        return $this->groupService;
    }

    protected function getCommonMailParameters(NotificationEntity $notification, UserInfo $recipient): array
    {
        if ($this->commonMailParameters === null) {
            $site = $this->app->make(SiteService::class)->getSite();
            if ($site === null) {
                throw new RuntimeException(t('Unable to get the current site'));
            }
            $this->commonMailParameters = [
                'siteName' => $site->getSiteName(),
                'siteUrl' => (string) $site->getSiteCanonicalURL(),
                'userService' => $this->app->make(UserService::class),
                'recipientAccountUrl' => (string) $this->app->make(ResolverManagerInterface::class)->resolve(['/account/edit_profile']),
            ];
        }

        return $this->commonMailParameters + [
            'recipientName' => $recipient->getUserName(),
        ];
    }

    protected function getBlockPageURL(string $blockName, string $blockAction = '', bool $isBlockActionInstanceSpecific = false): string
    {
        if (!isset($this->blockPageURLs[$blockName])) {
            $page = null;
            $block = Block::getByName($blockName);
            $page = $block && $block->getBlockID() ? $block->getOriginalCollection() : null;
            if ($page && $page->isError()) {
                $page = null;
            }
            $urlResolver = $this->app->make(ResolverManagerInterface::class);
            $this->blockPageURLs[$blockName] = [
                'foundBlockID' => $page === null ? null : $block->getBlockID(),
                'url' => (string) $urlResolver->resolve($page ? [$page] : ['/']),
            ];
        }
        $url = $this->blockPageURLs[$blockName]['url'];
        if ($blockAction !== '' && $this->blockPageURLs[$blockName]['foundBlockID'] !== null) {
            $url = rtrim($url, '/') . '/' . trim($blockAction, '/');
            if ($isBlockActionInstanceSpecific) {
                $url .= '/' . $this->blockPageURLs[$blockName]['foundBlockID'];
            }
        }

        return $url;
    }
}
