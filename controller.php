<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation;

use CommunityTranslation\Api\EntryPoint as ApiEntryPoint;
use CommunityTranslation\Console\Command;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Notification\CategoryInterface;
use CommunityTranslation\Parser\ConcreteCMSParser;
use CommunityTranslation\Parser\Provider as ParserProvider;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\EntitiesEventSubscriber;
use CommunityTranslation\Service\EventSubscriber;
use CommunityTranslation\ServiceProvider;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Page;
use Concrete\Core\Routing\Router;
use Doctrine\ORM\EntityManager;
use Gettext\Translations;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * The package controller.
 *
 * Handle installation/upgrading and startup of Community Translation.
 */
class Controller extends Package implements ProviderAggregateInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$appVersionRequired
     */
    protected $appVersionRequired = '9.0.3a1';

    /**
     * The package unique handle.
     *
     * @var string
     */
    protected $pkgHandle = 'community_translation';

    /**
     * The package version.
     *
     * @var string
     */
    protected $pkgVersion = '1.6.5';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$pkgAutoloaderRegistries
     */
    protected $pkgAutoloaderRegistries = [
        'src' => 'CommunityTranslation',
    ];

    /**
     * This needs to be static because the core may use different instances of this controller class while upgrading.
     */
    protected static string $upgradingFromVersion = '';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageName()
     */
    public function getPackageName()
    {
        return t('Community Translation');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageDescription()
     */
    public function getPackageDescription()
    {
        return t('Community-driven collaborative translation');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface::getEntityManagerProvider()
     */
    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'CommunityTranslation\Entity',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        parent::install();
        $this->installXml();
        $this->registerServiceProvider();
        $this->configureSourceLocale();
        $this->refreshLatestPackageVersions();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgradeCoreData()
     */
    public function upgradeCoreData()
    {
        $e = $this->getPackageEntity();
        if ($e !== null) {
            self::$upgradingFromVersion = (string) $e->getPackageVersion();
        }
        parent::upgradeCoreData();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgrade()
     */
    public function upgrade()
    {
        parent::upgrade();
        $this->installXml();
        if (self::$upgradingFromVersion !== '') {
            if (version_compare(self::$upgradingFromVersion, '0.4.0') < 0) {
                $this->refreshLatestPackageVersions();
            }
            if (version_compare(self::$upgradingFromVersion, '1.6.0') < 0) {
                $this->updatePackageNamesAndSources();
                $this->deletePage('/dashboard/community_translation/options/cli');
            }
        }
    }

    /**
     * Initialize the package.
     */
    public function on_start()
    {
        $this->registerServiceProvider();
        $this->registerParsers();
        $this->app->make('director')->addSubscriber($this->app->make(EventSubscriber::class));
        if ($this->app->isRunThroughCommandLineInterface()) {
            $this->registerCLICommands();
        } else {
            $this->registerAssets();
            $this->registerRoutes();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getTranslatableStrings()
     */
    public function getTranslatableStrings(Translations $translations)
    {
        $classPrefix = 'CommunityTranslation\\Notification\\Category';
        $filePrefix = $this->getPackagePath() . '/src/Notification/Category';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePrefix));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || strcasecmp($file->getExtension(), 'php') !== 0) {
                continue;
            }
            $filePath = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
            $relativePath = basename(substr($filePath, strlen($filePrefix) + 1), '.php');
            $className = $classPrefix . '\\' . str_replace('/', '\\', $relativePath);
            if (!is_a($className, CategoryInterface::class, true)) {
                continue;
            }
            $class = new ReflectionClass($className);
            if ($class->isAbstract()) {
                continue;
            }
            $translations->insert('NotificationCategory', $className::getDescription());
        }
    }

    /**
     * Install/refresh stuff from the XML installation file.
     */
    private function installXml(): void
    {
        $this->installContentFile('install.xml');
    }

    /**
     * Configure the source locale.
     */
    private function configureSourceLocale(): void
    {
        $em = $this->app->make(EntityManager::class);
        $repo = $this->app->make(LocaleRepository::class);
        if ($repo->findOneBy(['isSource' => true]) === null) {
            $locale = new LocaleEntity('en_US');
            $locale
                ->setIsApproved(true)
                ->setIsSource(true)
            ;
            $em->persist($locale);
            $em->flush($locale);
        }
    }

    private function refreshLatestPackageVersions(): void
    {
        $ees = $this->app->make(EntitiesEventSubscriber::class);
        $packageRepo = $this->app->make(PackageRepository::class);
        $em = $this->app->make(EntityManager::class);
        foreach ($packageRepo->findAll() as $package) {
            $ees->refreshPackageLatestVersion($em, $package);
        }
    }

    private function updatePackageNamesAndSources(): void
    {
        $cn = $this->app->make(EntityManager::class)->getConnection();
        $cn->executeStatement(
            <<<'EOT'
UPDATE CommunityTranslationPackages
INNER JOIN CommunityTranslationRemotePackages ON CommunityTranslationPackages.handle = CommunityTranslationRemotePackages.handle
SET CommunityTranslationPackages.fromRemotePackage = 1
EOT
        );
        $cn->executeStatement(
            <<<'EOT'
UPDATE CommunityTranslationPackages
INNER JOIN CommunityTranslationGitRepositories ON CommunityTranslationPackages.handle = CommunityTranslationGitRepositories.packageHandle
SET CommunityTranslationPackages.fromGitRepository = 1
EOT
        );
        $rs = $cn->executeQuery(
            <<<'EOT'
SELECT
    id,
    packageHandle
FROM
    CommunityTranslationGitRepositories
WHERE
    packageName = ''
EOT
        );
        while (($row = $rs->fetchAssociative()) !== false) {
            $segments = preg_split('/[_-]+/', $row['packageHandle'], -1, PREG_SPLIT_NO_EMPTY);
            if ($segments === []) {
                $packageName = $row['packageHandle'];
            } else {
                $parts = [];
                foreach ($segments as $segment) {
                    $len = mb_strlen($segment);
                    if ($len === 1) {
                        $parts[] = mb_strtoupper($segment);
                    } else {
                        $parts[] = mb_strtoupper(mb_substr($segment, 0, 1)) . mb_substr($segment, 1);
                    }
                }
                $packageName = implode(' ', $parts);
            }
            $cn->update('CommunityTranslationGitRepositories', ['packageName' => $packageName], ['id' => $row['id']]);
        }
    }

    private function deletePage(string $path): void
    {
        $page = Page::getByPath($path);
        if ($page && !$page->isError()) {
            $page->delete();
        }
    }

    /**
     * Register some commonly used service classes.
     */
    private function registerServiceProvider(): void
    {
        $provider = $this->app->make(ServiceProvider::class);
        $provider->register();
    }

    private function registerParsers(): void
    {
        $this->app->make(ParserProvider::class)->registerParserClass(ConcreteCMSParser::class);
    }

    /**
     * Register the CLI commands.
     */
    private function registerCLICommands(): void
    {
        $console = $this->app->make('console');
        $console->addCommands([
            new Command\AcceptPendingJoinRequestsCommand(),
            new Command\NotifyPackageVersionsCommand(),
            new Command\ProcessGitRepositoriesCommand(),
            new Command\ProcessRemotePackagesCommand(),
            new Command\SendNotificationsCommand(),
            new Command\TransifexGlossaryCommand(),
            new Command\TransifexTranslationsCommand(),
        ]);
    }

    /**
     * Register the assets.
     */
    private function registerAssets(): void
    {
        $al = AssetList::getInstance();
        $al->registerMultiple([
            'community_translation/table-sortable' => [
                ['css', 'css/table-sortable.css', ['minify' => false, 'combine' => true], $this],
                ['javascript', 'js/table-sortable.js', ['minify' => false, 'combine' => true], $this],
            ],
            'community_translation/bootstrap' => [
                ['javascript', 'js/bootstrap.js', ['minify' => false, 'combine' => false], $this],
            ],
            'community_translation/markdown-it' => [
                ['javascript', 'js/markdown-it.js', ['minify' => false, 'combine' => false], $this],
            ],
            'community_translation/progress-bar' => [
                ['css', 'css/progress-bar.css', ['minify' => false, 'combine' => true], $this],
            ],
            'community_translation/online-translation' => [
                ['css', 'css/online-translation.css', ['minify' => false, 'combine' => false], $this],
            ],
        ]);
        $al->registerGroupMultiple([
            'community_translation/table-sortable' => [
                [
                    ['css', 'font-awesome'],
                    ['css', 'community_translation/table-sortable'],
                    ['javascript', 'jquery'],
                    ['javascript', 'community_translation/table-sortable'],
                ],
            ],
            'community_translation/progress-bar' => [
                [
                    ['css', 'community_translation/progress-bar'],
                ],
            ],
            'community_translation/online-translation' => [
                [
                    ['css', 'font-awesome'],
                    ['css', 'community_translation/online-translation'],
                    ['javascript', 'jquery'],
                    ['javascript', 'vue'],
                    ['javascript', 'community_translation/bootstrap'],
                    ['javascript', 'community_translation/markdown-it'],
                ],
            ],
        ]);
    }

    /**
     * Register the routes.
     */
    private function registerRoutes(): void
    {
        $config = $this->app->make(Repository::class);
        $onlineTranslationPath = '/' . trim((string) $config->get('community_translation::paths.onlineTranslation'), '/');
        $apiBasePath = '/' . trim((string) $config->get('community_translation::paths.api'), '/');
        $handleRegex = '[A-Za-z0-9]([A-Za-z0-9\_]*[A-Za-z0-9])?';
        $localeRegex = '[a-zA-Z]{2,3}([_\-][a-zA-Z0-9]{2,3})?';
        $this->app->make(Router::class)->registerMultiple([
            // Online Translation
            "{$onlineTranslationPath}/{packageVersionID}/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::view',
                null,
                ['packageVersionID' => 'unreviewed|[1-9][0-9]*', 'localeID' => $localeRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$onlineTranslationPath}/action/save_comment/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_comment',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/delete_comment/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::delete_comment',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/load_all_places/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::load_all_places',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/process_translation/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::process_translation',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/load_translation/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::load_translation',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/save_glossary_entry/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_glossary_entry',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/delete_glossary_entry/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::delete_glossary_entry',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/download/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::download',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/upload/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::upload',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$onlineTranslationPath}/action/save_notifications/{packageID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_notifications',
                null,
                ['packageID' => '\d+'],
                [],
                '',
                [],
                ['POST'],
            ],
            // API Entry Points
            "{$apiBasePath}/rate-limit" => [
                ApiEntryPoint\GetRateLimit::class . '::__invoke',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/locales" => [
                ApiEntryPoint\GetLocales::class . '::__invoke',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/packages" => [
                ApiEntryPoint\GetPackages::class . '::__invoke',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/package/{packageHandle}/versions" => [
                ApiEntryPoint\GetPackageVersions::class . '::__invoke',
                null,
                ['packageHandle' => $handleRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/package/{packageHandle}/{packageVersion}/locales/{minimumLevel}" => [
                ApiEntryPoint\GetPackageVersionLocales::class . '::__invoke',
                null,
                ['packageHandle' => $handleRegex, 'minimumLevel' => '[0-9]{1,3}'],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/package/{packageHandle}/{packageVersion}/translations/{localeID}/{formatHandle}" => [
                ApiEntryPoint\GetPackageVersionTranslations::class . '::__invoke',
                null,
                ['packageHandle' => $handleRegex, 'localeID' => $localeRegex, 'formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "{$apiBasePath}/fill-translations/{formatHandle}" => [
                ApiEntryPoint\FillTranslations::class . '::__invoke',
                null,
                ['formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$apiBasePath}/package/{packageHandle}/{packageVersion}/translatables/{formatHandle}" => [
                ApiEntryPoint\ImportPackageVersionTranslatables::class . '::__invoke',
                null,
                ['packageHandle' => $handleRegex, 'formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$apiBasePath}/translations/{localeID}/{formatHandle}/{approve}" => [
                ApiEntryPoint\ImportTranslations::class . '::__invoke',
                null,
                ['localeID' => $localeRegex, 'formatHandle' => $handleRegex, 'approve' => '[01]'],
                [],
                '',
                [],
                ['POST'],
            ],
            "{$apiBasePath}/import/package" => [
                ApiEntryPoint\ImportPackage::class . '::__invoke',
                null,
                [],
                [],
                '',
                [],
                ['PUT'],
            ],
            "{$apiBasePath}/{unrecognizedPath}" => [
                ApiEntryPoint\Unrecognized::class . '::__invoke',
                null,
                ['unrecognizedPath' => '.*'],
            ],
            "{$apiBasePath}" => [
                ApiEntryPoint\Unrecognized::class . '::__invoke',
            ],
        ]);
    }
}
