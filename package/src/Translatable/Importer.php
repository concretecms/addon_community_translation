<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\Exception;
use Concrete\Package\CommunityTranslation\Src\Package\Package;

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
     * Parse a directory and extract the translatable strings.
     * 
     * @param string $directory
     * @param string $package
     * @param string $version
     *
     * @throws Exception
     *
     * @return bool
     */
    public function importDirectory($directory, $packageHandle, $version)
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
        $translations = new \Gettext\Translations();
        $translations->setLanguage('en_US');
        \C5TL\Parser::clearCache();
        foreach (\C5TL\Parser::getAllParsers() as $parser) {
            if ($parser->canParseDirectory()) {
                $parser->parseDirectory(
                    $directory.$directoryToPotify,
                    $relDirectory,
                    $translations,
                    false,
                    true
                );
            }
        }
        $packageRepo = $this->app->make('community_translation/package');
        $updated = false;
        $em = $this->getEntityManager();
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $package = $packageRepo->findOneBy(array(
                'pHandle' => $packageHandle,
                'pVersion' => $version,
            ));
            if ($package === null) {
                $package = Package::create($packageHandle, $version);
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
            $insertPlacesFields = 'tpPackage, tpTranslatable, tpLocations, tpComments';
            $insertPlacesChunk = ' ('.$package->getID().', ?, ?, ?),';
            $insertPlaces = $connection->prepare('INSERT INTO TranslatablePlaces ('.$insertPlacesFields.') VALUES'.rtrim(str_repeat($insertPlacesChunk, self::IMPORT_BATCH_SIZE), ','))->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertPlaces */
            if ($packageIsNew) {
                $prevHash = null;
            } else {
                $prevHash = (string) $connection->fetchColumn('
                    select md5(group_concat(tpTranslatable)) from IntegratedTranslatablePlaces where tpPackage = ? order by tpTranslatable',
                    array($package->getID())
                );
                $connection->executeQuery(
                    'delete from TranslatablePlaces where tpPackage = ?',
                    array($package->getID())
                );
            }
            $insertPlacesParams = array();
            $insertPlacesCount = 0;
            foreach ($translations as $translationKey => $translation) {
                /* @var \Gettext\Translation $translation */
                $plural = $translation->getPlural();
                $hash = md5($plural ? "$translationKey\005$plural" : $translationKey);
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
                ++$insertPlacesCount;
                if ($insertPlacesCount === self::IMPORT_BATCH_SIZE) {
                    $insertPlaces->execute($insertPlacesParams);
                    $insertPlacesParams = array();
                    $insertPlacesCount = 0;
                }
            }
            if ($insertPlacesCount > 0) {
                $connection->executeQuery(
                    'INSERT INTO TranslatablePlaces ('.$insertPlacesFields.') VALUES'.rtrim(str_repeat($insertPlacesChunk, $insertPlacesCount), ','),
                    $insertPlacesParams
                );
            }
            if ($updated === false && !$packageIsNew) {
                $newHash = (string) $connection->fetchColumn('
                    select md5(group_concat(tpTranslatable)) from IntegratedTranslatablePlaces where tpPackage = ? order by tpTranslatable',
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

        return $updated;
    }
}
