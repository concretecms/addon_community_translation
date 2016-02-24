<?php
namespace Concrete\Package\CommunityTranslation\Src;

use Concrete\Core\Foundation\Service\Provider;

class ServiceProvider extends Provider
{
    public function register()
    {
        $app = $this->app;

        $this->app->singleton(
            'community_translation/em',
            function () use ($app) {
                $orm = $app->make('Concrete\Core\Database\DatabaseManagerORM');

                return $orm->entityManager(\Package::getByHandle('community_translation'));
            }
        );

        foreach (array(
            'community_translation/git' => '\Concrete\Package\CommunityTranslation\Src\Git\Repository',
            'community_translation/translatable' => '\Concrete\Package\CommunityTranslation\Src\Translatable',
            'community_translation/translatable/place' => '\Concrete\Package\CommunityTranslation\Src\Translatable\Place\Place',
            'community_translation/translation' => '\Concrete\Package\CommunityTranslation\Src\Translation\Translation',
            'community_translation/locale' => '\Concrete\Package\CommunityTranslation\Src\Locale\Locale',
        ) as $abstract => $fqn) {
            $this->app->singleton(
                $abstract,
                function () use ($app, $fqn) {
                    return $app->make('community_translation/em')->getRepository($fqn);
                }
            );
        }

        foreach (array(
            'community_translation/git/fetcher' => array('Concrete\Package\CommunityTranslation\Src\Git\Fetcher', false),
            'community_translation/git/importer' => array('Concrete\Package\CommunityTranslation\Src\Git\Importer', false),
            'community_translation/translatable/importer' => array('Concrete\Package\CommunityTranslation\Src\Translatable\Importer', true),
            'community_translation/translation/importer' => array('Concrete\Package\CommunityTranslation\Src\Translation\Importer', true),
        ) as $abstract => $info) {
            // $info[0]: concrete
            // $info[1]: shared (aka singleton)
            $this->app->bind($abstract, $info[0], $info[1]);
        }
    }
}
