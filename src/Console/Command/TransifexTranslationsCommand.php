<?php

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Translation\Importer;
use Concrete\Core\Entity\User\User as UserEntity;
use Doctrine\ORM\EntityManager;
use Exception;
use Gettext\Translations;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TransifexTranslationsCommand extends Command
{
    private $transifexUsername;

    private $transifexPassword;

    protected function configure()
    {
        $errExitCode = static::RETURN_CODE_ON_FAILURE;
        $this
            ->setName('ct:transifex-translations')
            ->setDescription('Import translations from Transifex')
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'The Transifex username')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'The Transifex password')
            ->addOption('create', 'c', InputOption::VALUE_NONE, 'Create the locales that are not yet defined')
            ->addOption('approve', 'a', InputOption::VALUE_NONE, 'Import the translations as already approved')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'The locale identifier (if not specified we\'ll fetch all Transifex locales)')
            ->addOption('resource', 'r', InputOption::VALUE_REQUIRED, 'The Transifex resource handle (if not specified we\'ll fetch all the resources)')
            ->addArgument('project', InputArgument::REQUIRED, 'The Transifex project handle')
            ->setHelp(<<<EOT
Returns codes:
  0 operation completed successfully
  $errExitCode errors occurred
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->app->make(EntityManager::class);

        $createNewLocales = $input->getOption('create');
        $approveTranslations = $input->getOption('approve');

        $this->getTransifexAccount($input, $output);

        $output->write('Retrieve list of available Transifex locales... ');
        $availableTransifexLocales = $this->fetchTransifexLocales($input->getArgument('project'));
        $output->writeln('<info>done (' . count($availableTransifexLocales) . ' found)</info>');

        if ($input->getOption('locale') === null) {
            $transifexLocales = $availableTransifexLocales;
        } else {
            $transifexLocales = [];
            foreach ($availableTransifexLocales as $availableTransifexLocale) {
                if (strcasecmp($input->getOption('locale'), $availableTransifexLocale) === 0) {
                    $transifexLocales[] = $availableTransifexLocale;
                    break;
                }
            }
            if (!isset($transifexLocales[0])) {
                throw new Exception("Unable to find the requested locale with id '%s'", $input->getOption('locale'));
            }
        }

        if ($input->getOption('resource') === null) {
            $output->write('Retrieve list of resources... ');
            $transifexResources = $this->fetchTransifexResources($input->getArgument('project'));
            $output->writeln('<info>done (' . count($transifexResources) . ' found)</info>');
        } else {
            $transifexResources = [$input->getOption('resource')];
        }

        $user = $this->app->make(EntityManager::class)->find(UserEntity::class, USER_SUPER_ID);
        $translationsImporter = $this->app->make(Importer::class);
        /* @var Importer $translationsImporter */
        $localeRepo = $this->app->make(LocaleRepository::class);
        foreach ($transifexLocales as $transifexLocale) {
            $output->writeln('Working on locale ' . $transifexLocale);

            $output->write('   > checking Locale entity... ');
            $locale = $localeRepo->find($transifexLocale);
            if ($locale === null) {
                if ($createNewLocales) {
                    $locale = LocaleEntity::create($transifexLocale)
                        ->setIsApproved($approveTranslations)
                    ;
                    $em->persist($locale);
                    $em->flush($locale);
                    $output->writeln('<info>NOT FOUND -> created</info>');
                } else {
                    $output->writeln('<error>NOT FOUND -> skipped</error>');
                    continue;
                }
            } elseif ($locale->isApproved() === false) {
                if ($approveTranslations) {
                    $locale->setIsApproved(true);
                    $em->persist($locale);
                    $em->flush($locale);
                    $output->writeln('<info>FOUND BUT NOT APPROVED -> marked as approved</info>');
                } else {
                    $output->writeln('<error>FOUND BUT NOT APPROVED -> skipped</error>');
                    continue;
                }
            } else {
                $output->writeln('<info>found</info>');
            }

            foreach ($transifexResources as $transifexResource) {
                $output->write('   > retrieving strings for resource ' . $transifexResource . '... ');
                $translationsData = $this->fetchTransifexTranslations($input->getArgument('project'), $transifexResource, $transifexLocale);
                $output->writeln('<info>done</info>');

                $output->write(sprintf('   > parsing translations... '));
                $translations = $this->parseTranslations($translationsData);
                $output->writeln('<info>done (' . count($translations) . ' strings)</info>');

                if ($this->translationsNeedsPluralFix($translations, $locale, $translationsPluralCount)) {
                    $output->write(sprintf('   > fixing plural count (from %1$s to %2$s)... ', $translationsPluralCount, $locale->getPluralCount()));
                    $translations->setLanguage($locale->getID());
                    $output->writeln('<info>done</info>');
                }

                $output->write('   > saving... ');
                $details = $translationsImporter->import($translations, $locale, $user, $approveTranslations);
                $output->writeln('<info>done</info>');
                $output->writeln('   > details:');
                $output->writeln('      - strings not translated           : ' . $details->emptyTranslations);
                $output->writeln('      - unknown strings                  : ' . $details->unknownStrings);
                $output->writeln('      - new translations activated       : ' . $details->addedAsCurrent);
                $output->writeln('      - new translations not activated   : ' . $details->addedNotAsCurrent);
                $output->writeln('      - existing translations untouched  : ' . $details->existingCurrentUntouched);
                $output->writeln('      - existing translations approved   : ' . $details->existingCurrentApproved);
                $output->writeln('      - existing translations unapproved : ' . $details->existingCurrentUnapproved);
                $output->writeln('      - existing translations activated  : ' . $details->existingActivated);
                $output->writeln('      - untouched translations           : ' . $details->existingNotCurrentUntouched);
                $output->writeln('      - new translations needing approval: ' . $details->newApprovalNeeded);
            }
        }
        $output->writeln('');
        $output->writeln('<info>All done</info>');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws Exception
     */
    private function getTransifexAccount(InputInterface $input, OutputInterface $output)
    {
        $this->transifexUsername = trim((string) $input->getOption('username'));
        if ($this->transifexUsername === '') {
            if (!$input->isInteractive()) {
                throw new Exception('Please specify the Transifex username');
            }
            $question = new Question('Please specify the Transifex username: ', '');
            $this->transifexUsername = trim((string) $this->getHelper('question')->ask($input, $output, $question));
            if ($this->transifexUsername === '') {
                throw new Exception('Operation aborted');
            }
        }
        $this->transifexPassword = (string) $input->getOption('password');
        if ($this->transifexPassword === '') {
            if (!$input->isInteractive()) {
                throw new Exception('Please specify the Transifex password');
            }
            $question = new Question('Please specify the Transifex password (will be hidden): ', '');
            $question->setHidden(true);
            $this->transifexPassword = (string) $this->getHelper('question')->ask($input, $output, $question);
            if ($this->transifexPassword === '') {
                throw new Exception('Operation aborted');
            }
        }
    }

    /**
     * @return \Concrete\Core\Http\Client\Client
     */
    private function getHttpClient()
    {
        $client = $this->app->make('http/client');
        $client
            ->setOptions(['timeout' => 60])
            ->setAuth($this->transifexUsername, $this->transifexPassword)
        ;

        return $client;
    }

    /**
     * @param string $transifexProject
     *
     * @throws Exception
     *
     * @return string[]
     */
    private function fetchTransifexLocales($transifexProject)
    {
        $client = $this->getHttpClient();

        $response = $client
            ->setUri("https://www.transifex.com/api/2/project/$transifexProject/languages/")
            ->setMethod('GET')
            ->send()
        ;
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
        $rawLocales = @json_decode($response->getBody(), true);
        if (!is_array($rawLocales)) {
            throw new Exception('Failed to decode response');
        }
        $sourceLocale = $this->app->make('community_translation/sourceLocale');
        $sourceVariants = [strtolower($sourceLocale)];
        if (preg_match('/^(.+?)[\-_]/', $sourceLocale, $m)) {
            $sourceVariants[] = strtolower($m[1]);
        }
        $result = [];
        foreach ($rawLocales as $rawLocale) {
            $id = $rawLocale['language_code'];
            if (!in_array(strtolower($id), $sourceVariants)) {
                $result[] = $id;
            }
        }

        sort($result);

        return $result;
    }

    /**
     * @param string $transifexProject
     *
     * @throws Exception
     *
     * @return string[]
     */
    private function fetchTransifexResources($transifexProject)
    {
        $client = $this->getHttpClient();

        $response = $client
            ->setUri("https://www.transifex.com/api/2/project/$transifexProject/resources/")
            ->setMethod('GET')
            ->send()
        ;
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }
        $rawResources = @json_decode($response->getBody(), true);
        if (!is_array($rawResources)) {
            throw new Exception('Failed to decode response');
        }

        $result = [];
        foreach ($rawResources as $rawResource) {
            $result[] = $rawResource['slug'];
        }

        sort($result);

        return $result;
    }

    /**
     * @param string $transifexProject
     * @param string $transifexResource
     * @param string $transifexLocale
     *
     * @throws Exception
     *
     * @return string
     */
    private function fetchTransifexTranslations($transifexProject, $transifexResource, $transifexLocale)
    {
        $client = $this->getHttpClient();

        $response = $client
            ->setUri("https://www.transifex.com/api/2/project/$transifexProject/resource/$transifexResource/translation/$transifexLocale/")
            ->setMethod('GET')
            ->send()
        ;
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new Exception($response->getReasonPhrase());
        }

        $data = @json_decode($response->getBody(), true);
        if (!is_array($data) || !isset($data['mimetype']) || $data['mimetype'] !== 'text/x-po' || !isset($data['content']) || !is_string($data['content']) || $data['content'] === '') {
            throw new Exception('Failed to decode response');
        }

        return $data['content'];
    }

    /**
     * @param string $translationsData
     *
     * @throws Exception
     *
     * @return Translations
     */
    private function parseTranslations($translationsData)
    {
        $translations = Translations::fromPoString($translationsData);
        if (count($translations) < 1) {
            throw new Exception('No translations downloaded');
        }

        return $translations;
    }

    /**
     * @param Translations $translations
     * @param LocaleEntity $locale
     *
     * @return bool
     */
    private function translationsNeedsPluralFix(Translations $translations, LocaleEntity $locale, &$translationsPluralCount)
    {
        $txPlurals = $translations->getPluralForms();
        $translationsPluralCount = isset($txPlurals) ? $txPlurals[0] : null;

        return $translationsPluralCount !== $locale->getPluralCount();
    }
}
