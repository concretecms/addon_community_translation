<?php
namespace Concrete\Package\CommunityTranslation;

use CommunityTranslation\Console\Command\ProcessGitRepositoriesCommand;
use CommunityTranslation\Console\Command\TransifexGlossaryCommand;
use CommunityTranslation\Console\Command\TransifexTranslationsCommand;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Service\EventSubscriber;
use CommunityTranslation\ServiceProvider;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Package\Package;
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
    protected $appVersionRequired = '8.2.0a1';

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
    protected $pkgVersion = '0.0.1';

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
        return t('Translate concrete5 core and packages');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        $pkg = parent::install();
        $this->installXml();
        $this->registerServiceProvider();
        $this->configureSourceLocale();
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

    /**
     * Initialize the package.
     */
    public function on_start()
    {
        $this->registerServiceProvider();
        $this->registerParsers();
        \Route::setThemesByRoutes([
            '/translate/online' => ['community_translation', 'full_page.php'],
        ]);
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
        $this->app->make(\CommunityTranslation\Parser\Provider::class)->register(\CommunityTranslation\Parser\Concrete5Parser::class);
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
            'community_translation/common' => [
                ['javascript', 'js/common.js', ['minify' => true, 'combine' => true], $this],
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
        ]);
        $al->registerGroupMultiple([
            'jquery/scroll-to' => [
                [
                    ['javascript', 'jquery'],
                    ['javascript', 'jquery/scroll-to'],
                ],
            ],
            'community_translation/common' => [
                [
                    ['javascript', 'jquery'],
                    ['javascript', 'community_translation/common'],
                ],
            ],
            'community_translation/online_translation' => [
                [
                    ['css', 'community_translation/online_translation/core'],
                    ['javascript', 'community_translation/online_translation/bootstrap'],
                    ['javascript', 'community_translation/online_translation/markdown-it'],
                    ['javascript', 'community_translation/online_translation/core'],
                ]
            ]
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
        $this->app->make('Concrete\Core\Routing\Router')->registerMultiple([
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
            "$apiEntryPoint/locales/" => [
                'CommunityTranslation\Api\EntryPoint::getApprovedLocales',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/locales/{packageHandle}/{packageVersion}/{minimumLevel}/" => [
                'CommunityTranslation\Api\EntryPoint::getLocalesForPackage',
                null,
                ['packageHandle' => $handleRegex, 'minimumLevel' => '[0-9]{1,3}'],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/packages/" => [
                'CommunityTranslation\Api\EntryPoint::getAvailablePackageHandles',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/package/{packageHandle}/versions/" => [
                'CommunityTranslation\Api\EntryPoint::getAvailablePackageVersions',
                null,
                ['packageHandle' => $handleRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/package/import/translatable/" => [
                'CommunityTranslation\Api\EntryPoint::importPackageTranslatable',
                null,
                [],
                [],
                '',
                [],
                ['POST'],
            ],
            "$apiEntryPoint/package/update/translations/" => [
                'CommunityTranslation\Api\EntryPoint::updatePackageTranslations',
                null,
                [],
                [],
                '',
                [],
                ['POST'],
            ],
            "$apiEntryPoint/package/updated/translations/" => [
                'CommunityTranslation\Api\EntryPoint::recentPackagesUpdated',
                null,
                [],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/po/{packageHandle}/{packageVersion}/{localeID}" => [
                'CommunityTranslation\Api\EntryPoint::getPackagePo',
                null,
                ['packageHandle' => $handleRegex, 'localeID' => $localeRegex],
                [],
                '',
                [],
                ['GET'],
            ],
            "$apiEntryPoint/mo/{packageHandle}/{packageVersion}/{localeID}" => [
                'CommunityTranslation\Api\EntryPoint::getPackageMo',
                null,
                ['packageHandle' => $handleRegex, 'localeID' => $localeRegex],
                [],
                '',
                [],
                ['GET'],
            ],
        ]);
    }
}
