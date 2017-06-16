<?php

namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
use CommunityTranslation\Notification\Category\NewLocaleApproved;
use CommunityTranslation\Notification\Category\NewLocaleRejected;
use CommunityTranslation\Notification\Category\NewLocaleRequested;
use CommunityTranslation\Notification\Category\NewTeamJoinRequest;
use CommunityTranslation\Notification\Category\NewTranslatablePackage;
use CommunityTranslation\Notification\Category\NewTranslatablePackageVersion;
use CommunityTranslation\Notification\Category\NewTranslatorApproved;
use CommunityTranslation\Notification\Category\NewTranslatorRejected;
use CommunityTranslation\Notification\Category\TranslatableComment;
use CommunityTranslation\Notification\Category\TranslationsNeedApproval;
use CommunityTranslation\Notification\Category\UpdatedTranslatablePackageVersion;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\User\User;
use DateTime;
use Doctrine\ORM\EntityRepository;

class Notification extends EntityRepository
{
    /**
     * @param bool $required
     *
     * @return int|null
     */
    private function getCurrentUserID($required)
    {
        $result = null;
        if (User::isLoggedIn()) {
            $u = new User();
            if ($u->isRegistered()) {
                $uID = (int) $u->getUserID();
                if ($uID !== 0) {
                    $result = $uID;
                }
            }
        }

        if ($result === null && $required) {
            throw new UserMessageException(t('No logged in user'));
        }

        return $result;
    }

    /**
     * @param bool $required
     *
     * @return UserEntity|null
     */
    private function getCurrentUser($required)
    {
        $result = null;
        $id = $this->getCurrentUserID($required);
        if ($id !== null) {
            $result = $this->getEntityManager()->find(UserEntity::class, $id);
        }

        if ($result === null && $required) {
            throw new UserMessageException(t('No logged in user'));
        }

        return $result;
    }

