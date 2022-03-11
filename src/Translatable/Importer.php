<?php

declare(strict_types=1);

namespace CommunityTranslation\Translatable;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Service\SourceLocale;
use Concrete\Core\Application\Application;
use Concrete\Core\Events\EventDispatcher;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Gettext\Translations;
use Symfony\Component\EventDispatcher\GenericEvent;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class Importer
{
    private const IMPORT_BATCH_SIZE = 50;

    private Application $app;

    private EntityManager $em;

    private ParserInterface $parser;

    private EventDispatcher $events;

    public function __construct(Application $app, EntityManager $em, ParserInterface $parser)
    {
        $this->app = $app;
        $this->em = $em;
        $this->parser = $parser;
        $this->events = $this->app->make(EventDispatcher::class);
    }

    /**
     * Parse a directory and extract the translatable strings, and import them into the database.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return bool returns true if some translatable strings changed, false otherwise
     */
    public function importDirectory(string $directory, string $packageHandle, string $packageVersion, string $basePlacesDirectory): bool
    {
        $parsed = $this->parser->parseDirectory($packageHandle, $packageVersion, $directory, $basePlacesDirectory, ParserInterface::DICTIONARY_NONE);
        $translations = $parsed ? $parsed->getSourceStrings() : null;
        if ($translations === null) {
            $sourceLocale = $this->app->make(SourceLocale::class)->getRequiredSourceLocale();
            $translations = new Translations();
            $translations->setLanguage($sourceLocale->getID());
            $translations->setPluralForms($sourceLocale->getPluralCount(), $sourceLocale->getPluralFormula());
        }

        return $this->importTranslations($translations, $packageHandle, $packageVersion);
    }

    /**
     * Import translatable strings into the database.
     *
     * This function works directly with the database, not with entities (so that working on thousands of strings requires seconds instead of minutes).
     * This implies that entities related to Translatable may become invalid.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return bool returns true if some translatable strings changed, false otherwise
     */
    public function importTranslations(Translations $translations, string $packageHandle, string $packageVersion): bool
    {
        $packageIsNew = true;
        $someStringChanged = false;
        $numStringsAdded = 0;
        $version = null;
        $this->em->getConnection()->transactional(function (Connection $connection) use ($translations, $packageHandle, $packageVersion, & $packageIsNew, & $numStringsAdded, & $version) {
            $packageRepo = $this->em->getRepository(PackageEntity::class);
            $package = $packageRepo->findOneBy(['handle' => $packageHandle]);
            $version = null;
            if ($package === null) {
                $package = new PackageEntity($packageHandle);
                $this->em->persist($package);
                $this->em->flush($package);
            } else {
                foreach ($package->getVersions() as $pv) {
                    $packageIsNew = false;
                    if (version_compare($pv->getVersion(), $packageVersion) === 0) {
                        $version = $pv;
                        break;
                    }
                }
            }
            if ($version === null) {
                $packageVersionIsNew = true;
                $version = new PackageVersionEntity($package, $packageVersion);
                $this->em->persist($version);
                $this->em->flush($version);
            } else {
                $packageVersionIsNew = false;
            }
            $searchHash = $connection->prepare('SELECT id FROM CommunityTranslationTranslatables WHERE hash = ? LIMIT 1')->getWrappedStatement();
            $insertTranslatable = $connection->prepare('INSERT INTO CommunityTranslationTranslatables SET hash = ?, context = ?, text = ?, plural = ?')->getWrappedStatement();
            $insertPlaces = $connection->prepare($this->buildInsertTranslatablePlacesSQL($version, self::IMPORT_BATCH_SIZE))->getWrappedStatement();
            if ($packageVersionIsNew) {
                $prevHash = null;
            } else {
                $prevHash = (string) $connection->fetchOne(
                    'select md5(group_concat(translatable)) from CommunityTranslationTranslatablePlaces where packageVersion = ? order by translatable',
                    [$version->getID()]
                );
                $connection->executeStatement(
                    'delete from CommunityTranslationTranslatablePlaces where packageVersion = ?',
                    [$version->getID()]
                );
            }
            $insertPlacesParams = [];
            $insertPlacesCount = 0;
            $importCount = 0;
            foreach ($translations as $translationKey => $translation) {
                /** @var \Gettext\Translation $translation */
                $plural = $translation->getPlural();
                $hash = TranslatableEntity::generateHashFromGettextKey($translationKey, $plural);
                $searchHash->execute([$hash]);
                $translatableID = $searchHash->fetchOne();
                if ($translatableID === false) {
                    $insertTranslatable->execute([
                        $hash,
                        $translation->getContext(),
                        $translation->getOriginal(),
                        $plural,
                    ]);
                    $translatableID = (int) $connection->lastInsertId();
                    $someStringChanged = true;
                    $numStringsAdded++;
                } else {
                    $translatableID = (int) $translatableID;
                }
                // translatable
                $insertPlacesParams[] = $translatableID;
                // locations
                $locations = [];
                foreach ($translation->getReferences() as $tr) {
                    $locations[] = isset($tr[1]) ? implode(':', $tr) : $tr[0];
                }
                $insertPlacesParams[] = serialize($locations);
                // comments
                $insertPlacesParams[] = serialize($translation->getExtractedComments());
                // sort
                $insertPlacesParams[] = $importCount;
                $insertPlacesCount++;
                if ($insertPlacesCount === self::IMPORT_BATCH_SIZE) {
                    $insertPlaces->execute($insertPlacesParams);
                    $insertPlacesParams = [];
                    $insertPlacesCount = 0;
                }
                $importCount++;
            }
            if ($insertPlacesCount > 0) {
                $connection->executeStatement(
                    $this->buildInsertTranslatablePlacesSQL($version, $insertPlacesCount),
                    $insertPlacesParams
                );
            }
            if ($someStringChanged === false && !$packageVersionIsNew) {
                $newHash = (string) $connection->fetchOne(
                    'select md5(group_concat(translatable)) from CommunityTranslationTranslatablePlaces where packageVersion = ? order by translatable',
                    [$version->getID()]
                );
                if ($newHash !== $prevHash) {
                    $someStringChanged = true;
                }
            }
            if ($someStringChanged) {
                $version->setUpdatedOn(new DateTimeImmutable());
                $this->em->persist($version);
                $this->em->flush($version);
                $this->em->clear(TranslatableEntity::class);
            }
        });
        if ($someStringChanged) {
            try {
                $this->events->dispatch(
                    'community_translation.translatableUpdated',
                    new GenericEvent(
                        $version,
                        [
                            'packageIsNew' => $packageIsNew,
                            'numStringsAdded' => $numStringsAdded,
                        ]
                    )
                );
            } catch (Throwable $foo) {
            }
        }

        return $someStringChanged;
    }

    private function buildInsertTranslatablePlacesSQL(PackageVersionEntity $packageVersion, int $numRecords): string
    {
        $fields = '(packageVersion, translatable, locations, comments, sort)';
        $values = '(' . $packageVersion->getID() . ', ?, ?, ?, ?),';
        $sql = 'INSERT INTO CommunityTranslationTranslatablePlaces ';
        $sql .= ' ' . $fields;
        $sql .= ' VALUES ' . rtrim(str_repeat($values, $numRecords), ',');

        return $sql;
    }
}
