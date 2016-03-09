<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Translate;

use Concrete\Core\Page\Controller\PageController;
use Concrete\Package\CommunityTranslation\Src\UserException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Concrete\Package\CommunityTranslation\Src\Service\Access;

class Online extends PageController
{
    public function view($packageID = '', $localeID = '')
    {
        $error = null;
        if ($error === null) {
            $package = $packageID ? $this->app->make('community_translation/package')->find($packageID) : null;
            if ($package === null) {
                $error = t('Invalid translated package identifier received');
            }
        }
        if ($error === null) {
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                $error = t('Invalid language identifier received');
            }
        }
        if ($error === null) {
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                $error = t("You don't belong to the %s translation group", $locale->getDisplayName());
            }
        }
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/translate');
        }
        $hh = $this->app->make('helper/html');
        $this->addHeaderItem($hh->css('translate/online.css', 'community_translation'));
        $this->addFooterItem($hh->javascript('bootstrap.min.js', 'community_translation'));
        $this->addFooterItem($hh->javascript('translate/online.js', 'community_translation'));
        $this->requireAsset('javascript', 'jquery');
        $this->requireAsset('core/translator');
        $this->set('package', $package);
        $this->set('token', $this->app->make('helper/validation/token'));
        $this->set('canApprove', $access >= Access::ADMIN);
        $this->set('locale', $locale);
        $this->set('canEditGlossary', $access >= Access::ADMIN);
        $pluralCases = array();
        foreach ($locale->getPluralForms() as $pluralForm) {
            list($pluralFormKey, $pluralFormExamples) = explode(':', $pluralForm);
            $pluralCases[$pluralFormKey] = $pluralFormExamples;
        }
        $this->set('pluralCases', $pluralCases);
        $this->set('translations', $this->app->make('community_translation/editor')->getInitialTranslations($package, $locale));
    }

    public function load_translation($localeID, $packageID = null)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-load-translation'.$localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $translatableID = $this->post('translatableID');
            $translatable = (is_string($translatableID) && $translatableID) ? $this->app->make('community_translation/translatable')->find($translatableID) : null;
            if ($translatable === null) {
                throw new UserException(t('Invalid translatable string identifier received'));
            }
            $package = null;
            $packageID = $this->post('packageID');
            if ($packageID) {
                $package = $this->app->make('community_translation/package')->find($packageID);
                if ($package === null) {
                    $error = t('Invalid translated package identifier received');
                }
            }

            return JsonResponse::create(
                $this->app->make('community_translation/editor')->getTranslatableData($locale, $translatable, $package, true)
            );
        } catch (UserException $x) {
            return JsonResponse::create(
                array(
                    'error' => $x->getMessage(),
                ),
                400
            );
        }
    }

    public function save_glossary_term($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-save-glossary-term'.$localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            } elseif ($access < Access::ADMIN) {
                throw new UserException(t("Access denied."));
            }
            $id = $this->post('id');
            if ($id === 'new') {
                $editing = \Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry::create();
            } else {
                $editing = $id ? $this->app->make('community_translation/glossary/entry')->find($id) : null;
                if ($editing === null) {
                    throw new UserException(t("Unable to find the specified gossary entry."));
                }
            }
            $editing->setTerm($this->post('term'));
            if ($editing->getTerm() === '') {
                throw new UserException(t("Please specify the term."));
            }
            $editing->setType($this->post('type'));
            $existing = $this->app->make('community_translation/glossary/entry')->findOneBy(array('geTerm' => $editing->getTerm(), 'geType' => $editing->getType()));
            if ($existing !== null && $existing->getID() !== $editing->getID()) {
                throw new UserException(t('The term "%1$s" already exists for the type "%2$s"', $editing->getTerm(), $editing->getType()));
            }
            $editing->setComments($this->post('termComments'));
            $em = $this->app->make('community_translation/em');
            $em->beginTransaction();
            try {
                $em->persist($editing);
                $em->flush();
                $translation = trim((string) $this->post('translation'));
                $localized = $editing->getID() ? $this->app->make('community_translation/glossary/entry/localized')->find(array('gleEntry' => $editing->getID(), 'gleLocale' => $locale->getID())) : null;
                if ($translation === '') {
                    if ($localized !== null) {
                        $em->remove($localized);
                        $localized = null;
                    }
                } else {
                    if ($localized === null) {
                        $localized = \Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Localized::create($editing, $locale);
                    }
                    $localized->setTranslation($translation);
                    $localized->setComments($this->post('translationComments'));
                    $em->persist($localized);
                }
                $em->flush();
                $em->commit();

                return JsonResponse::create(
                    array(
                        'id' => $editing->getID(),
                        'term' => $editing->getTerm(),
                        'type' => $editing->getType(),
                        'termComments' => $editing->getComments(),
                        'translation' => ($localized === null) ? '' : $localized->getTranslation(),
                        'translationComments' => ($localized === null) ? '' : $localized->getComments(),
                    )
                );
            } catch (\Exception $x) {
                try {
                    $em->rollback();
                } catch (\Exception $foo) {
                }
                throw $x;
            }
        } catch (UserException $x) {
            return JsonResponse::create(
                array(
                    'error' => $x->getMessage(),
                ),
                400
            );
        }
    }

    public function delete_glossary_term($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-delete-glossary-term'.$localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make('community_translation/locale')->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make('community_translation/access')->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            } elseif ($access < Access::ADMIN) {
                throw new UserException(t("Access denied."));
            }
            $id = $this->post('id');
            $term = $id ? $this->app->make('community_translation/glossary/entry')->find($id) : null;
            if ($term === null) {
                throw new UserException(t("Unable to find the specified gossary entry."));
            }
            /* @var \Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry $term */
            $otherLocaleNames = array();
            foreach ($term->getTranslations() as $translation) {
                if ($translation->getLocale() !== $locale) {
                    $otherLocaleNames[] = $translation->getLocale()->getDisplayName();
                }
            }
            if (!empty($otherLocaleNames)) {
                if (count($otherLocaleNames) < 5) {
                    throw new UserException(t("It's not possible to delete this entry since it's translated in these languages too:", "\n- %s".implode("\n- ", $otherLocaleNames)));
                } else {
                    throw new UserException(t("It's not possible to delete this entry since it's translated in %d other languages too.", count($otherLocaleNames)));
                }
            }
            $em = $this->app->make('community_translation/em');
            $em->remove($term);
            $em->flush();

            return JsonResponse::create(
                true
            );
        } catch (UserException $x) {
            return JsonResponse::create(
                array(
                    'error' => $x->getMessage(),
                ),
                400
            );
        }
    }
}
