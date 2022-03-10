<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Entity\RemotePackage as RemotePackageEntity;
use CommunityTranslation\RemotePackage\Importer as RemotePackageImporter;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManager;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Accept a package version to be imported and queue it for later processing.
 *
 * @example PUT request to http://www.example.com/api/import/package/ with this body (it's in JSON format):
 * {
 *   "package_handle": "...", // Required
 *   "package_version": "...",  // Required
 *   "archive_url": "...", // Required
 *   "package_name": "...", // Optional
 *   "package_url": "...", // Optional
 *   "approved": true/false // Optional
 *   "immediate": true/false // Optional
 * }
 */
class ImportPackage extends EntryPoint
{
    public const ACCESS_KEY = 'importPackage';

    public function __invoke(): Response
    {
        return $this->handle(
            function (): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $args = $this->getRequestJson();
                $entity = $this->createRemoteRepository($args);
                if (array_key_exists('immediate', $args)) {
                    if (!is_bool($args['immediate'])) {
                        throw new UserMessageException(t('Invalid type of argument: %s', 'immediate'), Response::HTTP_NOT_ACCEPTABLE);
                    }
                    $immediate = $args['immediate'];
                } else {
                    $immediate = false;
                }
                $this->app->make(EntityManager::class)->transactional(static function (EntityManager $em) use ($entity, $immediate) {
                    $repo = $em->getRepository(RemotePackageEntity::class);
                    // Remove duplicated packages still to be processed
                    $qb = $repo->createQueryBuilder('rp');
                    $qb
                        ->delete()
                        ->where('rp.handle = :handle')->setParameter('handle', $entity->getHandle())
                        ->andWhere('rp.version = :version')->setParameter('version', $entity->getVersion())
                        ->andWhere($qb->expr()->isNull('rp.processedOn'))
                        ->getQuery()->execute()
                    ;
                    if ($entity->isApproved()) {
                        // Approve previously queued packages that were'nt approved
                        $qb = $repo->createQueryBuilder('rp');
                        $qb
                            ->update()
                            ->set('rp.approved', true)
                            ->where('rp.handle = :handle')->setParameter('handle', $entity->getHandle())
                            ->andWhere('rp.approved = :approved')->setParameter('approved', false)
                            ->andWhere($qb->expr()->isNull('rp.processedOn'))
                            ->getQuery()->execute()
                        ;
                    }
                    if ($immediate === false) {
                        $em->persist($entity);
                        $em->flush($entity);
                    }
                });
                if (!$immediate) {
                    return $this->buildJsonResponse('queued');
                }
                if (!$entity->isApproved()) {
                    return $this->buildJsonResponse('skipped');
                }
                $importer = $this->app->make(RemotePackageImporter::class);
                $importer->import($entity);

                return $this->buildJsonResponse('imported');
            }
        );
    }

    /**
     * Check if the request is a JSON request and returns the posted parameters.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getRequestJson(): array
    {
        if ($this->request->getContentType() !== 'json') {
            throw new UserMessageException(t('Invalid request Content-Type: %s', $this->request->headers->get('Content-Type', '')), Response::HTTP_NOT_ACCEPTABLE);
        }
        $contentBody = $this->request->getContent();
        try {
            $contentJson = json_decode($contentBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $x) {
            $contentJson = null;
        }
        if (!is_array($contentJson)) {
            throw new UserMessageException(t('Failed to parse the request body as JSON'), Response::HTTP_NOT_ACCEPTABLE);
        }

        return $contentJson;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function createRemoteRepository(array $data): RemotePackageEntity
    {
        $package_handle = is_string($data['package_handle'] ?? null) ? trim($data['package_handle']) : '';
        if ($package_handle === '') {
            throw new UserMessageException(t('Missing argument: %s', 'package_handle'), Response::HTTP_NOT_ACCEPTABLE);
        }
        $package_version = is_string($data['package_version'] ?? null) ? trim($data['package_version']) : '';
        if ($package_version === '') {
            throw new UserMessageException(t('Missing argument: %s', 'package_version'), Response::HTTP_NOT_ACCEPTABLE);
        }
        $archive_url = is_string($data['archive_url'] ?? '') ? trim($data['archive_url']) : '';
        if ($archive_url === '') {
            throw new UserMessageException(t('Missing argument: %s', 'archive_url'), Response::HTTP_NOT_ACCEPTABLE);
        }
        $entity = new RemotePackageEntity($package_handle, $package_version, $archive_url);
        if (array_key_exists('package_name', $data)) {
            if (!is_string($data['package_name'])) {
                throw new UserMessageException(t('Invalid type of argument: %s', 'package_name'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $entity->setName(trim($data['package_name']));
        }
        if (array_key_exists('package_url', $data)) {
            if (!is_string($data['package_url'])) {
                throw new UserMessageException(t('Invalid type of argument: %s', 'package_url'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $entity->setUrl(trim($data['package_url']));
        }
        if (array_key_exists('approved', $data)) {
            if (!is_bool($data['approved'])) {
                throw new UserMessageException(t('Invalid type of argument: %s', 'approved'), Response::HTTP_NOT_ACCEPTABLE);
            }
            $entity->setIsApproved($data['approved']);
        }

        return $entity;
    }
}
