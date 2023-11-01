<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command;

use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\SourceLocale;
use CommunityTranslation\Translation\Importer;
use CommunityTranslation\Translation\ImportOptions;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\Client\Client;
use Doctrine\ORM\EntityManager;
use Generator;
use Gettext\Translations;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Console\Question\Question;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @see https://transifex.github.io/openapi/
 */
class TransifexTranslationsCommand extends Command
{
    private const API_PREFIX = 'https://rest.api.transifex.com';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
ct:transifex:translations:import
    {owner : The handle of the user/organization owning the Transifex project }
    {project : The handle of the Transifex project }
    {--t|token= : The Transifex authentication token }
    {--c|create : Create the locales that are not yet defined }
    {--a|approve : Import the translations as already approved }
    {--l|locale= : The locale identifier (if not specified we'll fetch all Transifex locales) }
    {--r|resource= : The Transifex resource handle (if not specified we'll fetch all the resources) }
EOT
    ;

    private EntityManager $em;

    private UserEntity $translator;

    private LocaleRepository $localeRepo;

    private Client $client;

    private Importer $translationsImporter;

    private LocaleEntity $sourceLocale;

    private string $transifexOwner;

    private string $transifexProject;

    private string $transifexAuthenticationToken;

    private bool $createNewLocales;

    private bool $approveTranslations;

    private string $filterLocaleID;

    private string $transifexResource;

    public function handle(EntityManager $em, Client $client, Importer $translationsImporter, SourceLocale $sourceLocaleService): int
    {
        $this->em = $em;
        $this->translator = $this->em->find(UserEntity::class, USER_SUPER_ID);
        $this->localeRepo = $this->em->getRepository(LocaleEntity::class);
        $this->client = $client;
        $this->translationsImporter = $translationsImporter;
        $this->sourceLocale = $sourceLocaleService->getRequiredSourceLocale();
        $this->readOptions();
        $this->output->write('Retrieve list of available Transifex locales... ');
        $availableTransifexLocales = $this->fetchTransifexLocales();
        $this->output->writeln('<info>done (' . count($availableTransifexLocales) . ' found)</info>');
        $transifexLocales = $this->filterTransifexLocales($availableTransifexLocales);
        if ($this->transifexResource === '') {
            $this->output->write('Retrieve list of available Transifex resources... ');
            $transifexResources = $this->fetchTransifexResources();
            $this->output->writeln('<info>done (' . count($transifexResources) . ' found)</info>');
        } else {
            $transifexResources = [$this->transifexResource];
        }
        $count = 0;
        foreach ($transifexLocales as $transifexLocaleID => $localeCode) {
            $count += $this->processTransifexLocale($transifexLocaleID, $localeCode, $transifexResources);
        }
        $this->output->writeln("<info>Total number of processes translations: {$count}</info>");

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
                'ct:transifex-translations',
            ])
            ->setDescription('Import translations from Transifex')
            ->setHelp(
                <<<'EOT'
You can obtain your Transifex authentication token at the following URL:
https://www.transifex.com/user/settings/api/

Returns codes:
  0 operation completed successfully
  1 errors occurred
EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @see \CommunityTranslation\Console\Command::formatThrowable()
     */
    protected function formatThrowable(Throwable $error): string
    {
        if (!$error instanceof ClientException) {
            return parent::formatThrowable($error);
        }
        $response = $error->getResponse();
        if ($response === null) {
            $result = 'Error communicating with the Transifex server';
            if ($error->getMessage()) {
                $result .= ":\n" . $error->getMessage();
            }

            return $result;
        }
        $result = "Error {$response->getStatusCode()} ({$response->getReasonPhrase()}) from the Transifex server";
        $responseBody = (string) $response->getBody();
        if (trim($responseBody) === '') {
            return $result;
        }
        $contentType = $response->getHeader('Content-Type');
        if (is_array($contentType)) {
            $contentType = array_shift($contentType);
        }
        if (!is_string($contentType) || !preg_match('/^(application\/(vnd\.api\+)?)?json\b/i', $contentType)) {
            return $result . ': ' . trim($responseBody);
        }
        $responseData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($responseData) || !is_array($responseData['errors'] ?? null) || $responseData['errors'] === []) {
            return $result . "\n" . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        foreach ($responseData['errors'] as $error) {
            $title = $error['title'] ?? null;
            if (!is_string($title) || $title === '') {
                $result .= "\n" . json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $result .= "\n{$title}";
                $detail = $error['detail'] ?? null;
                if (is_string($detail) && $detail !== '') {
                    $result .= ": {$detail}";
                }
            }
        }

        return $result;
    }

    private function readOptions(): void
    {
        $transifexOwner = trim((string) $this->input->getArgument('owner'));
        if ($transifexOwner === '') {
            throw new UserMessageException('Please specify the handle of the Transifex owner (user or organization)');
        }
        if (!preg_match('/^[a-zA-Z0-9._\-]{1,255}$/', $transifexOwner)) {
            throw new UserMessageException('The handle of the Transifex owner is malformed');
        }
        $this->transifexOwner = $transifexOwner;
        $transifexProject = trim((string) $this->input->getArgument('project'));
        if ($transifexProject === '') {
            throw new UserMessageException('Please specify the handle of the Transifex project');
        }
        if (!preg_match('/^[a-zA-Z0-9._\-]{1,255}$/', $transifexProject)) {
            throw new UserMessageException('The handle of the Transifex project is malformed');
        }
        $this->transifexProject = $transifexProject;
        $this->transifexAuthenticationToken = $this->readTransifexAuthenticationToken();
        $this->createNewLocales = $this->input->getOption('create');
        $this->approveTranslations = $this->input->getOption('approve');
        $this->filterLocaleID = $this->input->getOption('locale') ?: '';
        $this->transifexResource = $this->input->getOption('resource') ?: '';
    }

    private function readTransifexAuthenticationToken(): string
    {
        $result = (string) $this->input->getOption('token');
        if ($result !== '') {
            return $result;
        }
        if (!$this->input->isInteractive()) {
            throw new UserMessageException('Please specify your Transifex authentication token');
        }
        $question = new Question('Please specify your Transifex authentication token (will be hidden): ', '');
        $question->setHidden(true);
        $result = (string) $this->getHelper('question')->ask($this->input, $this->output, $question);
        if ($result === '') {
            throw new UserMessageException('Operation aborted');
        }

        return $result;
    }

    private function processTransifexLocale(string $transifexLocaleID, string $localeCode, array $transifexResources): int
    {
        $result = 0;
        $this->output->writeln("Working on locale {$localeCode}");
        $locale = $this->getLocaleEntity($localeCode);
        $translationsPluralCount = null;
        foreach ($transifexResources as $transifexResource) {
            $this->output->write("   > retrieving strings for resource {$transifexResource}... ");
            $translationsData = $this->fetchTransifexTranslations($transifexResource, $transifexLocaleID);
            $this->output->writeln('<info>done</info>');
            $this->output->write(sprintf('   > parsing translations... '));
            $translations = $this->parseTranslations($translationsData);
            $this->output->writeln('<info>done (' . count($translations) . ' strings)</info>');
            if ($this->translationsNeedsPluralFix($translations, $locale, $translationsPluralCount)) {
                $this->output->write(sprintf('   > fixing plural count (from %1$s to %2$s)... ', $translationsPluralCount, $locale->getPluralCount()));
                $translations->setLanguage($locale->getID());
                $translations->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
                $this->output->writeln('<info>done</info>');
            }
            $this->output->write('   > saving... ');
            $details = $this->translationsImporter->import($translations, $locale, $this->translator, $this->approveTranslations ? ImportOptions::forAdministrators() : ImportOptions::forTranslators());
            $this->output->writeln('<info>done</info>');
            $this->output->writeln(
                <<<EOT
   > details:
      - strings not translated           : {$details->emptyTranslations}
      - unknown strings                  : {$details->unknownStrings}
      - new translations activated       : {$details->addedAsCurrent}
      - new translations not activated   : {$details->addedNotAsCurrent}
      - existing translations untouched  : {$details->existingCurrentUntouched}
      - existing translations approved   : {$details->existingCurrentApproved}
      - existing translations unapproved : {$details->existingCurrentUnapproved}
      - existing translations activated  : {$details->existingActivated}
      - untouched translations           : {$details->existingNotCurrentUntouched}
      - new translations needing approval: {$details->newApprovalNeeded}

EOT
            );
            $result += $translations->count();
        }

        return $result;
    }

    /**
     * @return array array keys are the Transifex language IDs, array values are the language codes
     */
    private function fetchTransifexLocales(): array
    {
        $sourceVariants = [strtolower($this->sourceLocale->getID())];
        $m = null;
        if (preg_match('/^(.+?)[\-_]/', $this->sourceLocale->getID(), $m)) {
            $sourceVariants[] = strtolower($m[1]);
        }
        $result = [];
        foreach ($this->getTransifexRecords("/projects/o:{$this->transifexOwner}:p:{$this->transifexProject}/languages") as $localeData) {
            $transifexID = $localeData['id'];
            $languageCode = $localeData['attributes']['code'];
            if (!in_array(strtolower($languageCode), $sourceVariants)) {
                $result[$transifexID] = $languageCode;
            }
        }
        if ($result === []) {
            throw new UserMessageException('No locales found in the Transifex project');
        }
        asort($result);

        return $result;
    }

    private function filterTransifexLocales(array $availableLocales): array
    {
        if ($this->filterLocaleID === '') {
            return $availableLocales;
        }
        $result = array_filter(
            $availableLocales,
            function (string $code): bool {
                return strcasecmp($this->filterLocaleID, $code) === 0;
            }
        );
        if ($result === []) {
            throw new UserMessageException("Unable to find the locale with id '{$this->filterLocaleID}'.\nAvailable locales are:\n- " . implode("\n- ", $availableLocales));
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function fetchTransifexResources(): array
    {
        $result = [];
        foreach ($this->getTransifexRecords("/resources?filter[project]=o:{$this->transifexOwner}:p:{$this->transifexProject}") as $resourceData) {
            $result[] = $resourceData['attributes']['slug'];
        }
        if ($result === []) {
            throw new UserMessageException('No resources found in the Transifex project');
        }
        sort($result);

        return $result;
    }

    private function fetchTransifexTranslations(string $transifexResource, string $transifexLocaleID): string
    {
        $downloadJobID = $this->startTransifexTranslationsDownloadJob($transifexResource, $transifexLocaleID);
        $downloadUrl = '';
        for ($sleepTime = 1000000.; $downloadUrl === ''; $sleepTime *= 1.3) {
            usleep((int) $sleepTime);
            $downloadUrl = $this->inspectTransifexTranslationsDownloadJob($downloadJobID);
        }

        return $this->downloadTransifexTranslations($downloadUrl);
    }

    private function startTransifexTranslationsDownloadJob(string $transifexResource, string $transifexLocaleID): string
    {
        $request = new Request(
            'POST',
            self::API_PREFIX . '/resource_translations_async_downloads',
            [
                'Authorization' => "Bearer {$this->transifexAuthenticationToken}",
                'Content-Type' => 'application/vnd.api+json',
            ],
            json_encode([
                'data' => [
                    'type' => 'resource_translations_async_downloads',
                    'relationships' => [
                        'language' => [
                            'data' => [
                                'type' => 'languages',
                                'id' => $transifexLocaleID,
                            ],
                        ],
                        'resource' => [
                            'data' => [
                                'type' => 'resources',
                                'id' => "o:{$this->transifexOwner}:p:{$this->transifexProject}:r:{$transifexResource}",
                            ],
                        ],
                    ],
                ],
            ])
        );
        $response = $this->client->send($request);
        $responseData = json_decode((string) $response->getBody(), true, JSON_THROW_ON_ERROR);

        return $responseData['data']['id'];
    }

    private function inspectTransifexTranslationsDownloadJob(string $downloadJobID): string
    {
        $request = new Request(
            'GET',
            self::API_PREFIX . "/resource_translations_async_downloads/{$downloadJobID}",
            [
                'Authorization' => "Bearer {$this->transifexAuthenticationToken}",
            ]
        );
        $response = $this->client->send($request, [RequestOptions::ALLOW_REDIRECTS => false]);
        if ($response->getStatusCode() === 303) {
            $location = $response->getHeader('Location');
            if (is_array($location)) {
                $location = array_shift($location);
            }
            if (!is_string($location) || $location === '') {
                throw new UserMessageException('The Transifex download job did not provide the URL to download the translations.');
            }

            return $location;
        }
        $responseData = json_decode((string) $response->getBody(), true, JSON_THROW_ON_ERROR);
        switch ($responseData['data']['attributes']['status']) {
            case 'pending':
            case 'processing':
                return '';
            case 'failed':
                $error = $responseData['data']['attributes']['errors'][0]['detail'] ?? null;
                if (!is_string($error) || $error === '') {
                    throw new UserMessageException('The export of Transifex translations failed.');
                }
                throw new UserMessageException("The export of Transifex translations failed: {$error}");
        }
        throw new UserMessageException("Unrecognized Transifex response:\n" . json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function downloadTransifexTranslations(string $downloadUrl): string
    {
        $request = new Request(
            'GET',
            $downloadUrl,
            [
                'Authorization' => "Bearer {$this->transifexAuthenticationToken}",
            ]
        );
        $response = $this->client->send($request, [RequestOptions::ALLOW_REDIRECTS => false]);

        return (string) $response->getBody();
    }

    private function parseTranslations(string $translationsData): Translations
    {
        $translations = Translations::fromPoString($translationsData);
        if (count($translations) < 1) {
            throw new UserMessageException('No translations downloaded');
        }

        return $translations;
    }

    private function translationsNeedsPluralFix(Translations $translations, LocaleEntity $locale, & $translationsPluralCount): bool
    {
        $txPlurals = $translations->getPluralForms();
        $translationsPluralCount = isset($txPlurals) ? $txPlurals[0] : null;

        return $translationsPluralCount !== $locale->getPluralCount();
    }

    /**
     * @return mixed[]
     */
    private function getTransifexRecords(string $url): Generator
    {
        $url = self::API_PREFIX . $url;
        while ($url !== '') {
            $request = new Request(
                'GET',
                $url,
                [
                    'Authorization' => "Bearer {$this->transifexAuthenticationToken}",
                ]
            );
            $response = $this->client->send($request);
            $responseData = json_decode((string) $response->getBody(), true, JSON_THROW_ON_ERROR);
            foreach ($responseData['data'] as $record) {
                yield $record;
            }
            $url = (string) ($responseData['links']['next'] ?? '');
        }
    }

    private function getLocaleEntity(string $localeCode): ?LocaleEntity
    {
        $this->output->write('   > checking Locale entity... ');
        $locale = $this->localeRepo->find($localeCode);
        if ($locale === null) {
            if ($this->createNewLocales) {
                $locale = new LocaleEntity($localeCode);
                $locale->setIsApproved($this->approveTranslations);
                $this->em->persist($locale);
                $this->em->flush($locale);
                $this->output->writeln('<info>NOT FOUND -> created</info>');

                return $locale;
            }
            $this->output->writeln('<error>NOT FOUND -> skipped</error>');

            return null;
        }
        if ($locale->isApproved() === false) {
            if ($this->approveTranslations) {
                $locale->setIsApproved(true);
                $this->em->persist($locale);
                $this->em->flush($locale);
                $this->output->writeln('<info>FOUND BUT NOT APPROVED -> marked as approved</info>');

                return $locale;
            }
            $this->output->writeln('<error>FOUND BUT NOT APPROVED -> skipped</error>');

            return null;
        }
        $this->output->writeln('<info>found</info>');

        return $locale;
    }
}
