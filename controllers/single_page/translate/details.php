<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Translate;

use Concrete\Core\Page\Controller\PageController;
use CommunityTranslation\Entity\Stats as StatsEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\UserException;
use CommunityTranslation\Service\Access;

class Details extends PageController
{
    public function view($prefixedPackageHandle = '', $packageVersion = '', $localeID = '')
    {
        $package = null;
        $locale = null;
        if (is_string($prefixedPackageHandle) && strpos($prefixedPackageHandle, 'pkg_') === 0) {
            $packageHandle = trim(substr($prefixedPackageHandle, strlen('pkg_')));
            if (is_string($packageVersion) && ($packageVersion = trim($packageVersion)) !== '') {
                $package = $this->app->make('community_translation/package')->findOneBy(['packageHandle' => $packageHandle, 'pVersion' => $packageVersion]);
                if ($package !== null) {
                    if (is_string($localeID) && ($localeID = trim($localeID)) !== '') {
                        $locale = $this->app->make(LocaleRepository::class)->findOneBy(['lID' => $localeID, 'lIsSource' => false, 'lIsApproved' => true]);
                    }
                }
            }
        }
        if ($package === null || $locale === null) {
            $this->redirect('/translate');
        }
        $allVersions = $this->app->make('community_translation/package')->findBy(['packageHandle' => $package->getHandle()]);
        usort($allVersions, function (PackageVersionEntity $a, PackageVersionEntity $b) {
            $devA = strpos($a->getVersion(), PackageVersionEntity::DEV_PREFIX) === 0;
            $devB = strpos($b->getVersion(), PackageVersionEntity::DEV_PREFIX) === 0;
            if ($devA === $devB) {
                return version_compare($b->getVersion(), $a->getVersion());
            } elseif ($devA) {
                return -1;
            } else {
                return 1;
            }
        });
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('cannotDownloadTranslationsBecause', $this->app->make(Access::class)->getDownloadAccess($locale));
        $this->set('translationsAccess', $this->app->make(Access::class)->getLocaleAccess($locale));
        $this->set('allVersions', $allVersions);
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate.css', 'community_translation'));
        $locales = $this->app->make(LocaleRepository::class)->getApprovedLocales();
        $this->set('translatedThreshold', $this->app->make('community_translation/config')->get('options.translatedThreshold', 90));
        $this->set('dh', $this->app->make('helper/date'));
        $this->set('locales', $locales);
        $this->set('package', $package);
        $this->set('locale', $locale);
        $stats = $this->app->make('community_translation/stats')->get($package, $locales);
        usort($stats, function (StatsEntity $a, StatsEntity $b) {
            $delta = $b->getTranslated() - $a->getTranslated();
            if ($delta == 0) {
                $delta = strcasecmp($a->getLocale()->getDisplayName(), $b->getLocale()->getDisplayName());
            }

            return $delta;
        });
        $this->set('stats', $stats);
    }

