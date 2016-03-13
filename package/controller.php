<?php
namespace Concrete\Package\CommunityTranslation;

use Concrete\Core\Application\Application;
use Concrete\Core\Package\Package;
use Concrete\Core\Page\Theme\Theme;
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
        $config->save('options.translatedThreshold', 90);
        $config->save('options.downloadAccess', 'members');

        $app = \Core::make('app');
        $this->registerServiceProvider($app);

        $em = $app->make('community_translation/em');

        $gitRepo = $app->make('community_translation/git');
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
        $theme = Theme::getByHandle('community_translation');
        if ($theme === null) {
            $theme = Theme::add('community_translation', $pkg);
        }
        // Add fulltext indexes manually, since with Doctrine ORM < 2.5 it's not possible with annotations
        try {
            $connection = \Core::make('community_translation/em')->getConnection();
            try {
                $connection->executeQuery('ALTER TABLE GlossaryEntries ADD FULLTEXT INDEX IXGlossaryEntriesTermFulltext (geTerm);');
            } catch (\Exception $foo) {
            }
            try {
                $connection->executeQuery('ALTER TABLE Translatables ADD FULLTEXT INDEX IXTranslatablesTextFulltext (tText);');
            } catch (\Exception $foo) {
            }
        } catch (\Exception $foo) {
        }

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
        $sp = Page::getByPath('/translate/details');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/translate/details', $pkg);
            $sp->update(array(
                'cName' => t('Package details'),
            ));
            $sp->setAttribute('exclude_nav', 1);
        }
        $sp = Page::getByPath('/translate/online');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/translate/online', $pkg);
            $sp->update(array(
                'cName' => t('Online Translation'),
            ));
            $sp->setAttribute('exclude_nav', 1);
        }
        $sp = Page::getByPath('/utilities/fill_translations');
        if (!is_object($sp) || $sp->getError() === COLLECTION_NOT_FOUND) {
            $sp = SinglePage::add('/utilities/fill_translations', $pkg);
            $sp->update(array(
                'cName' => t('Fill translations'),
            ));
        }
        $apiTokenAttributeKey = \Concrete\Core\Attribute\Key\UserKey::getByHandle('api_token');
        if (!is_object($apiTokenAttributeKey)) {
            $apiTokenAttributeType = \Concrete\Core\Attribute\Type::getByHandle('api_token');
            if ($apiTokenAttributeType === null) {
                $apiTokenAttributeType = \Concrete\Core\Attribute\Type::add('api_token', tc('AttributeTypeName', 'API Token'), $pkg);
            }
            $userAttributeCategory = \Concrete\Core\Attribute\Key\Category::getByHandle('collection');
            if (isset($userAttributeCategory)) {
                if (!$userAttributeCategory->allowAttributeSets()) {
                    $userAttributeCategory->setAllowAttributeSets(\Concrete\Core\Attribute\Key\Category::ASET_ALLOW_SINGLE);
                }
                $userAttributeSet = \AttributeSet::getByHandle('community_translation', $userAttributeCategory->getAttributeKeyCategoryID());
                if (!isset($userAttributeSet)) {
                    $userAttributeSet = $userAttributeCategory->addSet('community_translation', tc('AttributeSetName', 'Community Translation'), $pkg);
                }
                if (isset($userAttributeSet)) {
                    $apiTokenAttributeKey = \Concrete\Core\Attribute\Key\UserKey::add(
                        $apiTokenAttributeType,
                        array(
                            // Handle
                            'akHandle' => 'api_token',
                            // Name
                            'akName' => tc('AttributeKeyName', 'API Token'),
                            // Available in Dashboard User Search?
                            'akIsSearchable' => 1,
                            // Content included in user keyword search
                            'akIsSearchableIndexed' => 1,
                            // Automatically created by a process?
                            //'akIsAutoCreated' => ???
                            // Can be edited through the frontend?
                            'akIsEditable' => 1,
                            // Displayed in public profile?
                            'uakProfileDisplay' => 0,
                            // Displayed on member list?
                            'uakMemberListDisplay' => 0,
                            // Editable in profile?
                            'uakProfileEdit' => 1,
                            // Editable and required in profile?
                            'uakProfileEditRequired' => 0,
                            // Show on registration form?
                            'uakRegisterEdit' => 0,
                            // Require on registration form?
                            'uakRegisterEditRequired' => 0,
                            // Activated?
                            'uakIsActive' => 1,
                        ),
                        $pkg
                    );
                }
            }
        }
    }

    public function on_start()
    {
        $app = Core::make('app');

        \Route::setThemesByRoutes(array(
            '/translate/online' => array('community_translation', 'full_page.php'),
        ));
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
            $handleRegex = '([A-Za-z0-9]([A-Za-z0-9\_]*[A-Za-z0-9])?)?';
            $localeRegex = '[a-zA-Z]{2,3}([_\-][a-zA-Z0-9]{2,3})?';
            $app->make('Concrete\Core\Routing\Router')->registerMultiple(array(
                '/api/locales/' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getApprovedLocales',
                    null,
                    array(),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
                '/api/locales/{packageHandle}/{packageVersion}/{minimumLevel}/' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getLocalesForPackage',
                    null,
                    array('packageHandle' => $handleRegex, 'minimumLevel' => '[0-9]{1,3}'),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
                '/api/packages/' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getAvailablePackageHandles',
                    null,
                    array(),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
                '/api/package/{packageHandle}/versions/' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getAvailablePackageVersions',
                    null,
                    array('packageHandle' => $handleRegex),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
                '/api/package/process/' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::processPackage',
                    null,
                    array(),
                    array(),
                    '',
                    array(),
                    array('POST'),
                ),
                '/api/po/{packageHandle}/{packageVersion}/{localeID}' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getPackagePo',
                    null,
                    array('packageHandle' => $handleRegex, 'localeID' => $localeRegex),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
                '/api/mo/{packageHandle}/{packageVersion}/{localeID}' => array(
                    '\Concrete\Package\CommunityTranslation\Src\Rest\Api::getPackageMo',
                    null,
                    array('packageHandle' => $handleRegex, 'localeID' => $localeRegex),
                    array(),
                    '',
                    array(),
                    array('GET'),
                ),
            ));
        }
    }
}
