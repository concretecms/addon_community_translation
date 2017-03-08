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
use CommunityTranslation\Notification\Category\NewTranslatorApproved;
use CommunityTranslation\Notification\Category\NewTranslatorRejected;
use CommunityTranslation\Notification\Category\TranslatableComment;
use CommunityTranslation\Notification\Category\TranslationsNeedApproval;
use CommunityTranslation\UserException;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\User\User;
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
            throw new UserException(t('No logged in user'));
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
            throw new UserException(t('No logged in user'));
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
                'rejectedByUserID' => (int) $approvedByUserID,
            ]
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param TranslatableCommentEntity $comment
     */
    public function translatableCommentSubmitted(TranslatableCommentEntity $comment)
    {
        $em = $this->getEntityManager();
        $localeID = $comment->getLocale() ? $comment->getLocale()->getID() : null;
        $commentID = $comment->getID();
        $createNew = true;
        foreach ($this->findBy(['classHandle' => TranslatableComment::class, 'sentOn' => null]) as $existing) {
            $data = $existing->getNotificationData();
            if (in_array($commentID, $data['commentIDs'], true)) {
                if ($data['localeID'] === $localeID) {
                    $createNew = false;
                } else {
                    $otherMessageIDs = array_diff($data['commentIDs'], [$commentID]);
                    if (empty($otherMessageIDs)) {
                        $data['localeID'] = $localeID;
                        $existing->setNotificationData($data);
                        $em->persist($existing);
                        $em->flush($existing);
                        $createNew = false;
                    } else {
                        $data['commentIDs'] = array_values($otherMessageIDs);
                        $existing->setNotificationData($data);
                        $em->persist($existing);
                        $em->flush($existing);
                    }
                }
                break;
            }
        }
        if ($createNew) {
            $n = NotificationEntity::create(
                TranslatableComment::class,
                [
                    'commentIDs' => [$commentID],
                    'localeID' => $localeID,
                ]
            );
            $em->persist($n);
            $em->flush($n);
        }
    }

    /**
     * @param LocaleEntity $locale
     * @param int $numTranslations
     * @param int $translatorUserID
     * @param PackageVersionEntity $packageVersion
     */
    public function translationsNeedApproval(LocaleEntity $locale, $numTranslations, $translatorUserID = null, PackageVersionEntity $packageVersion = null)
    {
        $em = $this->getEntityManager();
        $localeID = $locale->getID();
        $packageVersionID = ($packageVersion === null) ? null : $packageVersion->getID();
        $numTranslations = (int) $numTranslations;
        $createNew = true;
        $userKey = (int) ($translatorUserID ?: USER_SUPER_ID);
        foreach ($this->findBy(['classHandle' => TranslationsNeedApproval::class, 'sentOn' => null]) as $existing) {
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
                $existing->setNotificationData($data);
                $em->persist($existing);
                $em->flush($existing);
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
        }
    }
}
