<?php
namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use Concrete\Core\Mail\Service as MailService;

abstract class Category
{
    /**
     * Get the email recipient email addresses.
     *
     * @return string[]
     */
    abstract public function getRecipients();

    /**
     * Set the.
     *
     * @param NotificationEntity $notification
     * @param MailService $notification
     *
     * @todo
     *
     * @return int
     */
    public function processNotification(NotificationEntity $notification, MailService $mail)
    {
        $recipients = $this->getRecipients();
        $mail->reset();

        return count($recipients);
    }
}
