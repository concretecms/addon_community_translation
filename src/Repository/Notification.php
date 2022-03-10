<?php

declare(strict_types=1);

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
use CommunityTranslation\Notification\Category;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class Notification extends EntityRepository
{
    public function newLocaleRequested(LocaleEntity $locale, string $notes = ''): void
    {
        $this->createEntity(
            Category\NewLocaleRequested::class,
            [
                'localeID' => $locale->getID(),
                'notes' => $notes,
            ]
        );
    }

    public function newLocaleRequestApproved(LocaleEntity $locale, ?int $byUserID): void
    {
        $this->createEntity(
            Category\NewLocaleApproved::class,
            [
                'localeID' => $locale->getID(),
                'by' => $byUserID ?: USER_SUPER_ID,
            ]
        );
    }

    public function newLocaleRequestRejected(LocaleEntity $locale, ?int $byUserID): void
    {
        $this->createEntity(
            Category\NewLocaleRejected::class,
            [
                'localeID' => $locale->getID(),
                'by' => $byUserID ?: USER_SUPER_ID,
                'requestedBy' => $locale->getRequestedBy() ? $locale->getRequestedBy()->getUserID() : null,
            ]
        );
    }

    public function newTeamJoinRequest(LocaleEntity $locale, int $applicantUserID): void
    {
        $this->createEntity(
            Category\NewTeamJoinRequest::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => $applicantUserID,
            ]
        );
    }

    public function newTranslatorApproved(LocaleEntity $locale, int $applicantUserID, int $approvedByUserID, bool $automatic): void
    {
        $this->createEntity(
            Category\NewTranslatorApproved::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => $applicantUserID,
                'approvedByUserID' => $approvedByUserID,
                'automatic' => $automatic,
            ]
        );
    }

    public function newTranslatorRejected(LocaleEntity $locale, int $applicantUserID, int $rejectedByUserID): void
    {
        $this->createEntity(
            Category\NewTranslatorRejected::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => $applicantUserID,
                'rejectedByUserID' => $rejectedByUserID,
            ]
        );
    }

    public function translatableCommentSubmitted(TranslatableCommentEntity $comment, PackageVersionEntity $packageVersion, LocaleEntity $whileTranslatingLocale): void
    {
        $em = $this->getEntityManager();
        $locale = $comment->getRootComment()->getLocale();
        $localeID = $locale === null ? null : $locale->getID();
        $commentID = $comment->getID();
        // First of all: let's see if we're already queued a notification for this comment
        foreach ($this->findBy(['fqnClass' => Category\TranslatableComment::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if (!isset($data['comments'][$commentID])) {
                continue;
            }
            if ($data['localeID'] === $localeID) {
                return;
            }
            if (count($data['comments']) === 1) {
                $data['localeID'] = $localeID;
                $createNew = false;
            } else {
                unset($data['comments'][$commentID]);
                $createNew = true;
            }
            $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
            $em->persist($existing);
            $em->flush($existing);
            $em->detach($existing);
            if ($createNew === false) {
                return;
            }
            break;
        }
        $thisNotificationData = [
            'packageVersionID' => $packageVersion->getID(),
            'whileTranslatingLocaleID' => $whileTranslatingLocale->getID(),
        ];
        // A new notification is required: let's see if we have notifications for this locale
        foreach ($this->findBy(['fqnClass' => Category\TranslatableComment::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['localeID'] === $localeID) {
                $data['comments'][$commentID] = $thisNotificationData;
                $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
                $em->persist($existing);
                $em->flush($existing);
                $em->detach($existing);

                return;
            }
        }
        // A notification for a new locale is required
        $this->createEntity(
            Category\TranslatableComment::class,
            [
                'localeID' => $localeID,
                'comments' => [
                    $commentID => $thisNotificationData,
                ],
            ]
        );
    }

    public function translationsNeedApproval(LocaleEntity $locale, int $numTranslations, ?int $translatorUserID = null, ?int $packageVersionID = null): void
    {
        $em = $this->getEntityManager();
        $localeID = $locale->getID();
        $userKey = $translatorUserID ?: USER_SUPER_ID;
        $packageVersionID = $packageVersionID ?: null;
        foreach ($this->findBy(['fqnClass' => Category\TranslationsNeedApproval::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['localeID'] !== $localeID) {
                continue;
            }
            if ($data['packageVersionID'] !== $packageVersionID) {
                $data['packageVersionID'] = null;
            }
            if (isset($data['numTranslations'][$userKey])) {
                $data['numTranslations'][$userKey] += $numTranslations;
            } else {
                $data['numTranslations'][$userKey] = $numTranslations;
            }
            $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
            $em->persist($existing);
            $em->flush($existing);
            $em->detach($existing);

            return;
        }
        $this->createEntity(
            Category\TranslationsNeedApproval::class,
            [
                'localeID' => $localeID,
                'packageVersionID' => $packageVersionID,
                'numTranslations' => [
                    $userKey => $numTranslations,
                ],
            ]
        );
    }

    public function newTranslatablePackage(int $packageID, int $recipientUserID): void
    {
        $em = $this->getEntityManager();
        foreach ($this->findBy(['fqnClass' => Category\NewTranslatablePackage::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] !== $recipientUserID) {
                continue;
            }
            $data['packageIDs'][] = $packageID;
            $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
            $em->persist($existing);
            $em->flush($existing);
            $em->detach($existing);

            return;
        }
        $this->createEntity(
            Category\NewTranslatablePackage::class,
            [
                'userID' => $recipientUserID,
                'packageIDs' => [$packageID],
            ]
        );
    }

    public function newTranslatablePackageVersion(int $packageVersionID, int $recipientUserID): void
    {
        $em = $this->getEntityManager();
        foreach ($this->findBy(['fqnClass' => Category\NewTranslatablePackageVersion::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] !== $recipientUserID) {
                continue;
            }
            $data['packageVersionIDs'][] = $packageVersionID;
            $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
            $em->persist($existing);
            $em->flush($existing);
            $em->detach($existing);

            return;
        }
        $this->createEntity(
            Category\NewTranslatablePackageVersion::class,
            [
                'userID' => $recipientUserID,
                'packageVersionIDs' => [$packageVersionID],
            ]
        );
    }

    public function updatedTranslatablePackageVersion(int $packageVersionID, int $recipientUserID): void
    {
        $em = $this->getEntityManager();
        foreach ($this->findBy(['fqnClass' => Category\UpdatedTranslatablePackageVersion::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] !== $recipientUserID) {
                continue;
            }
            if (!in_array($packageVersionID, $data['packageVersionIDs'])) {
                $data['packageVersionIDs'][] = $packageVersionID;
                $existing->setNotificationData($data)->setUpdatedOn(new DateTimeImmutable());
                $em->persist($existing);
                $em->flush($existing);
            }
            $em->detach($existing);

            return;
        }
        $this->createEntity(
            Category\UpdatedTranslatablePackageVersion::class,
            [
                'userID' => $recipientUserID,
                'packageVersionIDs' => [
                    $packageVersionID,
                ],
            ]
        );
    }

    public function pluralChangedReapprovalNeeded(LocaleEntity $locale, int $numTranslations): void
    {
        $this->createEntity(
            Category\PluralChangedReapprovalNeeded::class,
            [
                'localeID' => $locale->getID(),
                'numTranslations' => $numTranslations,
            ]
        );
    }

    private function createEntity(string $fqnClass, array $notificationData, ?int $priority = null): void
    {
        $em = $this->getEntityManager();
        $entity = new NotificationEntity($fqnClass, $notificationData, $priority);
        $em->persist($entity);
        $em->flush($entity);
        $em->detach($entity);
    }
}
