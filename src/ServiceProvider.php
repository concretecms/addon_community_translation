<?php

declare(strict_types=1);

namespace CommunityTranslation;

use CommunityTranslation\Service\EntitiesEventSubscriber;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Foundation\Service\Provider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

defined('C5_EXECUTE') or die('Access Denied.');

class ServiceProvider extends Provider
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Foundation\Service\Provider::register()
     */
    public function register()
    {
        $this->registerSingletonServices($this->app);
        $this->registerEntityRepositories($this->app);
        $this->registerInterfaces($this->app);
        $this->registerTranslationConverters($this->app);
        $this->registerEventSubscribers($this->app);
    }

    private function registerSingletonServices(Application $app): void
    {
        foreach ([
            \CommunityTranslation\Parser\Provider::class,
            \CommunityTranslation\Service\Access::class,
            \CommunityTranslation\Service\Editor::class,
            \CommunityTranslation\Service\Group::class,
            \CommunityTranslation\Service\SourceLocale::class,
            \CommunityTranslation\Service\User::class,
            \CommunityTranslation\Service\VolatileDirectoryCreator::class,
            \CommunityTranslation\Translation\Exporter::class,
            \CommunityTranslation\Translation\FileExporter::class,
            \CommunityTranslation\Translation\Importer::class,
        ] as $className) {
            $app->singleton($className);
        }
    }

    private function registerEntityRepositories(Application $app): void
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
            $app->singleton(
                "CommunityTranslation\\Repository\\{$path}",
                static function () use ($app, $path): EntityRepository {
                    return $app->make(EntityManager::class)->getRepository("CommunityTranslation\\Entity\\{$path}");
                }
            );
        }
    }

    private function registerInterfaces(Application $app): void
    {
        $app->singleton(
            \CommunityTranslation\Parser\ParserInterface::class,
            static function () use ($app): \CommunityTranslation\Parser\ParserInterface {
                $config = $app->make(Repository::class);

                return $app->make($config->get('community_translation::translate.parser'));
            }
        );
    }

    private function registerTranslationConverters(Application $app): void
    {
        $this->app->singleton(
            \CommunityTranslation\TranslationsConverter\Provider::class,
            static function () use ($app): \CommunityTranslation\TranslationsConverter\Provider {
                $provider = $app->build(\CommunityTranslation\TranslationsConverter\Provider::class);
                $provider->register($app->make(\CommunityTranslation\TranslationsConverter\JedConverter::class));
                $provider->register($app->make(\CommunityTranslation\TranslationsConverter\JsonDictionaryConverter::class));
                $provider->register($app->make(\CommunityTranslation\TranslationsConverter\MoConverter::class));
                $provider->register($app->make(\CommunityTranslation\TranslationsConverter\PhpArrayConverter::class));
                $provider->register($app->make(\CommunityTranslation\TranslationsConverter\PoConverter::class));

                return $provider;
            }
        );
    }

    private function registerEventSubscribers(Application $app): void
    {
        $em = $app->make(EntityManager::class);
        $em->getEventManager()->addEventSubscriber($app->make(EntitiesEventSubscriber::class));
    }
}
