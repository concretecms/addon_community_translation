<?php
namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use Concrete\Core\Mail\Service as MailService;

interface CategoryInterface
{
    /**
     * Fill-in a mail service object.
     *
     * @param NotificationEntity $notification
     * @param MailService $notification
     *
     * @return int The number of recipients
     */
    public function processNotification(NotificationEntity $notification, MailService $mail);
}
