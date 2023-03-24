<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Attribute\SubscribedPackages;

use CommunityTranslation\Entity\PackageSubscription;
use CommunityTranslation\Entity\PackageVersionSubscription;
use Concrete\Core\Attribute\Controller as AttributeTypeController;
use Concrete\Core\Attribute\FontAwesomeIconFormatter;
use Concrete\Core\Attribute\Value\EmptyRequestAttributeValue;
use Concrete\Core\Entity\User\User;
use Concrete\Core\User\UserInfo;
use Concrete\Core\Validation\CSRF\Token;
use Concrete\Core\View\View;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends AttributeTypeController
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::getIconFormatter()
     */
    public function getIconFormatter()
    {
        return new FontAwesomeIconFormatter('bell');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::getSearchIndexValue()
     */
    public function getSearchIndexValue()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::searchKeywords()
     */
    public function searchKeywords($keywords, $queryBuilder)
    {
        return null;
    }

    public function form()
    {
        $user = $this->resolveUserEntity();
        if ($user === null) {
            $this->set('error', t('Failed to retrieve the user'));

            return;
        }
        $view = View::getInstance();
        $view->addHeaderItem(
            <<<'EOT'
<style>
[v-cloak] {
    display: none;
}
</style>
EOT
        );
        $view->requireAsset('javascript', 'vue');
        $this->set('token', $this->app->make(Token::class));
        $this->set('user', $user);
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $qb
            ->from(PackageSubscription::class, 'ps')
            ->innerJoin('ps.package', 'p')
            ->select('ps', 'p')
            ->andWhere('ps.user = :user')->setParameter('user', $user)
            ->andWhere('ps.notifyNewVersions = :true')->setParameter('true', true)
        ;
        $this->set('packageSubscriptions', $qb->getQuery()->execute());
        $qb = $em->createQueryBuilder();
        $qb
            ->from(PackageVersionSubscription::class, 'pvs')
            ->innerJoin('pvs.packageVersion', 'pv')
            ->innerJoin('pv.package', 'p')
            ->select('pvs', 'pv', 'p')
            ->andWhere('pvs.user = :user')->setParameter('user', $user)
            ->andWhere('pvs.notifyUpdates = :true')->setParameter('true', true)
        ;
        $this->set('packageVersionSubscriptions', $qb->getQuery()->execute());
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::createAttributeValueFromRequest()
     */
    public function createAttributeValueFromRequest()
    {
        $data = $this->post();
        if (is_array($data) && !empty($data['unsubscribe-all'])) {
            $user = $this->resolveUserEntity($data);
            if ($user !== null) {
                $this->app->make(EntityManagerInterface::class)->wrapInTransaction(static function (EntityManagerInterface $em) use ($user) {
                    $em->createQueryBuilder()
                        ->delete(PackageVersionSubscription::class, 'pvs')
                        ->andWhere('pvs.user = :user')->setParameter('user', $user)
                        ->getQuery()->execute()
                    ;
                    $em->createQueryBuilder()
                        ->delete(PackageSubscription::class, 'ps')
                        ->andWhere('ps.user = :user')->setParameter('user', $user)
                        ->getQuery()->execute()
                    ;
                });
            }
        }

        return new EmptyRequestAttributeValue();
    }

    /**
     * @return \Concrete\Core\Entity\User\User|null
     */
    private function resolveUserEntity(array $data = [])
    {
        if ($this->attributeObject instanceof UserInfo) {
            return $this->attributeObject->getEntityObject() ?: null;
        }
        $userID = $data['user-id'] ?? null;
        if (is_numeric($userID)) {
            $userID = (int) $userID;
            $userIDToken = $data['user-id-token'] ?? '';
            $token = $this->app->make(Token::class);
            if ($token->validate("u{$userID}", $userIDToken)) {
                return $this->app->make(EntityManagerInterface::class)->find(User::class, $userID);
            }
        }

        return null;
    }
}
