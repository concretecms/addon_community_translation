<?php
namespace Concrete\Package\CommunityTranslation\Src;

use Concrete\Core\Foundation\Service\Provider;

class ServiceProvider extends Provider
{
    public function register()
    {
        $app = $this->app;

        // Entity manager
        $this->app->singleton(
            'community_translation/em',
            function () use ($app) {
                $orm = $app->make('Concrete\Core\Database\DatabaseManagerORM');

                return $orm->entityManager(\Package::getByHandle('community_translation'));
            }
        );

        // Repositories
        foreach (array(
            'community_translation/git' => 'Concrete\Package\CommunityTranslation\Src\Git\Repository',
            'community_translation/package' => 'Concrete\Package\CommunityTranslation\Src\Package\Package',
            'community_translation/translatable' => 'Concrete\Package\CommunityTranslation\Src\Translatable\Translatable',
            'community_translation/translatable/place' => 'Concrete\Package\CommunityTranslation\Src\Translatable\Place\Place',
            'community_translation/translation' => 'Concrete\Package\CommunityTranslation\Src\Translation\Translation',
            'community_translation/locale' => 'Concrete\Package\CommunityTranslation\Src\Locale\Locale',
            'community_translation/stats' => 'Concrete\Package\CommunityTranslation\Src\Stats\Stats',
            'community_translation/glossary/entry' => 'Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry',
        ) as $abstract => $fqn) {
            $this->app->singleton(
                $abstract,
                function () use ($app, $fqn) {
                    return $app->make('community_translation/em')->getRepository($fqn);
                }
            );
        }

        // Services
        foreach (array(
            'community_translation/git/fetcher' => array('Concrete\Package\CommunityTranslation\Src\Git\Fetcher', false),
            'community_translation/git/importer' => array('Concrete\Package\CommunityTranslation\Src\Git\Importer', true),
            'community_translation/translatable/importer' => array('Concrete\Package\CommunityTranslation\Src\Translatable\Importer', true),
            'community_translation/translation/importer' => array('Concrete\Package\CommunityTranslation\Src\Translation\Importer', true),
            'community_translation/translation/exporter' => array('Concrete\Package\CommunityTranslation\Src\Translation\Exporter', true),
            'community_translation/groups' => array('Concrete\Package\CommunityTranslation\Src\Service\Groups', true),
            'community_translation/access' => array('Concrete\Package\CommunityTranslation\Src\Service\Access', true),
            'community_translation/events' => array('Concrete\Package\CommunityTranslation\Src\Service\Events', true),
            'community_translation/notify' => array('Concrete\Package\CommunityTranslation\Src\Service\Notify', true),
            'community_translation/tempdir' => array('Concrete\Package\CommunityTranslation\Src\Service\VolatileDirectory', false),
            'community_translation/parser' => array('Concrete\Package\CommunityTranslation\Src\Service\Parser\Parser', true),
        ) as $abstract => $info) {
            // $info[0]: concrete
            // $info[1]: shared (aka singleton)
            $this->app->bind($abstract, $info[0], $info[1]);
        }
    }
}
