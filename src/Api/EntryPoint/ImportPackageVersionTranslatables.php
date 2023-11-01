<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Translatable\Importer as TranslatableImporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Set the translatable strings of a package version.
 *
 * @example POST request to http://www.example.com/api/package/concrete5/8.2.0/translatables/po in multipart/form-data format with these fields:
 * - file: the file containing the translatable strings (required)
 * - packageName: the name of the package (required when creating a new package, optional if the package already exists)
 */
class ImportPackageVersionTranslatables extends EntryPoint
{
    public const ACCESS_KEY = 'importPackageVersionTranslatables';

    public function __invoke(string $packageHandle, string $packageVersion, string $formatHandle): Response
    {
        return $this->handle(
            function () use ($packageHandle, $packageVersion, $formatHandle): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                if ($packageHandle === '') {
                    throw new UserMessageException(t('Package handle not specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                if ($packageVersion === '') {
                    throw new UserMessageException(t('Package version not specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                if ($formatHandle === '') {
                    throw new UserMessageException(t('Translations format handle not specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $file = $this->request->files->get('file');
                if ($file === null) {
                    throw new UserMessageException(t('The file with the translatable strings has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                if (!$file->isValid()) {
                    throw new UserMessageException(t('The file with the translatable strings has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
                }
                $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
                if ($format === null) {
                    throw new UserMessageException(t('Unable to find the specified translations format'), 404);
                }
                $translations = $format->loadTranslationsFromFile($file->getPathname());
                if (count($translations) < 1) {
                    throw new UserMessageException(t('No translatable strings found in the received file'));
                }
                $packageName = $this->request->request->get('packageName', '');
                $packageName = is_string($packageName) ? trim($packageName) : '';
                $package = $this->app->make(PackageRepository::class)->getByHandle($packageHandle);
                $em = $this->app->make(EntityManager::class);
                if ($package === null) {
                    if ($packageName === '') {
                        throw new UserMessageException(t('The package with handle "%1$s" does not exist yet, so you need to specify the "%2$s" field in the request body.', $packageHandle, 'packageName'), Response::HTTP_NOT_ACCEPTABLE);
                    }
                    $package = new PackageEntity($packageHandle, $packageName);
                    $em->persist($package);
                    $version = null;
                } else {
                    if ($packageName !== '') {
                        $package->setName($packageName);
                    }
                    $version = $this->app->make(PackageVersionRepository::class)->findByOneBy(['package' => $package, 'version' => $packageVersion]);
                }
                $package->setFromApiRequest(true);
                $em->flush();
                if ($version === null) {
                    $version = new PackageVersionEntity($package, $packageVersion);
                    $em->persist($version);
                    $em->flush($version);
                }
                $importer = $this->app->make(TranslatableImporter::class);
                $changed = $importer->importTranslations($translations, $package->getHandle(), $packageVersion->getVersion());

                return $this->buildJsonResponse(['changed' => $changed]);
            }
        );
    }
}
