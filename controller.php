<?php

namespace Concrete\Package\CommunityTranslation;

use CommunityTranslation\Console\Command\AcceptPendingJoinRequestsCommand;
use CommunityTranslation\Console\Command\NotifyPackageVersionsCommand;
use CommunityTranslation\Console\Command\ProcessGitRepositoriesCommand;
use CommunityTranslation\Console\Command\ProcessRemotePackagesCommand;
use CommunityTranslation\Console\Command\RemoveLoggedIPAddressesCommand;
use CommunityTranslation\Console\Command\SendNotificationsCommand;
use CommunityTranslation\Console\Command\TransifexGlossaryCommand;
use CommunityTranslation\Console\Command\TransifexTranslationsCommand;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Parser\Concrete5Parser;
use CommunityTranslation\Parser\Provider as ParserProvider;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Service\EntitiesEventSubscriber;
use CommunityTranslation\Service\EventSubscriber;
use CommunityTranslation\ServiceProvider;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Package\Package;
use Concrete\Core\Routing\Router;
use Doctrine\ORM\EntityManager;

/**
 * The package controller.
 *
 * Handle installation/upgrading and startup of Community Translation.
 */
class Controller extends Package
{
    /**
     * The minimum concrete5 version.
     *
     * @var string
     */
    protected $appVersionRequired = '8.2.0';

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
    protected $pkgVersion = '0.6.7';

    /**
     * The mapping between RelativeDirectory <-> Namespace to autoload package classes.
     *
     * @var array
     */
    protected $pkgAutoloaderRegistries = [
        'src' => 'CommunityTranslation',
    ];

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

