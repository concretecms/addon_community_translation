<?php
namespace Concrete\Package\CommunityTranslation;

use Concrete\Core\Package\Package;
use Core;
use Page;
use SinglePage;

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

    public function install()
    {
        $pkg = parent::install();
        self::installReal($pkg, '');
    }

    public function upgrade()
    {
        $fromVersion = $this->getPackageVersion();
        parent::upgrade();
        self::installReal($this, $fromVersion);
    }

    private static function installReal(Controller $pkg, $fromVersion)
    {
        $sp = Page::getByPath('/teams');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/teams', $pkg);
            $sp->update(array(
                'cName' => t('Translation teams'),
            ));
        }
    }

    public function on_start()
    {
        $app = Core::make('app');

        $provider = new Src\ServiceProvider($app);
        $provider->register();
        $director = $app->make('director')->addSubscriber($app->make('community_translation/events'));
        if ($app->isRunThroughCommandLineInterface()) {
            $console = $app->make('console');
            $console->add(new Src\Console\Command\InitializeCommand());
        } else {
            $al = \AssetList::getInstance();
            $al->registerMultiple(array(
                'community_translation/common' => array(
                    array('javascript', 'js/common.js', array('minify' => false, 'combine' => false), $this),
                ),
            ));
            $al->registerGroupMultiple(array(
                'community_translation/common' => array(
                    array(
                        array('javascript', 'jquery'),
                        array('javascript', 'community_translation/common'),
                    ),
                ),
            ));
        }
    }
}
