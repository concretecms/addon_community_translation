<?php
namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Entity\Glossary\Entry as GlossaryEntryEntity;
use CommunityTranslation\Entity\Glossary\Entry\Localized as GlossaryEntryLocalizedEntity;
use CommunityTranslation\Glossary\EntryType as GlossaryEntryType;
use CommunityTranslation\Repository\Glossary\Entry as GlossaryEntryRepository;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Console\Command;
use Concrete\Core\Support\Facade\Application;
use Doctrine\ORM\EntityManager;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TransifexGlossaryCommand extends Command
{
    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:transifex-glossary')
            ->setDescription('Import glossaries from Transifex')
            ->addArgument('file', InputArgument::REQUIRED, 'The path to the CSV file downloaded from Transifex (see help)')
            ->setHelp(<<<EOT
Transifex organizations (that groups one or more Transifex projects) can have one or more glossaries.

This command allows you to import those glossaries.

Since Transifex does not offer an API to automatically fetch these glossaries, you have to manually download them.
Organizations maintainers can go to the organization settings page (for the concrete5 project it's URL is
https://www.transifex.com/concrete5/settings/glossary/ ).

Other users needs to know the glossary handle in order to download it. For concrete5, for instance, where the
glossary handle is named concrete5, you can go to the page
https://www.transifex.com/_/glossary/ajax/download/project/concrete5/concrete5

When you download the glossary, you should choose to download all the languages, including glossary notes.

Returns codes:
  0 operation completed successfully
  $errExitCode errors occurred
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = Application::getFacadeApplication();
        $em = $app->make(EntityManager::class);
        /* @var EntityManager $em */
        $fs = $app->make(Filesystem::class);
        /* @var Filesystem $fs */
        $glossaryFile = $input->getArgument('file');
        if (!$fs->isFile($glossaryFile)) {
            throw new Exception("Unable to find the file $glossaryFile");
        }
        $fd = @fopen($glossaryFile, 'rb');
        if ($fd === false) {
            throw new Exception("Failed to open the glossary file $glossaryFile");
        }
        try {
            $this->parseFile($output, $fd);
        } catch (Exception $x) {
            @fclose($fd);
            throw $x;
        }
        @fclose($fd);
    }

    private $map;
    private $sourceFields;
    private $localizedFields;
    private $rxLocalizedFields;
    private $rowIndex;
    private $numFields;
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    private $entryRepo;

    private function initializeState()
    {
        $this->map = null;
        $this->sourceFields = ['term' => 'setTerm', 'pos' => 'setType', 'comment' => 'setComments'];
        $this->localizedFields = ['translation' => 'text', 'comment' => 'comments'];
        $rx = '/^(';
        foreach (array_keys($this->localizedFields) as $i => $f) {
            if ($i > 0) {
                $rx .= '|';
            }
            $rx .= preg_quote($f, '/');
        }
        $rx .= ')_(.+)$/';
        $this->rxLocalizedFields = $rx;
        $this->rowIndex = 0;
        $this->numFields = 0;
        $app = Application::getFacadeApplication();
        $this->em = $app->make(EntityManager::class);
        $this->entryRepo = $app->make(GlossaryEntryRepository::class);
        $existingLocales = [];
        foreach ($app->make(LocaleRepository::class)->findBy(['isSource' => null]) as $locale) {
            $existingLocales[$locale->getID()] = $locale;
        }
        $this->existingLocales = $existingLocales;
    }

    /**
     * @param resource $fd
     */
    private function parseFile(OutputInterface $output, $fd)
    {
        $this->initializeState();
        foreach ($this->readCSVLine($fd) as $line) {
            ++$this->rowIndex;
            if ($this->map === null) {
                $this->parseMap($output, $line);
            } else {
                $this->parseLine($output, $line);
            }
        }
    }

    /**
     * @param resource $fd
     *
     * @return array[array]
     */
    private function readCSVLine($fd)
    {
        for (; ;) {
            $line = @fgetcsv($fd, 0, ',', '"');
            if ($line === false) {
                break;
            }
            if ($line === null) {
                throw new Exception('Error in CSV file');
            }
            yield $line;
        }
    }

    /**
     * @param array $line
     *
     * @return array
     */
    private function parseMap(OutputInterface $output, array $line)
    {
        $output->write('Parsing CSV header... ');
        $map = [];
        $testLocales = [];
        $numFields = 0;
        $sourceFieldsFound = 0;
        foreach ($line as $index => $field) {
            if ($index !== $numFields) {
                throw new Exception('Invalid field index: ' . $index);
            }
            if (strpos($field, ',') !== false) {
                throw new Exception('Invalid field name: ' . $field);
            }
            if (isset($this->sourceFields[$field])) {
                ++$sourceFieldsFound;
                $key = $this->sourceFields[$field];
            } elseif (preg_match($this->rxLocalizedFields, $field, $m)) {
                $mappedField = $this->localizedFields[$m[1]];
                $mappedLocale = $m[2];
                if (!isset($testLocales[$mappedLocale])) {
                    $testLocales[$mappedLocale] = [];
                }
                $testLocales[$mappedLocale][] = $mappedField;
                $key = $mappedLocale . ',' . $mappedField;
            } else {
                throw new Exception('Unknown field #' . ($index + 1) . ': ' . $field);
            }
            if (in_array($key, $map, true)) {
                throw new Exception('Duplicated field: ' . $field);
            }
            $map[$index] = $key;
            ++$numFields;
        }
        if ($sourceFieldsFound !== count($this->sourceFields)) {
            throw new Exception('Bad source fields count');
        }
        foreach ($testLocales as $id => $fields) {
            if (count($fields) !== count($this->localizedFields)) {
                throw new Exception('Bad fields count for locale ' . $id);
            }
        }
        $output->writeln('<info>done</info>');
        $this->map = $map;
        $this->numFields = $numFields;
    }

    private function parseLine(OutputInterface $output, array $line)
    {
        $output->writeln("Parsing row {$this->rowIndex}");
        if (count($line) !== $this->numFields) {
            throw new Exception('Bad fields count!');
        }
        $entry = GlossaryEntryEntity::create();
        $translations = [];
        foreach ($line as $index => $value) {
            if (!isset($this->map[$index])) {
                throw new Exception('Bad field index: ' . $index);
            }
            $v = explode(',', $this->map[$index]);
            $value = trim($value);
            if (count($v) === 1) {
                switch ($this->map[$index]) {
                    case 'setType':
                        $value = strtolower($value);
                        if (GlossaryEntryType::isValidType($value) !== true) {
                            throw new Exception('Invalid term type: ' . $value);
                        }
                        break;
                }

                $entry->{$this->map[$index]}($value);
            } else {
                if (!isset($translations[$v[0]])) {
                    $translations[$v[0]] = [];
                }
                $translations[$v[0]][$v[1]] = $value;
            }
        }
        if ($entry->getTerm() === '') {
            $output->writeln('EMPTY TERM - SKIPPING');

            return;
        }
        $output->write(' > term is "' . $entry->getTerm() . '" (type: "' . $entry->getType() . '")');
        $existing = $this->entryRepo->findOneBy(['term' => $entry->getTerm(), 'type' => $entry->getType()]);
        if ($existing === null) {
            $output->write(' (NEW)');
            $this->em->persist($entry);
            $this->em->flush($entry);
            $output->writeln(' persisted');
        } else {
            $output->writeln(' (AREADY EXISTS)');
            $entry = $existing;
        }
        ksort($translations);
        foreach ($translations as $localeID => $translation) {
            $output->write(" > checking translation for $localeID... ");
            if ($translation['text'] === '') {
                $output->writeln('empty translation - skipped');
                continue;
            }
            if (!isset($this->existingLocales[$localeID])) {
                $output->writeln('LOCALE NOT DEFINED - skipped');
                continue;
            }
            $locale = $this->existingLocales[$localeID];
            $already = null;
            foreach ($entry->getTranslations() as $et) {
                if ($et->getLocale() === $locale) {
                    $already = $et;
                    break;
                }
            }
            if ($already !== null) {
                $output->writeln('already in DB - skipped');
                continue;
            }
            $localizedEntry = GlossaryEntryLocalizedEntity::create($entry, $locale)
                ->setTranslation($translation['text'])
                ->setComments($translation['comments']);
            $entry->getTranslations()->add($localizedEntry);
            $this->em->persist($localizedEntry);
            $this->em->flush($localizedEntry);
            $output->writeln('saved.');
        }
    }
}
