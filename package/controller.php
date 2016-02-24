<?php
namespace Concrete\Package\CommunityTranslation;

use Concrete\Core\Package\Package;

class Controller extends Package
{
    protected $pkgHandle = 'community_translation';

    protected $appVersionRequired = '5.7.5.6';

    protected $pkgVersion = '0.0.1';

    public function getPackageName()
    {
        return t("Community Translation");
    }

    public function getPackageDescription()
    {
        return t('Translate concrete5 core and packages');
    }

    public function on_start()
    {
        $app = \Core::make('app');

        $provider = new Src\ServiceProvider($app);
        $provider->register();
        if ($app->isRunThroughCommandLineInterface()) {
            $console = $app->make('console');
            $console->add(new Src\Console\Command\InitializeCommand());
        }
    }
}