    /**
     * @param LocaleEntity $locale
     * @param LocaleEntity $locale
     * @param string $notes
     */
    public function newLocaleRequested(LocaleEntity $locale, $notes = '')
    {
        $n = NotificationEntity::create(
            NewLocaleRequested::class,
            [
                'localeID' => $locale->getID(),
                'notes' => (string) $notes,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param int $byUserID
     */
    public function newLocaleRequestApproved(LocaleEntity $locale, $byUserID)
    {
        $n = NotificationEntity::create(
            NewLocaleApproved::class,
            [
                'localeID' => $locale->getID(),
                'by' => (int) ($byUserID ?: USER_SUPER_ID),
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param int $byUserID
     */
    public function newLocaleRequestRejected(LocaleEntity $locale, $byUserID)
    {
        $n = NotificationEntity::create(
            NewLocaleRejected::class,
            [
                'localeID' => $locale->getID(),
                'by' => (int) ($byUserID ?: USER_SUPER_ID),
                'requestedBy' => $locale->getRequestedBy() ? $locale->getRequestedBy()->getUserID() : null,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param int $applicantUserID
     */
    public function newTeamJoinRequest(LocaleEntity $locale, $applicantUserID)
    {
        $n = NotificationEntity::create(
            NewTeamJoinRequest::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => (int) $applicantUserID,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param int $applicantUserID
     * @param int $approvedByUserID
     * @param bool $automatic
     */
    public function newTranslatorApproved(LocaleEntity $locale, $applicantUserID, $approvedByUserID, $automatic)
    {
        $n = NotificationEntity::create(
            NewTranslatorApproved::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => (int) $applicantUserID,
                'approvedByUserID' => (int) $approvedByUserID,
                'automatic' => (bool) $automatic,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param int $applicantUserID
     * @param int $rejectedByUserID
     */
    public function newTranslatorRejected(LocaleEntity $locale, $applicantUserID, $rejectedByUserID)
    {
        $n = NotificationEntity::create(
            NewTranslatorRejected::class,
            [
                'localeID' => $locale->getID(),
                'applicantUserID' => (int) $applicantUserID,
                'rejectedByUserID' => (int) $rejectedByUserID,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
        $em->detach($n);
    }

    /**
     * @param TranslatableCommentEntity $comment
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $whileTranslatingLocale
     */
    public function translatableCommentSubmitted(TranslatableCommentEntity $comment, PackageVersionEntity $packageVersion, LocaleEntity $whileTranslatingLocale)
    {
        $em = $this->getEntityManager();
        $locale = $comment->getRootComment()->getLocale();
        $localeID = ($locale === null) ? null : $locale->getID();
        $commentID = $comment->getID();
        $createNew = true;
        // First of all: let's see if we're already queued a notification for this comment
        foreach ($this->findBy(['fqnClass' => TranslatableComment::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if (isset($data['comments'][$commentID])) {
                if ($data['localeID'] === $localeID) {
                    $createNew = false;
                } else {
                    if (count($data['comments']) === 1) {
                        $data['localeID'] = $localeID;
                        $createNew = false;
                    } else {
                        unset($data['comments'][$commentID]);
                    }
                    $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                    $em->persist($existing);
                    $em->flush($existing);
                    $em->detach($existing);
                }
                break;
            }
        }
        if ($createNew === true) {
            $thisNotificationData = [
                'packageVersionID' => $packageVersion->getID(),
                'whileTranslatingLocaleID' => $whileTranslatingLocale->getID(),
            ];
            // A new notification is required: let's see if we have notifications for this locale
            foreach ($this->findBy(['fqnClass' => TranslatableComment::class, 'sentOn' => null]) as $existing) {
                $data = $existing->getNotificationData();
                if ($data['localeID'] === $localeID) {
                    $createNew = false;
                    $data['comments'][$commentID] = $thisNotificationData;
                    $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                    $em->persist($existing);
                    $em->flush($existing);
                    $em->detach($existing);
                    $createNew = false;
                    break;
                }
            }
            if ($createNew === true) {
                // A notification for a new locale is required
                $n = NotificationEntity::create(
                    TranslatableComment::class,
                    [
                        'localeID' => $localeID,
                        'comments' => [
                            $commentID => $thisNotificationData,
                        ],
                    ]
                );
                $em->persist($n);
                $em->flush($n);
                $em->detach($n);
            }
        }
    }

    /**
     * @param LocaleEntity $locale
     * @param int $numTranslations
     * @param int $translatorUserID
     * @param int|null $packageVersionID
     */
    public function translationsNeedApproval(LocaleEntity $locale, $numTranslations, $translatorUserID = null, $packageVersionID = null)
    {
        $em = $this->getEntityManager();
        $localeID = $locale->getID();
        $numTranslations = (int) $numTranslations;
        $createNew = true;
        $userKey = (int) ($translatorUserID ?: USER_SUPER_ID);
        $packageVersionID = $packageVersionID ? (int) $packageVersionID : null;
        foreach ($this->findBy(['fqnClass' => TranslationsNeedApproval::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['localeID'] === $localeID) {
                if ($data['packageVersionID'] !== $packageVersionID) {
                    $data['packageVersionID'] = null;
                }
                if (isset($data['numTranslations'][$userKey])) {
                    $data['numTranslations'][$userKey] += $numTranslations;
                } else {
                    $data['numTranslations'][$userKey] = $numTranslations;
                }
                $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                $em->persist($existing);
                $em->flush($existing);
                $em->detach($existing);
                $createNew = false;
                break;
            }
        }
        if ($createNew) {
            $n = NotificationEntity::create(
                TranslationsNeedApproval::class,
                [
                    'localeID' => $localeID,
                    'packageVersionID' => $packageVersionID,
                    'numTranslations' => [
                        $userKey => $numTranslations,
                    ],
                ]
            );
            $em->persist($n);
            $em->flush($n);
            $em->detach($n);
        }
    }

    /**
     * @param int $packageID
     * @param int $recipientUserID
     */
    public function newTranslatablePackage($packageID, $recipientUserID)
    {
        $em = $this->getEntityManager();
        $packageID = (int) $packageID;
        $recipientUserID = (int) $recipientUserID;
        $createNew = true;
        foreach ($this->findBy(['fqnClass' => NewTranslatablePackage::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] === $recipientUserID) {
                $data['packageIDs'][] = $packageID;
                $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                $em->persist($existing);
                $em->flush($existing);
                $em->detach($existing);
                $createNew = false;
                break;
            }
        }
        if ($createNew) {
            $n = NotificationEntity::create(
                NewTranslatablePackage::class,
                [
                    'userID' => $recipientUserID,
                    'packageIDs' => [
                        $packageID,
                    ],
                ]
            );
            $em->persist($n);
            $em->flush($n);
            $em->detach($n);
        }
    }

    /**
     * @param int $packageVersionID
     * @param int $recipientUserID
     */
    public function newTranslatablePackageVersion($packageVersionID, $recipientUserID)
    {
        $em = $this->getEntityManager();
        $packageVersionID = (int) $packageVersionID;
        $recipientUserID = (int) $recipientUserID;
        $createNew = true;
        foreach ($this->findBy(['fqnClass' => NewTranslatablePackageVersion::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] === $recipientUserID) {
                $data['packageVersionIDs'][] = $packageVersionID;
                $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                $em->persist($existing);
                $em->flush($existing);
                $em->detach($existing);
                $createNew = false;
                break;
            }
        }
        if ($createNew) {
            $n = NotificationEntity::create(
                NewTranslatablePackageVersion::class,
                [
                    'userID' => $recipientUserID,
                    'packageVersionIDs' => [
                        $packageVersionID,
                    ],
                ]
                );
            $em->persist($n);
            $em->flush($n);
            $em->detach($n);
        }
    }

    /**
     * @param int $packageVersionID
     * @param int $recipientUserID
     */
    public function updatedTranslatablePackageVersion($packageVersionID, $recipientUserID)
    {
        $em = $this->getEntityManager();
        $packageVersionID = (int) $packageVersionID;
        $recipientUserID = (int) $recipientUserID;
        $createNew = true;
        foreach ($this->findBy(['fqnClass' => UpdatedTranslatablePackageVersion::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if ($data['userID'] === $recipientUserID) {
                if (!in_array($packageVersionID, $data['packageVersionIDs'])) {
                    $data['packageVersionIDs'][] = $packageVersionID;
                    $existing->setNotificationData($data)->setUpdatedOn(new DateTime());
                    $em->persist($existing);
                    $em->flush($existing);
                }
                $em->detach($existing);
                $createNew = false;
                break;
            }
        }
        if ($createNew) {
            $n = NotificationEntity::create(
                UpdatedTranslatablePackageVersion::class,
                [
                    'userID' => $recipientUserID,
                    'packageVersionIDs' => [
                        $packageVersionID,
                    ],
                ]
                );
            $em->persist($n);
            $em->flush($n);
            $em->detach($n);
        }
    }
}
