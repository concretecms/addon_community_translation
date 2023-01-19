<?php

declare(strict_types=1);

namespace CommunityTranslation\Notification;

use CommunityTranslation\Entity\Notification as NotificationEntity;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Mail\Service as MailService;
use Concrete\Core\User\UserInfo;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class Sender
{
    private Application $categoryBuilder;

    private MailService $mailService;

    private EntityManager $entityManager;

    private array $from;

    private array $categories = [];

    public function __construct(Application $categoryBuilder, MailService $mailService, EntityManager $entityManager, Repository $config)
    {
        $this->categoryBuilder = $categoryBuilder;
        $this->mailService = $mailService;
        $this->entityManager = $entityManager;
        $from = $this->buildFrom($config, 'community_translation::notifications.sender.address', 'community_translation::notifications.sender.name');
        if ($from === null) {
            $from = $this->buildFrom($config, 'concrete.email.default.address', 'concrete.email.default.name');
            if ($from === null) {
                throw new UserMessageException('Neither the CommunityTranslation sender address nor the system default sender address are configured');
            }
        }
        $this->from = $from;
    }

    /**
     * @return \Throwable|null returns NULL if the notification has been sent to at lease one recipient, a throwable otherwise (see also $notification->getDeliveryErrors() for further details)
     */
    public function send(NotificationEntity $notification): ?Throwable
    {
        // Let's mark the notification as sent, so that it won't be updated with new messages
        $notification
            ->setSentOn(new DateTimeImmutable())
            ->setDeliveryAttempts($notification->getDeliveryAttempts() + 1)
            ->setDeliveryErrors(['Delivery in progress right now'])
        ;
        $this->entityManager->persist($notification);
        $this->entityManager->flush($notification);
        $notification
            ->setSentCountPotential(0)
            ->setSentCountActual(0)
            ->setDeliveryErrors([])
        ;
        $deliveryErrors = [];
        $result = null;
        try {
            $category = $this->getNotificationCategory($notification);
            foreach ($category->getRecipients($notification) as $recipient) {
                $notification->setSentCountPotential($notification->getSentCountPotential() + 1);
                $this->prepareMailService($notification, $recipient, $category);
                try {
                    $this->mailService->sendMail();
                    $sent = true;
                } catch (Throwable $x) {
                    $sent = false;
                    $deliveryErrors[] = $x;
                }
                if ($sent) {
                    $notification->setSentCountActual($notification->getSentCountActual() + 1);
                }
            }
            if ($notification->getSentCountActual() === 0 && $notification->getSentCountPotential() !== 0) {
                throw new UserMessageException("Mail service failed for all recipients.\nFirst error: " . trim($deliveryErrors[0]->getMessage()));
            }
            foreach ($deliveryErrors as $deliveryError) {
                $notification->addDeliveryError($deliveryError->getMessage());
            }
        } catch (Throwable $x) {
            $notification
                ->addDeliveryError($x->getMessage())
                ->setSentOn(null)
            ;
            $result = $x;
        } finally {
            $this->entityManager->flush($notification);
        }

        return $result;
    }

    private function buildFrom(Repository $config, string $addressKey, string $nameKey = ''): ?array
    {
        $fromEmail = $config->get($addressKey);
        if (!is_string($fromEmail) || $fromEmail === '') {
            return null;
        }
        $fromName = $nameKey === '' ? null : $config->get($nameKey);
        if (!is_string($fromName) || $fromName === '') {
            $fromName = null;
        }

        return [$fromEmail, $fromName];
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getNotificationCategory(NotificationEntity $notification): CategoryInterface
    {
        $fqnClass = $notification->getFQNClass();
        if (!isset($this->categories[$fqnClass])) {
            if (!class_exists($fqnClass, true)) {
                $this->categories[$fqnClass] = sprintf('Unable to find the category class %s', $fqnClass);
            } else {
                $obj = null;
                $error = null;
                try {
                    $obj = $this->categoryBuilder->make($fqnClass);
                } catch (Throwable $x) {
                    $error = $x;
                }
                if ($error !== null) {
                    $this->categories[$fqnClass] = sprintf('Failed to initialize category class %1$s: %2$s', $fqnClass, $error->getMessage());
                } elseif (!($obj instanceof CategoryInterface)) {
                    $this->categories[$fqnClass] = sprintf('The class %1$s does not implement %2$s', $fqnClass, CategoryInterface::class);
                } else {
                    $this->categories[$fqnClass] = $obj;
                }
            }
        }
        if (is_string($this->categories[$fqnClass])) {
            throw new UserMessageException($this->categories[$fqnClass]);
        }

        return $this->categories[$fqnClass];
    }

    private function prepareMailService(NotificationEntity $notification, UserInfo $recipient, CategoryInterface $category): void
    {
        $this->mailService->reset();
        $this->mailService->setIsThrowOnFailure(true);
        $this->mailService->from($this->from[0], $this->from[1]);
        $this->mailService->to($recipient->getUserEmail(), $recipient->getUserName());
        foreach ($category->getMailParameters($notification, $recipient) as $key => $value) {
            $this->mailService->addParameter($key, $value);
        }
        $tp = $category->getMailTemplate();
        $this->mailService->load($tp[0], $tp[1]);
    }
}
