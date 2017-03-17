<?php
namespace CommunityTranslation\Notification\Category;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
use CommunityTranslation\Notification\Category;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\Translatable\Comment as TranslatableCommentRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserList;
use Exception;
use URL;

/**
 * Notification category: a new comment about a translation has been submitted.
 */
class TranslatableComment extends Category
{
    /**
     * @var int
     */
    const PRIORITY = 5;

    /**
     * @param TranslatableCommentEntity $comment
     *
     * @return int[]
     */
    private function getCommentPeopleIDs(TranslatableCommentEntity $comment)
    {
        $result = [];
        $author = $comment->getPostedBy();
        if ($author !== null) {
            $result[] = $author->getUserID();
        }
        foreach ($comment->getChildComments() as $childComment) {
            $result = array_merge($result, $this->getCommentPeopleIDs($childComment));
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getRecipientIDs()
     */
    protected function getRecipientIDs(NotificationEntity $notification)
    {
        $result = [];
        $locale = null;
        $notificationData = $notification->getNotificationData();
        $commentsRepository = $this->app->make(TranslatableCommentRepository::class);
        $someComment = false;
        // Let's notify also all the people involved in the whole discussions
        foreach (array_keys($notificationData['comments']) as $commentID) {
            $comment = $commentsRepository->find($commentID);
            if ($comment !== null) {
                $someComment = true;
                $result = array_merge($result, $this->getCommentPeopleIDs($comment->getRootComment()));
            }
        }
        if ($someComment === false) {
            throw new Exception(t('No comment found (have they been deleted?)'));
        }
        if ($notificationData['localeID'] === null) {
            // Discussions about source strings (not locale specific): let's notify the global administrators
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getGlobalAdministrators());
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
        } else {
            // Locale-specific discussions: let's notify the people involved in that locale
            $locale = $this->app->make(LocaleRepository::class)->findApproved($notificationData['localeID']);
            if ($locale === null) {
                throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
            }
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getTranslators($locale));
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
            $ul = new UserList();
            $ul->disableAutomaticSorting();
            $ul->filterByGroup($this->getGroupsHelper()->getAdministrators($locale));
            $ul->filterByAttribute('notify_translatable_messages', 1);
            $result = array_merge($result, $ul->getResultIDs());
        }

        return $result;
    }

    /**
     * @param string $md
     *
     * @return string
     */
    private function markdownToHtml($md)
    {
        return nl2br(
            $this->app->make('helper/text')->autolink(
                htmlspecialchars(
                    $md,
                    ENT_QUOTES,
                    APP_CHARSET,
                    true
                )
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see Category::getMailParameters()
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient)
    {
        $notificationData = $notification->getNotificationData();
        $localeRepository = $this->app->make(LocaleRepository::class);
        if ($notificationData['localeID'] === null) {
            $locale = null;
        } else {
            $locale = $localeRepository->findApproved($notificationData['localeID']);
            if ($locale === null) {
                throw new Exception(t('Unable to find the locale with ID %s', $notificationData['localeID']));
            }
        }
        $commentsRepository = $this->app->make(TranslatableCommentRepository::class);
        $packageVersionRepository = $this->app->make(PackageVersionRepository::class);
        $comments = [];
        $config = $this->app->make('community_translation/config');
        $onlineTranslationPath = $config->get('options.onlineTranslationPath');
        $dh = $this->app->make('date');
        foreach ($notificationData['comments'] as $commentID => $commentInfo) {
            $comment = $commentsRepository->find($commentID);
            if ($comment !== null) {
                $packageVersion = $packageVersionRepository->find($commentInfo['packageVersionID']);
                if ($packageVersion !== null) {
                    $localeForLink = $locale;
                    if ($localeForLink === null) {
                        $localeForLink = $localeRepository->findApproved($commentInfo['whileTranslatingLocaleID']);
                    }
                    if ($localeForLink !== null) {
                        $comments[] = [
                            '_dateSort' => $comment->getPostedOn()->getTimestamp(),
                            'date' => $dh->formatPrettyDateTime($comment->getPostedOn(), true, true, $recipient->getUserTimezone() ?: 'user'),
                            'author' => $comment->getPostedBy(),
                            'translatable' => $comment->getTranslatable()->getText(),
                            'messageHtml' => $this->markdownToHtml($comment->getText()),
                            'link' => ((string) URL::to($onlineTranslationPath, $packageVersion->getID(), $localeForLink->getID())) . '#tid:' . $comment->getTranslatable()->getID(),
                        ];
                    }
                }
            }
        }

        if (empty($comments)) {
            throw new Exception(t('No comment found (have they been deleted?)'));
        }

        usort($comments, function (array $a, array $b) {
            return $a['_dateSort'] - $b['_dateSort'];
        });
        $comments = array_map(
            function (array $comment) {
                unset($comment['_dateSort']);

                return $comment;
            },
            $comments
        );

        return [
            'specificForLocale' => ($locale === null) ? null : $locale->getDisplayName(),
            'comments' => $comments,
        ] + $this->getCommonMailParameters($notification, $recipient);
    }
}
