<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use CommunityTranslation\Repository\RemotePackage as RemotePackageRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Localization\Service\Date as DateService;
use Concrete\Core\Page\Controller\DashboardPageController;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class RemotePackagesLog extends DashboardPageController
{
    private const PAGE_SIZE = 50;

    public function view(): ?Response
    {
        $this->set('remotePackages', $this->fetchRemotePackages());
        $this->set('token', $this->token);

        return null;
    }

    public function get_next_page(): Response
    {
        if (!$this->token->validate('comtra-repa-nextpage')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $createdOnDB = $this->request->request->get('createdOnDB');
        if (!is_string($createdOnDB) || $createdOnDB === '') {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        set_error_handler(static function () {}, -1);
        try {
            $createdOnDBOk = DateTimeImmutable::createFromFormat($dbDateTimeFormat, $createdOnDB);
        } finally {
            restore_error_handler();
        }
        if ($createdOnDBOk === false) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $records = $this->fetchRemotePackages([
            'id' => $id,
            'createdOn' => $createdOnDBOk,
        ]);

        return $this->app->make(ResponseFactoryInterface::class)->json($records);
    }

    public function refresh_remote_package(): Response
    {
        if (!$this->token->validate('comtra-repa-refresh1')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $remotePackage = $em->find(RemotePackageEntity::class, $id);
        if ($remotePackage === null) {
            throw new UserMessageException(t('Unable to find the remote package requested.'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeRemotePackage($remotePackage));
    }

    public function import_remote_package(): Response
    {
        if (!$this->token->validate('comtra-repa-import1')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_numeric($id) ? (int) $id : 0;
        if ($id <= 0) {
            throw new UserMessageException(t('Invalid parameter received'));
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $remotePackage = $em->find(RemotePackageEntity::class, $id);
        if ($remotePackage === null) {
            throw new UserMessageException(t('Unable to find the remote package requested.'));
        }
        $remotePackageImporter = $this->app->make(RemotePackageImporter::class);
        try {
            $em->getConnection()->transactional(function () use ($remotePackage, $em, $remotePackageImporter) {
                $remotePackage->setProcessedOn(new DateTimeImmutable());
                $em->flush();
                $remotePackageImporter->import($remotePackage);
            });
        } catch (Throwable $error) {
            $remotePackage->setProcessedOn(null);
            $remotePackage
                ->setFailCount($remotePackage->getFailCount() + 1)
                ->setLastError($error->getMessage())
            ;
            $em->flush();
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeRemotePackage($remotePackage));
    }

    private function fetchRemotePackages(?array $lastLoaded = null): array
    {
        $repo = $this->app->make(RemotePackageRepository::class);
        $expr = Criteria::expr();
        $criteria = Criteria::create();
        $criteria
            ->orderBy([
                'createdOn' => 'DESC',
                'id' => 'DESC',
            ])
            ->setMaxResults(self::PAGE_SIZE)
        ;
        if ($lastLoaded !== null) {
            $criteria->andWhere($expr->orX(
                $expr->lt('createdOn', $lastLoaded['createdOn']),
                $expr->andX(
                    $expr->eq('createdOn', $lastLoaded['createdOn']),
                    $expr->lt('id', $lastLoaded['id']),
                )
            ));
        }
        $result = [];
        $dateService = $this->app->make(DateService::class);
        $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        foreach ($repo->matching($criteria) as $remotePackage) {
            $result[] = $this->serializeRemotePackage($remotePackage, $dbDateTimeFormat, $dateService);
        }

        return $result;
    }

    private function serializeRemotePackage(RemotePackageEntity $remotePackage, string $dbDateTimeFormat = '', ?DateService $dateService = null): array
    {
        if ($dbDateTimeFormat === '') {
            $dbDateTimeFormat = $this->app->make(EntityManagerInterface::class)->getConnection()->getDatabasePlatform()->getDateTimeFormatString();
        }
        if ($dateService === null) {
            $dateService = $this->app->make(DateService::class);
        }

        return [
            'id' => $remotePackage->getID(),
            'handle' => $remotePackage->getHandle(),
            'name' => $remotePackage->getName(),
            'approved' => $remotePackage->isApproved(),
            'url' => $remotePackage->getUrl(),
            'version' => $remotePackage->getVersion(),
            'archiveUrl' => $remotePackage->getArchiveUrl(),
            'createdOn' => $dateService->formatPrettyDateTime($remotePackage->getCreatedOn(), true),
            'createdOnDB' => $remotePackage->getCreatedOn()->format($dbDateTimeFormat),
            'processedOn' => $remotePackage->getProcessedOn() === null ? null : $dateService->formatPrettyDateTime($remotePackage->getProcessedOn(), true),
            'failCount' => $remotePackage->getFailCount(),
            'lastError' => $remotePackage->getLastError(),
        ];
    }
}
