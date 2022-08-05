<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use Concrete\Core\User\UserInfo;
use Generator;

defined('C5_EXECUTE') or die('Access Denied.');

interface CategoryInterface
{
    /**
     * Get the mail template identifier.
     *
     * @return array first array element is the email template, second array element is the package handle
     */
    public function getMailTemplate(): array;

    /**
     * Get the list of recipients.
     *
     * @return \Concrete\Core\User\UserInfo[]
     */
    public function getRecipients(NotificationEntity $notification): Generator;

    /**
     * Get the mail parameters for the mail template.
     */
    public function getMailParameters(NotificationEntity $notification, UserInfo $recipient): array;

    /**
     * Get the English description of the category.
     */
    public static function getDescription(): string;
}
