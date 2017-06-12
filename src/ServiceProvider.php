<?php
namespace CommunityTranslation;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Application\Application;
use Concrete\Core\Foundation\Service\Provider;
use Doctrine\ORM\EntityManager;

class ServiceProvider extends Provider
{
    public function register()
    {
        $this->registerSingletonServices($this->app);
        $this->registerEntityRepositories($this->app);
        $this->registerConfiguration($this->app);
        $this->registerInterfaces($this->app);
        $this->registerTranslationConverters($this->app);
    }

    /**
     * @param Application $app
     */
    private function registerSingletonServices(Application $app)
    {
        foreach ([
            \CommunityTranslation\Parser\Provider::class,
            \CommunityTranslation\Service\Access::class,
            \CommunityTranslation\Service\Editor::class,
            \CommunityTranslation\Service\Groups::class,
            \CommunityTranslation\Service\TranslationsFileExporter::class,
            \CommunityTranslation\Service\User::class,
            \CommunityTranslation\Translation\Exporter::class,
            \CommunityTranslation\Translation\Importer::class,
        ] as $className) {
            $app->singleton($className);
        }
    }

    /**
     * @param Application $app
     */
    private function registerEntityRepositories(Application $app)
    {
        foreach ([
            'DownloadStats',
            'GitRepository',
            'Glossary\Entry',
            'Glossary\Entry\Localized',
            'Locale',
            'LocaleStats',
            'Notification',
            'Package',
            'Package\Version',
            'PackageSubscription',
            'PackageVersionSubscription',
            'RemotePackage',
            'Stats',
            'Translatable',
            'Translatable\Comment',
            'Translatable\Place',
            'Translation',
        ] as $path) {
            $app->singleton("CommunityTranslation\\Repository\\$path", function () use ($app, $path) {
                return $app->make(EntityManager::class)->getRepository("CommunityTranslation\\Entity\\$path");
            });
        }
    }

    /**
     * @param Application $app
     */
    private function registerConfiguration(Application $app)
    {
        $app->singleton('community_translation/config', function () use ($app) {
            $pkg = $app->make(\Concrete\Core\Package\PackageService::class)->getClass('community_translation');

            return $pkg->getFileConfig();
        });

        $app->singleton('community_translation/sourceLocale', function () use ($app) {
            $repo = $app->make(LocaleRepository::class);
            $locale = $repo->findOneBy(['isSource' => true]);

            return $locale === null ? null : $locale->getID();
        });
    }

    private function registerInterfaces(Application $app)
    {
        $app->singleton(\CommunityTranslation\Parser\ParserInterface::class, function () use ($app) {
            $config = $app->make('community_translation/config');

            return $app->make($config->get('options.parser'));
        });
    }

    /**
     * @param Application $app
     */
    private function registerTranslationConverters(Application $app)
    {
        $this->app->singleton(\CommunityTranslation\TranslationsConverter\Provider::class, function () use ($app) {
            $provider = $app->build(\CommunityTranslation\TranslationsConverter\Provider::class);
            $provider->register($app->make(\CommunityTranslation\TranslationsConverter\JedConverter::class));
            $provider->register($app->make(\CommunityTranslation\TranslationsConverter\JsonDictionaryConverter::class));
            $provider->register($app->make(\CommunityTranslation\TranslationsConverter\MoConverter::class));
            $provider->register($app->make(\CommunityTranslation\TranslationsConverter\PhpArrayConverter::class));
            $provider->register($app->make(\CommunityTranslation\TranslationsConverter\PoConverter::class));

            return $provider;
        });
    }
}
