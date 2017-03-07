<?php
namespace CommunityTranslation\Repository;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Notification as NotificationEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
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
     * @param string $notes
     */
    public function newLocaleRequested(LocaleEntity $locale, $notes = '')
    {
        $n = NotificationEntity::create(
            'new_locale_requested',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS,
            [
                'notes' => (string) $notes,
            ],
            $locale,
            $locale->getRequestedBy()
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     */
    public function newLocaleRequestApprovedByCurrentUser(LocaleEntity $locale)
    {
        $n = NotificationEntity::create(
            'new_locale_approved',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS | NotificationEntity::RECIPIENT_USER,
            [
                'by' => $this->getCurrentUserID(true),
            ],
            $locale,
            $locale->getRequestedBy()
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     */
    public function newLocaleRequestRejectedByCurrentUser(LocaleEntity $locale)
    {
        $n = NotificationEntity::create(
            'new_locale_rejected',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS | NotificationEntity::RECIPIENT_USER,
            [
                'by' => $this->getCurrentUserID(true),
                'localeID' => $locale->getID(),
                'requestedBy' => $locale->getRequestedBy() ? $locale->getRequestedBy()->getUserID() : null,
            ],
            null,
            null
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     */
    public function newTeamJoinRequestFromCurrentUser(LocaleEntity $locale)
    {
        $n = NotificationEntity::create(
            'new_team_join_request',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS,
            [],
            $locale,
            $this->getCurrentUser(true)
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param UserEntity $translator
     */
    public function newTranslatorApprovedByCurrentUser(LocaleEntity $locale, UserEntity $translator)
    {
        $n = NotificationEntity::create(
            'new_translator_approved',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS | NotificationEntity::RECIPIENT_USER,
            [
                'by' => $this->getCurrentUserID(true),
            ],
            $locale,
            $translator
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    public function newTranslatorApprovedAutomatically(LocaleEntity $locale, UserEntity $translator)
    {
        $n = NotificationEntity::create(
            'new_translator_approved',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS | NotificationEntity::RECIPIENT_USER,
            [
                'by' => USER_SUPER_ID,
                'automatic' => true,
            ],
            $locale,
            $translator
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param UserEntity $translator
     */
    public function newTranslatorRejectedByCurrentUser(LocaleEntity $locale, UserEntity $translator)
    {
        $n = NotificationEntity::create(
            'new_translator_rejected',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS | NotificationEntity::RECIPIENT_USER,
            [
                'by' => $this->getCurrentUserID(true),
            ],
            $locale,
            $translator
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param LocaleEntity $locale
     * @param UserEntity $translator
     */
    public function translationsNeedApproval(LocaleEntity $locale, $numTranslations, PackageVersionEntity $packageVersion = null, UserEntity $translator = null)
    {
        $n = NotificationEntity::create(
            'new_translator_rejected',
            NotificationEntity::RECIPIENT_GLOBAL_ADMINISTRATORS | NotificationEntity::RECIPIENT_LOCALE_ADMINISTRATORS,
            [
                'by' => $translator ? $translator->getUserID() : null,
                'numTranslations' => $numTranslations,
                'packageHandle' => $packageVersion ? $packageVersion->getPackage()->getHandle() : null,
                'packageVersion' => $packageVersion ? $packageVersion->getVersion() : null,
            ],
            $locale
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }

    /**
     * @param TranslatableCommentEntity $comment
     */
    public function newTranslatableCommentSubmitted(TranslatableCommentEntity $comment)
    {
        $n = NotificationEntity::create(
            'new_translatable_comment',
            NotificationEntity::RECIPIENT_UNSPECIFIED,
            [
                'commentID' => $comment->getID(),
            ],
            $locale
        );
        $em = $this->getEntityManager();
        $em->persist($n);
        $em->flush($n);
    }
}
