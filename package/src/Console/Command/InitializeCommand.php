<?php
namespace Concrete\Package\CommunityTranslation\Src\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\Package\Package;
use Zend\Http\Client;
use Exception;

class InitializeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('translate:initialize')
            ->addArgument('operation', InputArgument::REQUIRED, "The operation to perform. In order you should call this command with 'git', 'transifex-translations', and 'transifex-glossary'")
            ->setDescription("Initializes the translation system by importing the data from GitHub and Transifex")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rc = 0;
        try {
            switch ($input->getArgument('operation')) {
                case 'git':
                    $this->importGitRepositories($input, $output);
                    break;
                case 'transifex-translations':
                    $this->importTransifexTranslations($input, $output);
                    break;
                case 'transifex-glossary':
                    $this->importTransifexGlossary($input, $output);
                    break;
                default:
                    throw new Exception('Invalid operation specified');
            }
        } catch (Exception $x) {
            $output->writeln('<error>'.$x->getMessage().'</error>');
            $rc = 1;
        }

        return $rc;
    }

    protected function importGitRepositories(InputInterface $input, OutputInterface $output)
    {
        $app = \Core::make('app');

        $repositories = $app->make('community_translation/git')->findAll();
        if (empty($repositories)) {
            throw new Exception('No git repositories defined!');
        }

        $importer = $app->make('community_translation/git/importer');
        $importer->setLogger(function ($line) use ($output) {
            $output->writeln(" - $line");
        });

        foreach ($repositories as $repository) {
            $output->writeln(sprintf('Importing strings from %s (development and tagged versions)... ', $git->getName()));
            $importer->import($git);
            $output->writeln(" - <info>done</info>");
        }
    }

    protected function importTransifexTranslations(InputInterface $input, OutputInterface $output)
    {
        $app = \Core::make('app');
        $coreVersions = $app->make('community_translation/package')->findBy(array('pHandle' => ''));
        if (count($coreVersions) < 5) {
            throw new Exception('Not enough core versions found: please import the git repositories before running this operation.');
        }
        $question = new Question('Please enter the Transifex username: ', '');
        $transifexUsername = trim((string) $this->getHelper('question')->ask($input, $output, $question));
        if ($transifexUsername === '') {
            throw new Exception('Operation aborted');
        }
        $question = new Question('Please enter the Transifex password for '.$transifexUsername.' (will be hidden): ', '');
        $question->setHidden(true);
        $transifexPassword = trim((string) $this->getHelper('question')->ask($input, $output, $question));
        if ($transifexPassword === '') {
            throw new Exception('Operation aborted');
        }

        $em = $app->make('community_translation/em');
        $client = new Client(
            '',
            array(
                'adapter' => 'Zend\Http\Client\Adapter\Curl',
                'curloptions' => array(
                    CURLOPT_SSL_VERIFYPEER => (bool) $app->make('config')->get('app.curl.verifyPeer'),
                ),
            )
        );
        $client->setAuth($transifexUsername, $transifexPassword);

        $output->write('Retrieve list of locales... ');
        $client->setUri('https://www.transifex.com/api/2/project/concrete5/languages/');
        $client->setMethod('GET');
        $response = $client->send();
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
        $txLocales = @json_decode($response->getBody(), true);
        if (!is_array($txLocales)) {
            throw new Exception('Failed to decode response');
        }
        $localeIDs = array('en_US');
        foreach ($txLocales as $txLocale) {
            $id = $txLocale['language_code'];
            switch ($id) {
                case 'en':
                case 'en_US':
                    // Source language
                    break;
                case 'cs': // Czech
                case 'en_AT': // English (Austria)
                    // Why these languages are there??? Transifex bug!
                    break;
                default:
                    $localeIDs[] = $id;
                    break;
            }
        }
        $output->writeln("<info>done (".count($localeIDs)." found).</info>");

        $output->write('Retrieve list of resources... ');
        $client->setUri('https://www.transifex.com/api/2/project/concrete5/resources/');
        $client->setMethod('GET');
        $response = $client->send();
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
        $txResources = @json_decode($response->getBody(), true);
        if (!is_array($txResources)) {
            throw new Exception('Failed to decode response');
        }
        $resources = array();
        foreach ($txResources as $txResource) {
            $slug = $txResource['slug'];
            if (preg_match('/^core-dev-(\d+)$/', $slug, $m)) {
                $version = Package::DEV_PREFIX.implode('.', str_split($m[1], 1));
            } elseif (preg_match('/^core-(\d+)$/', $slug, $m)) {
                $version = implode('.', str_split($m[1], 1));
            } else {
                throw new Exception(sprintf('Failed to decode resource slug %s', $slug));
            }
            $resources[$slug] = $version;
        }
        ksort($resources);
        $output->writeln("<info>done (".count($resources)." found).</info>");

        $localeRepo = $app->make('community_translation/locale');
        $translationsImporter = $app->make('community_translation/translation/importer');
        foreach ($localeIDs as $localeID) {
            $output->writeln('Working on '.$localeID);
            $output->write(sprintf(' - checking Locale entity... ', $localeID));
            $locale = $localeRepo->find($localeID);
            if ($locale === null) {
                $locale = Locale::createForLocaleID($localeID);
                $msg = 'created';
            } else {
                $msg = 'updated';
            }
            $locale->setIsSource($localeID === 'en_US');
            $locale->setIsApproved(true);
            $em->persist($locale);
            $em->flush();
            $output->writeln("<info>$msg.</info>");
            if ($localeID !== 'en_US') {
                foreach ($resources as $slug => $version) {
                    $output->writeln(sprintf(' - working on %s', $version));
                    $output->write(sprintf('   > fetching translations for %s... ', $version));
                    $client->setUri('https://www.transifex.com/api/2/project/concrete5/resource/'.$slug.'/translation/'.$localeID.'/');
                    $client->setMethod('GET');
                    $response = $client->send();
                    if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                        throw new Exception($response->getReasonPhrase());
                    }
                    $data = @json_decode($response->getBody(), true);
                    if (!is_array($data) || !isset($data['mimetype']) || $data['mimetype'] !== 'text/x-po' || !isset($data['content']) || !is_string($data['content']) || $data['content'] === '') {
                        throw new Exception('Failed to decode response');
                    }
                    $output->writeln('<info>done.</info>');
                    $output->write(sprintf('   > parsing translations... '));
                    $translations = \Gettext\Translations::fromPoString($data['content']);
                    if (count($translations) < 100) {
                        throw new Exception('Too few translations downloaded');
                    }
                    $output->writeln('<info>done ('.count($translations).' strings).</info>');
                    $txPlurals = $translations->getPluralForms();
                    $count = isset($txPlurals) ? $txPlurals[0] : null;
                    if ($count !== $locale->getPluralCount()) {
                        $output->write(sprintf('   > fixing plural count (from %1$s to %2$s)... ', $count, $locale->getPluralCount()));
                        $translations->setLanguage($locale->getID());
                        $output->writeln('<info>done.</info>');
                    }
                    $output->write('   > saving... ');
                    $translationsImporter->import($translations, $locale, array('maySetAsReviewed' => true));
                    $output->writeln('<info>done.</info>');
                }
            }
        }
    }

    protected function importTransifexGlossary(InputInterface $input, OutputInterface $output)
    {
        $app = \Core::make('app');

        $existingLocales = array();
        foreach ($app->make('community_translation/locale')->getApprovedLocales() as $locale) {
            $existingLocales[$locale->getID()] = $locale;
        }
        if (count($existingLocales) < 10) {
            throw new Exception('Not enough approved locales found: please import the transifex translations before running this operation.');
        }

        $output->writeln('Please download the glossary file of all languages from https://www.transifex.com/_/glossary/ajax/download/project/concrete5/concrete5/');
        $question = new Question('Enter the local path to the downloaded csv file: ', '');
        for (;;) {
            $glossaryFile = trim((string) $this->getHelper('question')->ask($input, $output, $question));
            if ($glossaryFile === '') {
                throw new Exception('Operation aborted');
            }
            if (is_file($glossaryFile)) {
                break;
            }
            $output->writeln("Unable to find the glossary file $glossaryFile");
        }
        $fd = @fopen($glossaryFile, 'rb');
        if (!$fd) {
            throw new Exception("Failed to open the glossary file $glossaryFile");
        }
        $repo = $app->make('community_translation/glossary/entry');
        $em = $app->make('community_translation/em');
        try {
            $map = null;
            $numFields = 0;
            $entries = array();
            $sourceFields = array('term' => 'setTerm', 'pos' => 'setType', 'comment' => 'setComments');
            $localizedFields = array('translation' => 'text', 'comment' => 'comments');
            $rxLocalizedFields = '/^(';
            foreach (array_keys($localizedFields) as $i => $f) {
                if ($i > 0) {
                    $rxLocalizedFields .= '|';
                }
                $rxLocalizedFields .= preg_quote($f, '/');
            }
            $rxLocalizedFields .= ')_(.+)$/';
            $sourceFieldsFound = 0;
            $rowIndex = 0;
            for (;;) {
                $line = @fgetcsv($fd, 0, ',', '"');
                if ($line === false) {
                    break;
                }
                if ($line === null) {
                    throw new Exception('Error in CSV file');
                }
                ++$rowIndex;

                if ($map === null) {
                    $output->write('Parsing CSV header... ');
                    $map = array();
                    $testLocales = array();
                    foreach ($line as $index => $field) {
                        if ($index !== $numFields) {
                            throw new Exception('Invalid field index: '.$index);
                        }
                        if (strpos($field, ',') !== false) {
                            throw new Exception('Invalid field name: '.$field);
                        }
                        if (isset($sourceFields[$field])) {
                            ++$sourceFieldsFound;
                            $key = $sourceFields[$field];
                        } elseif (preg_match($rxLocalizedFields, $field, $m)) {
                            $mappedField = $localizedFields[$m[1]];
                            $mappedLocale = $m[2];
                            if (!isset($testLocales[$mappedLocale])) {
                                $testLocales[$mappedLocale] = array();
                            }
                            $testLocales[$mappedLocale][] = $mappedField;
                            $key = $mappedLocale.','.$mappedField;
                        } else {
                            throw new Exception('Unknown field #'.($index + 1).': '.$field);
                        }
                        if (in_array($key, $map, true)) {
                            throw new Exception('Duplicated field: '.$field);
                        }
                        $map[$index] = $key;
                        ++$numFields;
                    }
                    if ($sourceFieldsFound !== count($sourceFields)) {
                        throw new Exception('Bad source fields count');
                    }
                    foreach ($testLocales as $id => $fields) {
                        if (count($fields) !== count($localizedFields)) {
                            throw new Exception('Bad fields count for locale '.$id);
                        }
                    }
                    $output->writeln("<info>done</info>");
                    continue;
                }

                $output->writeln("Parsing row $rowIndex");
                if (count($line) !== $numFields) {
                    throw new Exception('Bad fields count!');
                }
                $entry = new \Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry();
                $translations = array();
                foreach ($line as $index => $value) {
                    if (!isset($map[$index])) {
                        throw new Exception('Bad field index: '.$index);
                    }
                    $v = explode(',', $map[$index]);
                    $value = trim($value);
                    if (count($v) === 1) {
                        switch ($map[$index]) {
                            case 'setType':
                                $value = strtolower($value);
                                if (!\Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry::isValidType($value)) {
                                    throw new Exception('Invalid term type: '.$value);
                                }
                                break;
                        }

                        $entry->{$map[$index]}($value);
                    } else {
                        if (!isset($translations[$v[0]])) {
                            $translations[$v[0]] = array();
                        }
                        $translations[$v[0]][$v[1]] = $value;
                    }
                }
                if ($entry->getTerm() === '') {
                    $output->writeln('EMPTY TERM - SKIPPING');
                    continue;
                }
                $output->write(' > term is "'.$entry->getTerm().'" (type: "'.$entry->getType().'")');
                $existing = $repo->findOneBy(array('geTerm' => $entry->getTerm(), 'geType' => $entry->getType()));
                if ($existing === null) {
                    $output->write(' (NEW)');
                    $em->persist($entry);
                    $em->flush();
                    $output->writeln(' persisted');
                } else {
                    $output->writeln(' (AREADY EXISTS)');
                    $entry = $existing;
                }
                $existingTranslations = $entry->getTranslations();
                foreach ($translations as $localeID => $translation) {
                    $output->write(" > checking translation for $localeID... ");
                    if ($translation['text'] === '') {
                        $output->writeln('empty translation - skipped');
                        continue;
                    }
                    if (!isset($existingLocales[$localeID])) {
                        $output->writeln('LOCALE NOT DEFINED - skipped');
                        continue;
                    }
                    $locale = $existingLocales[$localeID];
                    $already = null;
                    foreach ($existingTranslations as $et) {
                        if ($et->getLocale() === $locale) {
                            $already = $et;
                            break;
                        }
                    }
                    if ($already !== null) {
                        $output->writeln('already in DB - skipped');
                        continue;
                    }
                    $t = $entry->addTranslation($locale, $translation['text'], $translation['comments']);
                    $em->persist($t);
                    $em->flush();
                    $output->writeln('saved.');
                }
            }
        } catch (Exception $x) {
            @fclose($fd);
            throw $x;
        }
        @fclose($fd);
    }
}
