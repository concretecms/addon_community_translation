<?php
namespace Concrete\Package\CommunityTranslation;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;
use Core;
use Page;
use SinglePage;

class Controller extends Package
{
    protected $pkgHandle = 'community_translation';

    protected $appVersionRequired = '5.7.5.7a1';

    protected $pkgVersion = '0.0.1';

    public function getPackageName()
    {
        return t("Community Translation");
    }

    public function getPackageDescription()
    {
        return t('Translate concrete5 core and packages');
    }

    private function registerServiceProvider(Application $app)
    {
        $provider = new Src\ServiceProvider($app);
        $provider->register();
    }

    public function install()
    {
        $pkg = parent::install();
        $config = $this->getFileConfig();
        $config->get('options.translatedThreshold', 90);

        $app = \Core::make('app');
        $this->registerServiceProvider($app);

        $em = $app->make('community_translation/em');
        /* @var \Doctrine\ORM\EntityManager $em */

        $gitRepo = $app->make('community_translation/git');
        /* @var \Doctrine\ORM\EntityRepository $gitRepo */
        if ($gitRepo->findOneBy(array('grURL' => 'https://github.com/concrete5/concrete5-legacy.git')) === null) {
            $git = new Src\Git\Repository();
            $git->setName('concrete5 Legacy');
            $git->setPackage('');
            $git->setURL('https://github.com/concrete5/concrete5-legacy.git');
            $git->setDevBranches(array(
                'master' => Src\Package\Package::DEV_PREFIX.'5.6',
            ));
            $git->setTagsFilter('< 5.7');
            $git->setWebRoot('web');
            $em->persist($git);
            $em->flush();
        }
        if ($gitRepo->findOneBy(array('grURL' => 'https://github.com/concrete5/concrete5.git')) === null) {
            $git = new Src\Git\Repository();
            $git->setName('concrete5');
            $git->setPackage('');
            $git->setURL('https://github.com/concrete5/concrete5.git');
            $git->setDevBranches(array(
                'develop' => Src\Package\Package::DEV_PREFIX.'5.7',
            ));
            $git->setTagsFilter('>= 5.7');
            $git->setWebRoot('web');
            $em->persist($git);
            $em->flush();
        }

        \Concrete\Core\Job\Job::installByPackage('parse_git_repositories', $pkg);

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
        $sp = Page::getByPath('/dashboard/system/community_translation');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/dashboard/system/community_translation', $pkg);
            $sp->update(array(
                'cName' => t('Community Translation'),
            ));
        }
        $sp = Page::getByPath('/dashboard/system/community_translation/git_repositories');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/dashboard/system/community_translation/git_repositories', $pkg);
            $sp->update(array(
                'cName' => t('Strings from Git Repositories'),
            ));
        }
        $sp = Page::getByPath('/dashboard/system/community_translation/git_repositories/details');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/dashboard/system/community_translation/git_repositories/details', $pkg);
            $sp->update(array(
                'cName' => t('Git Repository details'),
            ));
            $sp->setAttribute('exclude_nav', 1);
        }
        $sp = Page::getByPath('/dashboard/system/community_translation/options');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/dashboard/system/community_translation/options', $pkg);
            $sp->update(array(
                'cName' => t('Community Translation Options'),
            ));
        }
        $sp = Page::getByPath('/teams');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/teams', $pkg);
            $sp->update(array(
                'cName' => t('Translation Teams'),
            ));
        }
        $sp = Page::getByPath('/teams/create');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/teams/create', $pkg);
            $sp->update(array(
                'cName' => t('Create new Translation Team'),
            ));
            $sp->setAttribute('exclude_nav', 1);
        }
        $sp = Page::getByPath('/teams/details');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/teams/details', $pkg);
            $sp->update(array(
                'cName' => t('Translation Team Details'),
            ));
            $sp->setAttribute('exclude_nav', 1);
        }
        $sp = Page::getByPath('/translate');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/translate', $pkg);
            $sp->update(array(
                'cName' => t('Translate'),
            ));
        }
        $sp = Page::getByPath('/utilities/fill_translations');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/utilities/fill_translations', $pkg);
            $sp->update(array(
                'cName' => t('Fill translations'),
            ));
        }
    }

    public function on_start()
    {
        $app = Core::make('app');

        $this->registerServiceProvider($app);
        $director = $app->make('director')->addSubscriber($app->make('community_translation/events'));
        if ($app->isRunThroughCommandLineInterface()) {
            $console = $app->make('console');
            $console->add(new Src\Console\Command\InitializeCommand());
        } else {
            $al = \AssetList::getInstance();
            $al->registerMultiple(array(
                'jquery/scroll-to' => array(
                    array('javascript', 'js/jquery.scrollTo.min.js', array('minify' => true, 'combine' => true, 'version' => '2.1.2'), $this),
                ),
                'community_translation/common' => array(
                    array('javascript', 'js/common.js', array('minify' => true, 'combine' => true), $this),
                ),
            ));
            $al->registerGroupMultiple(array(
                'jquery/scroll-to' => array(
                    array(
                        array('javascript', 'jquery'),
                        array('javascript', 'jquery/scroll-to'),
                    ),
                ),
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
