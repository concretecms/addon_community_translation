<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Concrete\Package\CommunityTranslation\Src\Service\Parser\Parser;

class Importer implements \Concrete\Core\Application\ApplicationAwareInterface
{
    const IMPORT_BATCH_SIZE = 50;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Entity manager.
     *
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager = null;

    /**
     * Get the entity manager.
     *
     * @return \Concrete\Core\Database\Connection\Connection
     */
    protected function getEntityManager()
    {
        if ($this->entityManager === null) {
            $this->entityManager = $this->app->make('community_translation/em');
        }

        return $this->entityManager;
    }

    /**
     * Database connection.
     *
     * @var \Concrete\Core\Database\Connection\Connection
     */
    protected $connection = null;

    /**
     * Get the connection to the database.
     *
     * @return \Concrete\Core\Database\Connection\Connection
     */
    protected function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = $this->getEntityManager()->getConnection();
        }

        return $this->connection;
    }

    /**
     * Parse a directory and extract the translatable strings, and import them into the database.
     * 
     * @param string $directory
     * @param string $packageHandle
     * @param string $packageVersion
     *
     * @throws UserException
     *
     * @return bool Returns true if some translatable strings changed, false otherwise.
     */
    public function importDirectory($directory, $packageHandle, $packageVersion)
    {
        $directoryToPotify = '';
        $potfile2root = '';
        if ($packageHandle === '') {
            // Core
            $directoryToPotify = '/concrete';
            $relDirectory = 'concrete';
        } else {
            $directoryToPotify = '';
            $relDirectory = 'packages/'.$packageHandle;
        }
        $parsed = $this->app->make('community_translation/parser')->parseDirectory($directory.$directoryToPotify, $relDirectory, Parser::GETTEXT_NONE);
        $translations = $parsed ? $parsed->getPot() : null;
        if ($translations === null) {
            $translations = new \Gettext\Translations();
            $translations->setLanguage('en_US');
        }

        return $this->importTranslations($translations, $packageHandle, $packageVersion);
    }

    /**
     * Import a list of translatable strings into the database.
     *
     * @param \Gettext\Translations $translations
     * @param string $packageHandle
     * @param string $packageVersion
     *
     * @throws UserException
     *
     * @return bool Returns true if some translatable strings changed, false otherwise.
     */
    public function importTranslations(\Gettext\Translations $translations, $packageHandle, $packageVersion)
    {
        $packageRepo = $this->app->make('community_translation/package');
        $updated = false;
        $em = $this->getEntityManager();
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $package = $packageRepo->findOneBy(array(
                'pHandle' => $packageHandle,
                'pVersion' => $packageVersion,
            ));
            if ($package === null) {
                $package = Package::create($packageHandle, $packageVersion);
                $em->persist($package);
                $em->flush();
                $packageIsNew = true;
            } else {
                $packageIsNew = false;
            }

            $searchHash = $connection->prepare('SELECT tID FROM Translatables WHERE tHash = ? LIMIT 1')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $searchHash */
            $insertTranslatable = $connection->prepare('INSERT INTO Translatables SET tHash = ?, tContext = ?, tText = ?, tPlural = ?')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertTranslatable */
            $insertPlacesFields = 'tpPackage, tpTranslatable, tpLocations, tpComments, tpSort';
            $insertPlacesChunk = ' ('.$package->getID().', ?, ?, ?, ?),';
            $insertPlaces = $connection->prepare('INSERT INTO TranslatablePlaces ('.$insertPlacesFields.') VALUES'.rtrim(str_repeat($insertPlacesChunk, self::IMPORT_BATCH_SIZE), ','))->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertPlaces */
            if ($packageIsNew) {
                $prevHash = null;
            } else {
                $prevHash = (string) $connection->fetchColumn('
                    select md5(group_concat(tpTranslatable)) from TranslatablePlaces where tpPackage = ? order by tpTranslatable',
                    array($package->getID())
                );
                $connection->executeQuery(
                    'delete from TranslatablePlaces where tpPackage = ?',
                    array($package->getID())
                );
            }
            $insertPlacesParams = array();
            $insertPlacesCount = 0;
            $importCount = 0;
            foreach ($translations as $translationKey => $translation) {
                /* @var \Gettext\Translation $translation */
                $plural = $translation->getPlural();
                $hash = md5(($plural === '') ? $translationKey : "$translationKey\005$plural");
                $searchHash->execute(array($hash));
                $tID = $searchHash->fetchColumn(0);
                $searchHash->closeCursor();
                //$searchHash->ex
                if ($tID === false) {
                    $insertTranslatable->execute(array(
                        $hash,
                        $translation->getContext(),
                        $translation->getOriginal(),
                        $plural,
                    ));
                    $tID = (int) $connection->lastInsertId();
                    $updated = true;
                } else {
                    $tID = (int) $tID;
                }
                // tpTranslatable
                $insertPlacesParams[] = $tID;
                // tpLocations
                $locations = array();
                foreach ($translation->getReferences() as $tr) {
                    $locations[] = isset($tr[1]) ? implode(':', $tr) : $tr[0];
                }
                $insertPlacesParams[] = serialize($locations);
                // tpComments
                $insertPlacesParams[] = serialize($translation->getComments());
                // tpSort
                $insertPlacesParams[] = $importCount;
                ++$insertPlacesCount;
                if ($insertPlacesCount === self::IMPORT_BATCH_SIZE) {
                    $insertPlaces->execute($insertPlacesParams);
                    $insertPlacesParams = array();
                    $insertPlacesCount = 0;
                }
                ++$importCount;
            }
            if ($insertPlacesCount > 0) {
                $connection->executeQuery(
                    'INSERT INTO TranslatablePlaces ('.$insertPlacesFields.') VALUES'.rtrim(str_repeat($insertPlacesChunk, $insertPlacesCount), ','),
                    $insertPlacesParams
                );
            }
            if ($updated === false && !$packageIsNew) {
                $newHash = (string) $connection->fetchColumn('
                    select md5(group_concat(tpTranslatable)) from TranslatablePlaces where tpPackage = ? order by tpTranslatable',
                    array($package->getID())
                );
                if ($newHash !== $prevHash) {
                    $updated = true;
                }
            }
            if ($updated) {
                $package->setUpdatedOn(new \DateTime());
                $em->persist($package);
                $em->flush();
            }
            $connection->commit();
        } catch (\Exception $x) {
            try {
                $connection->rollBack();
            } catch (\Exception $foo) {
            }
            throw $x;
        }

        if ($updated) {
            try {
                $this->app->make('community_translation/stats')->resetForPackage($package);
            } catch (\Exception $foo) {
            }
        }

        return $updated;
    }
}