    private $upgradingFromVersion = null;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgradeCoreData()
     */
    public function upgradeCoreData()
    {
        $e = $this->getPackageEntity();
        if ($e !== null) {
            $this->upgradingFromVersion = $e->getPackageVersion();
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
        if ($this->upgradingFromVersion !== null && version_compare($this->upgradingFromVersion, '0.4.0') < 0) {
            $this->refreshLatestPackageVersions();
        }
    }

    /**
     * Install/refresh stuff from the XML installation file.
     */
    private function installXml()
    {
        $contentImporter = $this->app->make(ContentImporter::class);
        $contentImporter->importContentFile($this->getPackagePath() . '/install.xml');
    }

    /**
     * Configure the source locale.
     */
    private function configureSourceLocale()
    {
        $em = $this->app->make(EntityManager::class);
        /* @var EntityManager $em */
        $repo = $this->app->make(LocaleRepository::class);
        if ($repo->findOneBy(['isSource' => true]) === null) {
            $locale = LocaleEntity::create('en_US');
            $locale
                ->setIsApproved(true)
                ->setIsSource(true)
            ;
            $em->persist($locale);
            $em->flush($locale);
        }
    }

    private function refreshLatestPackageVersions()
    {
        $ees = $this->app->make(EntitiesEventSubscriber::class);
        /* @var EntitiesEventSubscriber $ees */
        $packageRepo = $this->app->make(PackageRepository::class);
        /* @var PackageRepository $packageRepo */
        $em = $this->app->make(EntityManager::class);
        /* @var EntityManager $em */
        foreach ($packageRepo->findAll() as $package) {
            $ees->refreshPackageLatestVersion($em, $package);
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
     * Register some commonly used service classes.
     */
    private function registerServiceProvider()
    {
        $provider = $this->app->make(ServiceProvider::class);
        $provider->register();
    }

    private function registerParsers()
    {
        $this->app->make(ParserProvider::class)->register(Concrete5Parser::class);
    }

    /**
     * Register the CLI commands.
     */
    private function registerCLICommands()
    {
        $console = $this->app->make('console');
        $console->add(new TransifexTranslationsCommand());
        $console->add(new TransifexGlossaryCommand());
        $console->add(new ProcessGitRepositoriesCommand());
        $console->add(new AcceptPendingJoinRequestsCommand());
        $console->add(new SendNotificationsCommand());
        $console->add(new RemoveLoggedIPAddressesCommand());
        $console->add(new NotifyPackageVersionsCommand());
        $console->add(new ProcessRemotePackagesCommand());
    }

    /**
     * Register the assets.
     */
    private function registerAssets()
    {
        $al = AssetList::getInstance();
        $al->registerMultiple([
            'jquery/scroll-to' => [
                ['javascript', 'js/jquery.scrollTo.min.js', ['minify' => true, 'combine' => true, 'version' => '2.1.2'], $this],
            ],
            'community_translation/online_translation/bootstrap' => [
                ['javascript', 'js/bootstrap.min.js', ['minify' => false, 'combine' => true], $this],
            ],
            'community_translation/online_translation/markdown-it' => [
                ['javascript', 'js/markdown-it.min.js', ['minify' => false, 'combine' => true], $this],
            ],
            'community_translation/online_translation/core' => [
                ['css', 'css/online-translation.css', ['minify' => false, 'combine' => true], $this],
                ['javascript', 'js/online-translation.js', ['minify' => true, 'combine' => true], $this],
            ],
            'jquery/comtraSortable' => [
                ['css', 'css/jquery.comtraSortable.css', ['minify' => true, 'combine' => true], $this],
                ['javascript', 'js/jquery.comtraSortable.js', ['minify' => true, 'combine' => true], $this],
            ],
        ]);
        $al->registerGroupMultiple([
            'jquery/scroll-to' => [
                [
                    ['javascript', 'jquery'],
                    ['javascript', 'jquery/scroll-to'],
                ],
            ],
            'community_translation/online_translation' => [
                [
                    ['css', 'community_translation/online_translation/core'],
                    ['javascript', 'community_translation/online_translation/bootstrap'],
                    ['javascript', 'community_translation/online_translation/markdown-it'],
                    ['javascript', 'community_translation/online_translation/core'],
                ],
            ],
            'jquery/comtraSortable' => [
                [
                    ['css', 'font-awesome'],
                    ['css', 'jquery/comtraSortable'],
                    ['javascript', 'jquery/comtraSortable'],
                ],
            ],
        ]);
    }

    /**
     * Register the routes.
     */
    private function registerRoutes()
    {
        $config = $this->app->make('community_translation/config');
        $onlineTranslationPath = $config->get('options.onlineTranslationPath');
        $apiEntryPoint = $config->get('options.api.entryPoint');
        $handleRegex = '[A-Za-z0-9]([A-Za-z0-9\_]*[A-Za-z0-9])?';
        $localeRegex = '[a-zA-Z]{2,3}([_\-][a-zA-Z0-9]{2,3})?';
        $this->app->make(Router::class)->registerMultiple([
            // Online Translation
            "$onlineTranslationPath/{packageVersionID}/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::view',
                null,
                ['packageVersionID' => 'unreviewed|[1-9][0-9]*', 'localeID' => $localeRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "$onlineTranslationPath/action/save_comment/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_comment',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/delete_comment/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::delete_comment',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/load_all_places/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::load_all_places',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/process_translation/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::process_translation',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/load_translation/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::load_translation',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/save_glossary_term/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_glossary_term',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/delete_glossary_term/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::delete_glossary_term',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/download/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::download',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/upload/{localeID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::upload',
                null,
                ['localeID' => $localeRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$onlineTranslationPath/action/save_notifications/{packageID}" => [
                'Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation::save_notifications',
                null,
                ['packageID' => '\d+'],
                [],
                '',
                [],
                ['POST'],
            ],
            // API Entry Points
            "$apiEntryPoint/rate-limit/" => [
                'CommunityTranslation\Api\EntryPoint::getRateLimit',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/locales/" => [
                'CommunityTranslation\Api\EntryPoint::getLocales',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/packages/" => [
                'CommunityTranslation\Api\EntryPoint::getPackages',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/package/{packageHandle}/versions/" => [
                'CommunityTranslation\Api\EntryPoint::getPackageVersions',
                null,
                ['packageHandle' => $handleRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/package/{packageHandle}/{packageVersion}/locales/{minimumLevel}/" => [
                'CommunityTranslation\Api\EntryPoint::getPackageVersionLocales',
                null,
                ['packageHandle' => $handleRegex, 'minimumLevel' => '[0-9]{1,3}'],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/package/{packageHandle}/{packageVersion}/translations/{localeID}/{formatHandle}/" => [
                'CommunityTranslation\Api\EntryPoint::getPackageVersionTranslations',
                null,
                ['packageHandle' => $handleRegex, 'localeID' => $localeRegex, 'formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/fill-translations/{formatHandle}/" => [
                'CommunityTranslation\Api\EntryPoint::fillTranslations',
                null,
                ['formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$apiEntryPoint/package/{packageHandle}/{packageVersion}/translatables/{formatHandle}/" => [
                'CommunityTranslation\Api\EntryPoint::importPackageVersionTranslatables',
                null,
                ['packageHandle' => $handleRegex, 'formatHandle' => $handleRegex],
                [],
                '',
                [],
                ['POST'],
            ],
            "$apiEntryPoint/translations/{localeID}/{formatHandle}/{approve}/" => [
                'CommunityTranslation\Api\EntryPoint::importTranslations',
                null,
                ['localeID' => $localeRegex, 'formatHandle' => $handleRegex, 'approve' => '[01]'],
                [],
                '',
                [],
                ['POST'],
            ],
            "$apiEntryPoint/import/package/" => [
                'CommunityTranslation\Api\EntryPoint::importPackage',
                null,
                [],
                [],
                '',
                [],
                ['PUT'],
            ],
            "$apiEntryPoint/{unrecognizedPath}" => [
                'CommunityTranslation\Api\EntryPoint::unrecognizedCall',
                null,
                ['unrecognizedPath' => '.*'],
            ],
            "$apiEntryPoint" => [
                'CommunityTranslation\Api\EntryPoint::unrecognizedCall',
            ],
        ]);
    }
}
