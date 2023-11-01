<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Console\Command\TransifexGlossaryCommand\State;
use CommunityTranslation\Entity\Glossary\Entry as GlossaryEntryEntity;
use CommunityTranslation\Entity\Glossary\Entry\Localized as GlossaryEntryLocalizedEntity;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Glossary\EntryType as GlossaryEntryType;
use CommunityTranslation\Repository\Glossary\Entry as GlossaryEntryRepository;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManager;
use Generator;

defined('C5_EXECUTE') or die('Access Denied.');

class TransifexGlossaryCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:transifex:glossary:import
    {file : The path to the CSV file downloaded from Transifex (see help) }
EOT
    ;

    private EntityManager $em;

    private GlossaryEntryRepository $entryRepo;

    public function handle(EntityManager $em): int
    {
        $this->em = $em;
        $locales = $this->em->getRepository(LocaleEntity::class)->findBy(['isSource' => null]);
        $this->entryRepo = $this->em->getRepository(GlossaryEntryEntity::class);
        $glossaryFile = $this->input->getArgument('file');
        $fd = $this->openGlossaryFile($glossaryFile);
        try {
            $state = new State($locales);
            $this->parseGlossaryFile($state, $fd);
        } finally {
            fclose($fd);
        }

        return static::SUCCESS;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Console\Command::getMutexKey()
     */
    protected function getMutexKey(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::configureUsingFluentDefinition()
     */
    protected function configureUsingFluentDefinition()
    {
        parent::configureUsingFluentDefinition();
        $this
            ->setAliases([
                'ct:transifex-glossary',
            ])
            ->setDescription('Import glossaries from Transifex')
            ->setHelp(
                <<<'EOT'
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
  1 errors occurred
EOT
            )
        ;
    }

    /**
     * @return resource
     */
    private function openGlossaryFile(string $glossaryFile)
    {
        if (!is_file($glossaryFile)) {
            throw new UserMessageException("Unable to find the file {$glossaryFile}");
        }
        if (!is_readable($glossaryFile)) {
            throw new UserMessageException("The file {$glossaryFile} is not readable");
        }
        set_error_handler(static function () {}, -1);
        try {
            $fd = fopen($glossaryFile, 'rb');
        } finally {
            restore_error_handler();
        }
        if (!$fd) {
            throw new UserMessageException("Failed to open the file {$glossaryFile}");
        }

        return $fd;
    }

    /**
     * @param resource $fd
     */
    private function parseGlossaryFile(State $state, $fd): void
    {
        $headerLineParsed = false;
        foreach ($this->readCSVLine($fd) as $line) {
            $state->rowIndex++;
            if ($headerLineParsed === false) {
                $this->parseHeaderLine($state, $line);
                $headerLineParsed = true;
            } else {
                $this->parseDataLine($state, $line);
            }
        }
    }

    /**
     * @param resource $fd
     *
     * @return string[][]
     */
    private function readCSVLine($fd): Generator
    {
        for (;;) {
            $line = fgetcsv($fd, 0, ',', '"');
            if ($line === false) {
                break;
            }
            yield $line;
        }
    }

    /**
     * @param string[] $line
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function parseHeaderLine(State $state, array $line): void
    {
        $this->output->write('Parsing CSV header... ');
        $map = [];
        $testLocales = [];
        $numFields = 0;
        $sourceFieldsFound = 0;
        $m = null;
        foreach ($line as $index => $field) {
            if ($index !== $numFields) {
                throw new UserMessageException("Invalid field index: {$index}");
            }
            if (strpos($field, ',') !== false) {
                throw new UserMessageException("Invalid field name: {$field}");
            }
            if (isset($state->sourceFields[$field])) {
                $sourceFieldsFound++;
                $key = $state->sourceFields[$field];
            } elseif (preg_match($state->rxLocalizedFields, $field, $m)) {
                $mappedField = $state->localizedFields[$m[1]];
                $mappedLocale = $m[2];
                if (!isset($testLocales[$mappedLocale])) {
                    $testLocales[$mappedLocale] = [];
                }
                $testLocales[$mappedLocale][] = $mappedField;
                $key = $mappedLocale . ',' . $mappedField;
            } else {
                $displayIndex = $index + 1;
                throw new UserMessageException("Unknown field #{$displayIndex}: {$field}");
            }
            if (in_array($key, $map, true)) {
                throw new UserMessageException("Duplicated field: {$field}");
            }
            $map[$index] = $key;
            $numFields++;
        }
        if ($sourceFieldsFound !== count($state->sourceFields)) {
            throw new UserMessageException('Bad source fields count');
        }
        foreach ($testLocales as $id => $fields) {
            if (count($fields) !== count($state->localizedFields)) {
                throw new UserMessageException("Bad fields count for locale {$id}");
            }
        }
        $state->map = $map;
        $state->numFields = $numFields;
        $this->output->writeln('<info>done</info>');
    }

    /**
     * @param string[] $line
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function parseDataLine(State $state, array $line): void
    {
        $this->output->writeln("Parsing row {$state->rowIndex}");
        if (count($line) !== $state->numFields) {
            throw new UserMessageException('Bad fields count!');
        }
        $entry = new GlossaryEntryEntity('');
        $translations = [];
        foreach ($line as $index => $value) {
            if (!isset($state->map[$index])) {
                throw new UserMessageException("Bad field index: {$index}");
            }
            $v = explode(',', $state->map[$index]);
            $value = trim($value);
            if (count($v) === 1) {
                $setter = $state->map[$index];
                switch ($setter) {
                    case 'setType':
                        $value = strtolower($value);
                        if (GlossaryEntryType::isValidType($value) !== true) {
                            throw new UserMessageException("Invalid term type: {$value}");
                        }
                        break;
                }
                $entry->{$setter}($value);
            } else {
                if (!isset($translations[$v[0]])) {
                    $translations[$v[0]] = [];
                }
                $translations[$v[0]][$v[1]] = $value;
            }
        }
        if ($entry->getTerm() === '') {
            $this->output->writeln('EMPTY TERM - SKIPPING');

            return;
        }
        $this->output->write(" > term is \"{$entry->getTerm()}\" (type: \"{$entry->getType()}\")");
        $existing = $this->entryRepo->findOneBy(['term' => $entry->getTerm(), 'type' => $entry->getType()]);
        if ($existing === null) {
            $this->output->write(' (NEW)');
            $this->em->persist($entry);
            $this->em->flush($entry);
            $this->output->writeln(' persisted');
        } else {
            $this->output->writeln(' (AREADY EXISTS)');
            $entry = $existing;
        }
        ksort($translations);
        foreach ($translations as $localeID => $translation) {
            $this->output->write(" > checking translation for {$localeID}... ");
            if ($translation['text'] === '') {
                $this->output->writeln('empty translation - skipped');
                continue;
            }
            if (!isset($state->existingLocales[$localeID])) {
                $this->output->writeln('LOCALE NOT DEFINED - skipped');
                continue;
            }
            $locale = $state->existingLocales[$localeID];
            $already = null;
            foreach ($entry->getTranslations() as $et) {
                if ($et->getLocale() === $locale) {
                    $already = $et;
                    break;
                }
            }
            if ($already !== null) {
                $this->output->writeln('already in DB - skipped');
                continue;
            }
            $localizedEntry = new GlossaryEntryLocalizedEntity($entry, $locale, $translation['text']);
            $localizedEntry->setComments($translation['comments']);
            $entry->getTranslations()->add($localizedEntry);
            $this->em->persist($localizedEntry);
            $this->em->flush($localizedEntry);
            $this->output->writeln('saved.');
        }
    }
}