    public function download($packageID, $localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-download-' . $packageID . '@' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $package = $this->app->make('community_translation/package')->find($packageID);
            if ($package === null) {
                throw new UserException(t('The requested package has not been found'));
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t('The requested locale has not been found'));
            }
            $whyNot = $this->app->make(Access::class)->getDownloadAccess($locale);
            if ($whyNot !== '') {
                throw new UserException($whyNot);
            }
            $translations = $this->app->make('community_translation/translation/exporter')->forPackage($package, $locale);
            $format = $this->post('format');
            switch ($format) {
                case 'po':
                    $filename = $locale->getID() . '.po';
                    $contents = $translations->toPoString();
                    break;
                case 'mo':
                    $filename = $locale->getID() . '.mo';
                    \Gettext\Generators\Mo::$includeEmptyTranslations = true;
                    $contents = $translations->toMoString();
                    break;
                default:
                    throw new UserException('Invalid parameter specified: format');
            }
            \Symfony\Component\HttpFoundation\Response::create(
                $contents,
                200,
                [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Disposition' => 'attachment; filename=' . $filename,
                    'Content-Transfer-Encoding' => 'binary',
                    'Content-Length' => strlen($contents),
                    'Expires' => '0',
                ]
            )->send();
            exit;
        } catch (UserException $x) {
            $message = $x->getMessage();
        } catch (\Exception $x) {
            $message = t('An unspecified error occurred');
        }
        $jsonMessage = json_encode($message);
        \Symfony\Component\HttpFoundation\Response::create(
            <<<EOT
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
    <script>
        window.parent.alert($jsonMessage);
   </script>
</head>
</html>
EOT
            ,
            200,
            [
                'Content-Type' => 'text/html; charset=' . APP_CHARSET,
                'Cache-Control' => 'no-cache',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]
        )->send();
        exit();
    }

    public function upload($packageID, $localeID)
    {
        try {
            $package = $this->app->make('community_translation/package')->find($packageID);
            if ($package === null) {
                throw new UserException(t('The requested package has not been found'));
            }
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                throw new UserException(t('The requested locale has not been found'));
            }
        } catch (UserException $x) {
            $this->flash('error', $x->getMessage());
            $this->redirect('/translate');
        }
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-upload-' . $packageID . '@' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $whyNot = $this->app->make(Access::class)->getDownloadAccess($locale);
            if ($whyNot !== '') {
                throw new UserException($whyNot);
            }
            $file = $this->request->files->get('translations-file');
            if ($file === null) {
                throw new UserException(t('Please specify the translations file to be uploaded'));
            }
            if (!$file->isValid()) {
                throw new UserException($file->getErrorMessage());
            }
            try {
                $translations = \Gettext\Translations::fromPoFile($file->getPathname());
            } catch (\Exception $x) {
                throw new UserException($x->getMessage());
            }
            if ($translations === null || count($translations) === 0) {
                throw new UserException(t("The specified file does not contain any translations.\nPlease be sure it is a .po file"));
            }
            /*
            // @todo START
            
                if (@strcasecmp(strtolower(str_replace('-', '_', $translations->getLanguage())), strtolower($locale->getID())) !== 0) {
                    $name = \Punic\Language::getName($translations->getLanguage());
                    if ($name) {
                        throw new UserException(t('The specified file contains translations for %1$s and not for %2$s', $name, $locale->getDisplayName()));
                    } else {
                        throw new UserException(t('It was not possible to determine the language of the uploaded file.'));
                    }
                }
            
                $pluralForms = $translations->getPluralForms();
                $pluralCount = isset($pluralForms) ? $pluralForms[0] : null;
                if ($pluralCount === null || $pluralCount !== $locale->getPluralCount()) {
                    foreach ($translations as $translation) {
                        if ($translation->hasPlural() && $translation->hasTranslation()) {
                            if ($pluralCount === null) {
                                throw new UserException(t('For the language %1$s there should be %2$d plural forms, but in your file this is not specified', $locale->getDisplayName(), $locale->getPluralCount()));
                            } else {
                                throw new UserException(t('For the language %1$s there should be %2$d plural forms, but in your file there are %3$d', $locale->getDisplayName(), $locale->getPluralCount(), $pluralCount));
                            }
                        }
                    }
                }
            
            // @todo END
            */
            $result = $this->app->make('community_translation/translation/importer')->import($translations, $locale);
            $message = '';
            foreach ([
                'emptyTranslations' => t('Number of strings not translated (skipped)'),
                'unknownStrings' => t('Number of translations for unknown translatable strings (skipped)'),
                'addedAsCurrent' => t('Number of new translations added and marked as the current ones'),
                'addedNeedReview' => t('Number of new translations added but waiting for review (not marked as current)'),
                'existingActiveUntouched' => t('Number of already active translations untouched'),
                'existingActiveReviewed' => t('Number of current translations marked as reviewed'),
                'existingActivated' => t('Number of previous translations that have been activated (made current)'),
                'existingInactiveUntouched' => t('Number of translations untouched'),
            ] as $propName => $description) {
                $num = $result->$propName;
                if ($num) {
                    $message .= $description . ': ' . $num . "<br />";
                }
            }
            if ($result->addedNeedReview > 0) {
                $this->app->make(NotificationRepository::class)->translationsNeedApproval($locale, $package, 'current');
            }
            $this->flash('message', $message);
            $this->redirect('/translate/details/', '', 'pkg_' . $package->getHandle(), $package->getVersion(), $locale->getID());
        } catch (UserException $x) {
            $this->set('error', $x->getMessage());
            $this->view('pkg_' . $package->getHandle(), $package->getVersion(), $locale->getID());
        }
    }
}
