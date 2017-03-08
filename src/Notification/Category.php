<?php
namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Service\Groups;
use Concrete\Core\Application\Application;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Mail\Service as MailService;
use Doctrine\ORM\EntityManager;

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
     * Set the email parameters.
     *
     * @param array $notificationData
     * @param MailService $mail
     */
    abstract protected function addMailParameters(array $notificationData, MailService $mail);

    /**
     * Get the recipients user IDs.
     *
     * @param array $notificationData
     *
     * @return int[]
     */
    abstract protected function getRecipientIDs(array $notificationData);

    /**
     * Get the mail template identifier.
     *
     * @return array first array element is the email template, second array element is the package handle
     */
    protected function getMailTemplate()
    {
        $chunks = explode('\\', get_class($this));
        $className = array_pop($chunks);

        return [
            uncamelcase($className),
            'community_translation',
        ];
    }

    /**
     * Get the recipient email addresses.
     *
     * @param array $notificationData
     *
     * @return string[]
     */
    private function getRecipientEmails(array $notificationData)
    {
        $result = [];
        $ids = $this->getRecipientIDs($notificationData);
        // be sure we have integers
        $ids = array_map('intval', $ids);
        // remove problematic IDs
        $ids = array_filter($ids);
        // remove duplicated
        $ids = array_unique($ids, SORT_REGULAR);
        if (!empty($ids)) {
            $repo = $this->app->make(EntityManager::class)->getRepository(UserEntity::class);
            /* @var \Doctrine\ORM\EntityRepository $repo */
            $qb = $repo->createQueryBuilder('u');
            $rows = $qb
                ->select('u.uEmail')
                ->where('u.uIsActive = 1')
                ->andWhere($qb->expr()->in('u.uID', $ids))
                ->getQuery()->getArrayResult();
            foreach ($rows as $row) {
                $result[] = array_pop($row);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see CategoryInterface::processNotification()
     */
    public function processNotification(NotificationEntity $notification, MailService $mail)
    {
        $recipientEmails = $this->getRecipientEmails($notification->getNotificationData());
        $numRecipient = count($recipientEmails);
        if ($numRecipient > 0) {
            $this->addMailParameters($notification->getNotificationData(), $mail);
            $tp = $this->getMailTemplate();
            $mail->load($tp[0], $tp[1]);
            foreach ($recipientEmails as $recipientEmail) {
                $mail->bcc($recipientEmail);
            }
        }

        return $numRecipient;
    }
}
