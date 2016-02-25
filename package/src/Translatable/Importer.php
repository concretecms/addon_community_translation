<?php
namespace Concrete\Package\CommunityTranslation\Src\Translatable;

use Concrete\Package\CommunityTranslation\Src\Exception;

class Importer
{
    const IMPORT_BATCH_SIZE = 50;

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
            $this->connection = \Core::make('community_translation/em')->getConnection();
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
    public function importDirectory($directory, $package, $version)
    {
        $directoryToPotify = '';
        $potfile2root = '';
        if ($package === '') {
            // Core
            $directoryToPotify = '/concrete';
            $relDirectory = 'concrete';
        } else {
            $directoryToPotify = '';
            $relDirectory = 'packages/'.$package;
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
        $newStrings = false;
        $connection = $this->getConnection();
        $connection->beginTransaction();
        try {
            $searchHash = $connection->prepare('SELECT tID FROM Translatables WHERE tHash = ? LIMIT 1')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $searchHash */
            $insertTranslatable = $connection->prepare('INSERT INTO Translatables SET tHash = ?, tContext = ?, tText = ?, tPlural = ?')->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertTranslatable */
            $insertPlacesChunk = ' (?, ?, ?, ?, ?),';
            $insertPlaces = $connection->prepare('INSERT INTO TranslatablePlaces (tpTranslatable, tpPackage, tpVersion, tpLocations, tpComments) VALUES'.rtrim(str_repeat($insertPlacesChunk, self::IMPORT_BATCH_SIZE), ','))->getWrappedStatement();
            /* @var \Concrete\Core\Database\Driver\PDOStatement $insertPlaces */
            $connection->executeQuery(
                'DELETE FROM TranslatablePlaces WHERE (tpPackage = ?) AND (tpVersion = ?)',
                array($package, $version)
            );
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
                    $newStrings = true;
                } else {
                    $tID = (int) $tID;
                }
                // tpTranslatable
                $insertPlacesParams[] = $tID;
                // tpPackage
                $insertPlacesParams[] = $package;
                // tpVersion
                $insertPlacesParams[] = $version;
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
                    'INSERT INTO TranslatablePlaces (tpTranslatable, tpPackage, tpVersion, tpLocations, tpComments) VALUES'.rtrim(str_repeat($insertPlacesChunk, $insertPlacesCount), ','),
                    $insertPlacesParams
                );
            }
            $connection->commit();
        } catch (\Exception $x) {
            try {
                $connection->rollBack();
            } catch (\Exception $foo) {
            }
            throw $x;
        }

        return $newStrings;
    }
}
