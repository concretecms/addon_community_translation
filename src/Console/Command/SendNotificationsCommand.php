<?php
namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Notification\CategoryInterface;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use Concrete\Core\Console\Command;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\Support\Facade\Application;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SendNotificationsCommand extends Command
{
    const RETURN_CODE_ON_FAILURE = 3;

    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:send-notifications')
            ->setDescription('Send pending notifications')
            ->setHelp(<<<EOT
This command send notifications about events of Community Translations, like requests for new translation teams and new translators.

Returns codes:
  0 no notification has been sent
  1 some notification has been sent
  2 errors occurred but some notification has been sent
  $errExitCode errors occurred and no notification has been sent
EOT
            )
        ;
    }

    /**
     * @var \Concrete\Core\Application\Application|null
     */
    private $app;

    /**
     * @var CategoryInterface[]|null
     */
    private $categories;

    /**
     * @var string[]|null
     */
    private $from;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->app = Application::getFacadeApplication();
        $ctConfig = $this->app->make('community_translation/config');
        $fromEmail = (string) $ctConfig->get('options.notificationsSenderAddress');
        if ($fromEmail !== '') {
            $this->from = [$fromEmail, $ctConfig->get('options.notificationsSenderName') ?: null];
        } else {
            $config = $this->app->make('config');
            $this->from = [$config->get('concrete.email.default.address'), $config->get('concrete.email.default.name') ?: null];
        }
        $this->categories = [];
        $em = $this->app->make(EntityManager::class);
        $repo = $this->app->make(NotificationRepository::class);
        $mail = $this->app->make('mail');
        $lastID = null;
        /* @var NotificationRepository $repo */
        for (; ;) {
            $criteria = Criteria::create()->where(Criteria::expr()->isNull('sentOn'))->setMaxResults(1);
            if ($lastID !== null) {
                $criteria->andWhere(Criteria::expr()->gt('id', $lastID));
            }
            $notification = $repo->matching($criteria)->first();
            if (!$notification) {
                break;
            }
            $lastID = $notification->getID();
            $notification->setSentOn(new DateTime());
            $em->persist($notification);
            $em->flush($notification);
            $error = null;
            try {
                $this->processNotification($notification, $mail);
            } catch (Exception $x) {
                $error = $x;
            } catch (Throwable $x) {
                $error = $x;
            }
            if ($error !== null) {
                $notification->addDeliveryError($error->getMessage());
            }
            $em->persist($notification);
            $em->flush($notification);
        }
    }

    /**
     * @param string $fqnClass
     *
     * @throws Exception
     *
     * @return CategoryInterface
     */
    private function getCategory($fqnClass)
    {
        if (!isset($this->categories[$fqnClass])) {
            if (!class_exists($fqnClass, true)) {
                $this->categories[$fqnClass] = sprintf('Unable to find the category class %s', $fqnClass);
            } else {
                $obj = null;
                $error = null;
                try {
                    $obj = $this->app->make($fqnClass);
                } catch (Exception $x) {
                    $error = $x;
                } catch (Throwable $x) {
                    $error = $x;
                }
                if ($error !== null) {
                    $this->categories[$fqnClass] = sprintf('Failed to initialize category class %1$s: $2$s', $fqnClass, $error->getMessage());
                } elseif (!($obj instanceof CategoryInterface)) {
                    $this->categories[$fqnClass] = sprintf('The class %1$s does not implement $2$s', $fqnClass, CategoryInterface::class);
                } else {
                    $this->categories[$fqnClass] = $obj;
                }
            }
        }
        if (is_string($this->categories[$fqnClass])) {
            throw new Exception($this->categories[$fqnClass]);
        }

        return $this->categories[$fqnClass];
    }

    /**
     * @param NotificationEntity $notification
     *
     * @throws Exception
     */
    private function processNotification(NotificationEntity $notification, MailService $mail)
    {
        $category = $this->getCategory($notification->getFQNClass());
        $recipients = $category->processNotification($notification, $mail);
        $notification->setSentCount($recipients);
        if ($recipients > 0) {
            $mail->from($this->from[0], $this->from[1]);
            $mail->to($this->from[0], $this->from[1]);
            if (!$mail->sendMail()) {
                throw new Exception('Email was not sent');
            }
        }
    }
}
