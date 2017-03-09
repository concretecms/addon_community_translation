<?php
namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use Concrete\Core\User\UserInfo;

interface CategoryInterface
{
    /**
     * Get the mail template identifier.
     *
     * @return array first array element is the email template, second array element is the package handle
     */
    public function getMailTemplate();

    /**
     * Get the list of recipients.
     *
     * @param NotificationEntity $notification
     *
     * @return UserInfo[]|\Generator
     */
    public function getRecipients(NotificationEntity $notification);

    /**
     * Get the mail parameters for the mail template.
     *
     * @param NotificationEntity $notification
     * @param UserInfo $recipient
     *
     * @return array
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient);
}
