<?php
namespace CommunityTranslation\Translatable;

use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Parser\ParserInterface;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Gettext\Translations;
use Symfony\Component\EventDispatcher\GenericEvent;

class Importer
{
    const IMPORT_BATCH_SIZE = 50;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Entity manager.
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * Parser.
     *
     * @var ParserInterface
     */
    protected $parser;

    /**
     * The events director.
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $events;

    /**
     * @param Application $app
     * @param EntityManager $em
     * @param ParserInterface $parser
     */
    public function __construct(Application $app, EntityManager $em, ParserInterface $parser)
    {
        $this->app = $app;
        $this->em = $em;
        $this->parser = $parser;
        $this->events = $this->app->make('director');
    }

    /**
     * Parse a directory and extract the translatable strings, and import them into the database.
     *
     * @param string $directory
     * @param string $packageHandle
     * @param string $packageVersion
     * @param string $basePlacesDirectory
     *
     * @throws UserException
     *
     * @return bool returns true if some translatable strings changed, false otherwise
     */
    public function importDirectory($directory, $packageHandle, $packageVersion, $basePlacesDirectory)
    {
        $parsed = $this->parser->parseDirectory($packageHandle, $packageVersion, $directory, $basePlacesDirectory, ParserInterface::DICTIONARY_NONE);
        $translations = $parsed ? $parsed->getSourceStrings() : null;
        if ($translations === null) {
            $translations = new Translations();
            $translations->setLanguage($this->app->make('community_translation/sourceLocale'));
        }

        return $this->importTranslations($translations, $packageHandle, $packageVersion);
    }

    /**
     * @param int $packageVersionID
     * @param int $numRecords
     *
     * @return string
     */
    private function buildInsertTranslatablePlacesSQL(PackageVersionEntity $packageVersion, $numRecords)
    {
        $fields = '(packageVersion, translatable, locations, comments, sort)';
        $values = '(' . $packageVersion->getID() . ', ?, ?, ?, ?),';
        $sql = 'INSERT INTO CommunityTranslationTranslatablePlaces ';
        $sql .= ' ' . $fields;
        $sql .= ' VALUES ' . rtrim(str_repeat($values, $numRecords), ',');

        return $sql;
    }

    /**
     * Import translatable strings into the database.
     *
     * This function works directly with the database, not with entities (so that working on thousands of strings requires seconds instead of minutes).
     * This implies that entities related to Translatable may become invalid.
     *
     * @param Translations $translations The strings to be imported
     * @param string $packageHandle The package handle
     * @param string $packageVersion The package version
     *
     * @throws UserException
     *
     * @return bool returns true if some translatable strings changed, false otherwise
     */
    public function importTranslations(Translations $translations, $packageHandle, $packageVersion)
    {
        $packageRepo = $this->app->make(PackageRepository::class);
        $someStringChanged = false;
        $numStringsAdded = 0;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $package = $packageRepo->findOneBy(['handle' => $packageHandle]);
            if ($package === null) {
                $package = PackageEntity::create($packageHandle);
                $this->em->persist($package);
                $this->em->flush($package);
            }
            $version = null;
            $packageIsNew = true;
            foreach ($package->getVersions() as $pv) {
                $packageIsNew = false;
                if (version_compare($pv->getVersion(), $packageVersion) === 0) {
                    $version = $pv;
                    break;
                }
            }
            if ($version === null) {
                $packageVersionIsNew = true;
                $version = PackageVersionEntity::create($package, $packageVersion);
                $this->em->persist($version);
                $this->em->flush($version);
            } else {
                $packageVersionIsNew = false;
            }

            $searchHash = $connection->prepare('SELECT id FROM CommunityTranslationTranslatables WHERE hash = ? LIMIT 1')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $searchHash */
            $insertTranslatable = $connection->prepare('INSERT INTO CommunityTranslationTranslatables SET hash = ?, context = ?, text = ?, plural = ?')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertTranslatable */
            $insertPlaces = $connection->prepare($this->buildInsertTranslatablePlacesSQL($version, self::IMPORT_BATCH_SIZE))->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertPlaces */
            if ($packageVersionIsNew) {
                $prevHash = null;
            } else {
                $prevHash = (string) $connection->fetchColumn(
                    'select md5(group_concat(translatable)) from CommunityTranslationTranslatablePlaces where packageVersion = ? order by translatable',
                    [$version->getID()]
                );
                $connection->executeQuery(
                    'delete from CommunityTranslationTranslatablePlaces where packageVersion = ?',
                    [$version->getID()]
                );
            }
            $insertPlacesParams = [];
            $insertPlacesCount = 0;
            $importCount = 0;
            foreach ($translations as $translationKey => $translation) {
                /* @var \Gettext\Translation $translation */
                $plural = $translation->getPlural();
                $hash = md5(($plural === '') ? $translationKey : "$translationKey\005$plural");
                $searchHash->execute([$hash]);
                $translatableID = $searchHash->fetchColumn(0);
                $searchHash->closeCursor();
                if ($translatableID === false) {
                    $insertTranslatable->execute([
                        $hash,
                        $translation->getContext(),
                        $translation->getOriginal(),
                        $plural,
                    ]);
                    $translatableID = (int) $connection->lastInsertId();
                    $someStringChanged = true;
                    ++$numStringsAdded;
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
                $insertPlacesParams[] = serialize($translation->getComments());
                // sort
                $insertPlacesParams[] = $importCount;
                ++$insertPlacesCount;
                if ($insertPlacesCount === self::IMPORT_BATCH_SIZE) {
                    $insertPlaces->execute($insertPlacesParams);
                    $insertPlacesParams = [];
                    $insertPlacesCount = 0;
                }
                ++$importCount;
            }
            if ($insertPlacesCount > 0) {
                $connection->executeQuery(
                    $this->buildInsertTranslatablePlacesSQL($version, $insertPlacesCount),
                    $insertPlacesParams
                );
            }
            if ($someStringChanged === false && !$packageVersionIsNew) {
                $newHash = (string) $connection->fetchColumn(
                    'select md5(group_concat(translatable)) from CommunityTranslationTranslatablePlaces where packageVersion = ? order by translatable',
                    [$version->getID()]
                );
                if ($newHash !== $prevHash) {
                    $someStringChanged = true;
                }
            }
            if ($someStringChanged) {
                $version->setUpdatedOn(new DateTime());
                $this->em->persist($version);
                $this->em->flush($version);
                $this->em->clear(Translatable::class);
            }
            $connection->commit();
        } catch (Exception $x) {
            try {
                $connection->rollBack();
            } catch (Exception $foo) {
            }
            throw $x;
        }

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
            } catch (Exception $foo) {
            }
        }

        return $someStringChanged;
    }
}
