<?php
namespace Concrete\Package\CommunityTranslation\Controller\Frontend;

use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Service\Access;
use CommunityTranslation\Service\Editor;
use Concrete\Core\Http\ResponseAssetGroup;
use Concrete\Core\Http\ResponseFactoryInterface;
use Controller;
use View;

class OnlineTranslation extends Controller
{
    const PACKAGEVERSION_UNREVIEWED = 'unreviewed';

    public function on_start()
    {
        $config = $this->app->make('community_translation/config');
        $this->controllerActionPath = $config->get('options.onlineTranslationPath').'/action';
    }

    public function getViewObject()
    {
        $v = new View('frontend/online_translation');
        $v->setPackageHandle('community_translation');
        $v->setViewTheme(null);

        return $v;
    }

    public function view($packageVersionID = '', $localeID = '')
    {
        $accessHelper = $this->app->make(Access::class);
        if ($accessHelper->isLoggedIn() === false) {
            return $this->app->make(ResponseFactoryInterface::class)->forbidden(
                $this->request->getUri()
            );
        }
        $error = null;
        if ($error === null) {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
            if ($locale === null) {
                $error = t('Invalid language identifier received');
            }
        }
        if ($error === null) {
            $access = $accessHelper->getLocaleAccess($locale);
            if ($access < Access::TRANSLATE) {
                $error = t("You don't belong to the %s translation group", $locale->getDisplayName());
            }
        }
        if ($error === null) {
            $packageVersion = null;
            if ($packageVersionID === self::PACKAGEVERSION_UNREVIEWED) {
                if ($access >= Access::ADMIN) {
                    $packageVersion = self::PACKAGEVERSION_UNREVIEWED;
                }
            } else {
                $packageVersion = $this->app->make(PackageVersionRepository::class)->find($packageVersionID);
            }
            if ($packageVersion === null) {
                $error = t('Invalid translated package version identifier received');
            }
        }
        if ($error !== null) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('An unexpected error occurred.'),
                h($error)
            );
        }
        // Hack to avoid account menu stuff
        $r = ResponseAssetGroup::get();
        $r->markAssetAsIncluded('core/account');
        // /hack
        $this->requireAsset('css', 'font-awesome');
        $this->requireAsset('javascript', 'jquery');
        $this->requireAsset('javascript', 'picturefill');
        $this->requireAsset('javascript-conditional', 'html5-shiv');
        $this->requireAsset('javascript-conditional', 'respond');
        $this->requireAsset('javascript', 'jquery');
        $this->requireAsset('core/translator');
        $this->requireAsset('community_translation/online_translation');
        if ($packageVersion === self::PACKAGEVERSION_UNREVIEWED) {
            $this->set('packageVersion', null);
        } else {
            $this->set('packageVersion', $packageVersion);
        }
        $config = $this->app->make('community_translation/config');
        $this->set('onlineTranslationPath', $config->get('options.onlineTranslationPath'));

        $this->set('token', $this->app->make('token'));
        $this->set('canApprove', $access >= Access::ADMIN);
        $this->set('locale', $locale);
        $this->set('canEditGlossary', $access >= Access::ADMIN);
        $pluralCases = [];
        foreach ($locale->getPluralForms() as $pluralForm) {
            list($pluralFormKey, $pluralFormExamples) = explode(':', $pluralForm);
            $pluralCases[$pluralFormKey] = $pluralFormExamples;
        }
        $this->set('pluralCases', $pluralCases);
        if ($packageVersion === static::PACKAGEVERSION_UNREVIEWED) {
            $this->set('translations', $this->app->make(Editor::class)->getUnreviewedInitialTranslations($locale));
            $this->set('pageTitle', t(/*i18n: %s is a language name*/'Strings awaiting review in %s', $locale->getDisplayName()));
        } else {
            $this->set('translations', $this->app->make(Editor::class)->getInitialTranslations($packageVersion, $locale));
            $this->set('pageTitle', t(/*i18n: %1$s is a package name, %2$s is a language name*/'Translating %1$s in %2$s', $packageVersion->getDisplayName(), $locale->getDisplayName()));
        }
    }

    /**
     * @todo
     */
    public function load_translation($localeID, $packageID = null)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-load-translation' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
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
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @todo
     */
    public function save_comment($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-save-comment' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $id = $this->post('id');
            if ($id === 'new') {
                $parentID = $this->post('parent');
                if ($parentID === 'root') {
                    $parent = null;
                    $translatableID = $this->post('translatable');
                    $translatable = $translatableID ? $this->app->make('community_translation/translatable')->find($translatableID) : null;
                    if ($translatable === null) {
                        throw new UserException(t('Unable to find the specified translatable string.'));
                    }
                } else {
                    $parent = $parentID ? $this->app->make('community_translation/translatable/comment')->find($parentID) : null;
                    if ($parent === null) {
                        throw new UserException(t('Unable to find the specified parent comment.'));
                    }
                    $translatable = $parent->getTranslatable();
                }
                if ($parent === null) {
                    switch ($this->post('visibility')) {
                        case 'locale':
                            $commentLocale = $locale;
                            break;
                        case 'global':
                            $commentLocale = null;
                            break;
                        default:
                            throw new UserException(t('Please specify the comment visibility.'));
                    }
                } else {
                    $commentLocale = null;
                }
                $comment = \CommunityTranslation\Translatable\Comment\Comment::create($translatable, $commentLocale, $parent);
            } else {
                $comment = $id ? $this->app->make('community_translation/translatable/comment')->find($id) : null;
                if ($comment === null) {
                    throw new UserException(t('Unable to find the specified comment.'));
                }
                $me = new \User();
                $myID = $me->isRegistered() ? (int) $me->getUserID() : null;
                if ($myID === null || $myID !== $comment->getPostedBy()) {
                    throw new UserException(t('Access denied to this comment.'));
                }
                if ($comment->getParentComment() === null) {
                    switch ($this->post('visibility')) {
                        case 'locale':
                            $commentLocale = $locale;
                            break;
                        case 'global':
                            $commentLocale = null;
                            break;
                        default:
                            throw new UserException(t('Please specify the comment visibility.'));
                    }
                    $comment->setLocale($commentLocale);
                }
            }
            $comment->setText($this->post('text'));
            if ($comment->getText() === '') {
                throw new UserException(t('Please specify the comment text.'));
            }
            $em = $this->app->make('community_translation/em');
            $em->persist($comment);
            $em->flush();
    
            return JsonResponse::create(
                [
                    'id' => $comment->getID(),
                    'date' => $this->app->make('helper/date')->formatPrettyDateTime($comment->getPostedOn(), true, true),
                    'mine' => true,
                    'by' => $this->app->make(UserService::class)->format($comment->getPostedBy()),
                    'text' => $comment->getText(),
                    'isGlobal' => $comment->getLocale() === null,
                ]
                );
        } catch (UserException $x) {
            return JsonResponse::create(
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @todo
     */
    public function delete_comment($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-delete-comment' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $id = $this->post('id');
            $comment = $id ? $this->app->make('community_translation/translatable/comment')->find($id) : null;
            if ($comment === null) {
                throw new UserException(t('Unable to find the specified comment.'));
            }
            if (count($comment->getChildComments()) > 0) {
                throw new UserException(t("This comment has some replies, so it can't be deleted."));
            }
            $em = $this->app->make('community_translation/em');
            $em->remove($comment);
            $em->flush();
    
            return JsonResponse::create(
                true
                );
        } catch (UserException $x) {
            return JsonResponse::create(
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @todo
     */
    public function save_glossary_term($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-save-glossary-term' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            } elseif ($access < Access::ADMIN) {
                throw new UserException(t('Access denied.'));
            }
            $id = $this->post('id');
            if ($id === 'new') {
                $editing = \CommunityTranslation\Glossary\Entry\Entry::create();
            } else {
                $editing = $id ? $this->app->make('community_translation/glossary/entry')->find($id) : null;
                if ($editing === null) {
                    throw new UserException(t('Unable to find the specified gossary entry.'));
                }
            }
            $editing->setTerm($this->post('term'));
            if ($editing->getTerm() === '') {
                throw new UserException(t('Please specify the term.'));
            }
            $editing->setType($this->post('type'));
            $existing = $this->app->make('community_translation/glossary/entry')->findOneBy(['geTerm' => $editing->getTerm(), 'geType' => $editing->getType()]);
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
                $localized = $editing->getID() ? $this->app->make('community_translation/glossary/entry/localized')->find(['gleEntry' => $editing->getID(), 'gleLocale' => $locale->getID()]) : null;
                if ($translation === '') {
                    if ($localized !== null) {
                        $em->remove($localized);
                        $localized = null;
                    }
                } else {
                    if ($localized === null) {
                        $localized = \CommunityTranslation\Glossary\Entry\Localized::create($editing, $locale);
                    }
                    $localized->setTranslation($translation);
                    $localized->setComments($this->post('translationComments'));
                    $em->persist($localized);
                }
                $em->flush();
                $em->commit();
    
                return JsonResponse::create(
                    [
                        'id' => $editing->getID(),
                        'term' => $editing->getTerm(),
                        'type' => $editing->getType(),
                        'termComments' => $editing->getComments(),
                        'translation' => ($localized === null) ? '' : $localized->getTranslation(),
                        'translationComments' => ($localized === null) ? '' : $localized->getComments(),
                    ]
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
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }

    /**
     * @todo
     */
    public function delete_glossary_term($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-delete-glossary-term' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            } elseif ($access < Access::ADMIN) {
                throw new UserException(t('Access denied.'));
            }
            $id = $this->post('id');
            $term = $id ? $this->app->make('community_translation/glossary/entry')->find($id) : null;
            if ($term === null) {
                throw new UserException(t('Unable to find the specified gossary entry.'));
            }
            $otherLocaleNames = [];
            foreach ($term->getTranslations() as $translation) {
                if ($translation->getLocale() !== $locale) {
                    $otherLocaleNames[] = $translation->getLocale()->getDisplayName();
                }
            }
            if (!empty($otherLocaleNames)) {
                if (count($otherLocaleNames) < 5) {
                    throw new UserException(t("It's not possible to delete this entry since it's translated in these languages too:", "\n- %s" . implode("\n- ", $otherLocaleNames)));
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
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @todo
     */
    public function load_all_places($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-load-all-places' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $id = $this->post('id');
            $translatable = $id ? $this->app->make('community_translation/translatable')->find($id) : null;
            if (translatable === null) {
                throw new UserException(t('Unable to find the specified translatable string.'));
            }
            $editorService = $this->app->make('community_translation/editor');
            $result = [];
            foreach ($translatable->getPlaces() as $place) {
                $result[] = [
                    'packageObject' => $place->getPackage(),
                    'package' => $place->getPackage()->getDisplayName(),
                    'comments' => $place->getComments(),
                    'references' => $editorService->expandReferences($place->getLocations(), $place->getPackage()),
                ];
            }
            usort($result, function (array $a, array $b) {
                $packageA = $a['packageObject'];
                $packageB = $b['packageObject'];
                $cmp = strcasecmp($packageA->getDisplayName(true), $packageB->getDisplayName(true));
                if ($cmp === 0) {
                    $isDevA = strpos($packageA->getVersion(), Package::DEV_PREFIX) === 0;
                    $isDevB = strpos($packageB->getVersion(), Package::DEV_PREFIX) === 0;
                    if ($isDevA === $isDevB) {
                        $cmp = version_compare($packageB->getVersion(), $packageA->getVersion());
                    } else {
                        $cmp = $isDevA ? -1 : 1;
                    }
                }
    
                return $cmp;
            });
                foreach (array_keys($result) as $i) {
                    unset($result[$i]['packageObject']);
                }
    
                return JsonResponse::create(
                    $result
                    );
        } catch (UserException $x) {
            return JsonResponse::create(
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @todo
     */
    public function process_translation($localeID)
    {
        try {
            $valt = $this->app->make('helper/validation/token');
            if (!$valt->validate('comtra-process-translation' . $localeID)) {
                throw new UserException($valt->getErrorMessage());
            }
            $locale = $localeID ? $this->app->make(LocaleRepository::class)->findApproved($localeID) : null;
            if ($locale === null) {
                throw new UserException(t('Invalid language identifier received'));
            }
            $access = $this->app->make(Access::class)->getLocaleAccess($locale);
            if ($access <= Access::NOT_LOGGED_IN) {
                $error = t('You need to log-in in order to translate');
            } elseif ($access < Access::TRANSLATE) {
                throw new UserException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
            }
            $translatableID = $this->post('id');
            $translatable = $translatableID ? $this->app->make('community_translation/translatable')->find($translatableID) : null;
            if ($translatable === null) {
                throw new UserException(t('Unable to find the specified translatable string.'));
            }
            $package = null;
            $packageID = $this->post('packageID');
            if ($packageID) {
                $package = $this->app->make('community_translation/package')->find($packageID);
                if ($package === null) {
                    $error = t('Invalid translated package identifier received');
                }
            }
            $operation = $this->post('operation');
            if (!is_string($operation) || $operation === '') {
                throw new UserException(t('Missing operation identifier'));
            }
            $processTranslationID = $this->post('translationID');
            if ($processTranslationID === null) {
                $processTranslation = null;
            } else {
                $processTranslation = $processTranslationID ? $this->app->make('community_translation/translation')->find($processTranslationID) : null;
                if ($processTranslation === null) {
                    throw new UserException(t('Unable to find the specified translation.'));
                }
                if ($processTranslation->getTranslatable() !== $translatable) {
                    throw new UserException(t('The specified translation is not for the correct string.'));
                }
                if ($processTranslation->getLocale() !== $locale) {
                    throw new UserException(t('The specified translation is not for the correct language.'));
                }
            }
            switch ($operation) {
                case 'approve':
                    return $this->approveTranslation($access, $processTranslation);
                case 'deny':
                    return $this->denyTranslation($access, $processTranslation);
                case 'reuse':
                    return $this->reuseTranslation($access, $processTranslation, $package);
                case 'save-current':
                    if ($this->post('clear') !== '1') {
                        return $this->setTranslationFromEditor($access, $locale, $translatable, $package);
                    } else {
                        return $this->unsetTranslationFromEditor($access, $locale, $translatable);
                    }
                default:
                    throw new UserException(t('Invalid operation identifier received: %s', $operation));
            }
        } catch (UserException $x) {
            return JsonResponse::create(
                [
                    'error' => $x->getMessage(),
                ],
                400
                );
        }
    }
    
    /**
     * @param int $access
     * @param Translation $translation
     *
     * @throws UserException
     *
     * @return JsonResponse
     * 
     * @todo
     */
    protected function approveTranslation($access, Translation $translation)
    {
        if ($access < Access::ADMIN) {
            throw new UserException(t('Access denied'));
        }
        if ($translation->isCurrent()) {
            throw new UserException(t('The selected translation is already the current one'));
        }
        $em = $this->app->make('community_translation/em');
        $currentTranslation = $this->app->make('community_translation/translation')->findOneBy([
            'tLocale' => $translation->getLocale(),
            'tTranslatable' => $translation->getTranslatable(),
            'tCurrent' => true,
        ]);
        if ($currentTranslation !== null) {
            $currentTranslation->setIsCurrent(false);
            $em->persist($currentTranslation);
            $em->flush();
        }
        $translation->setNeedReview(false);
        $translation->setIsReviewed(true);
        $translation->setIsCurrent(true);
        $em->persist($translation);
        $em->flush();
        $this->app->make('community_translation/stats')->resetForTranslation($translation);
        $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
    
        return JsonResponse::create($result);
    }
    
    /**
     * @param int $access
     * @param Translation $translation
     *
     * @throws UserException
     *
     * @return JsonResponse
     *
     * @todo
     */
    protected function denyTranslation($access, Translation $translation)
    {
        if ($access < Access::ADMIN) {
            throw new UserException(t('Access denied'));
        }
        if ($translation->isCurrent()) {
            throw new UserException(t('The selected translation is already the current one'));
        }
        $em = $this->app->make('community_translation/em');
        $translation->setNeedReview(false);
        $translation->setIsReviewed(false);
        $em->persist($translation);
        $em->flush();
    
        $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
        unset($result['current']);
    
        return JsonResponse::create($result);
    }
    
    /**
     * @param int $access
     * @param Translation $translation
     * @param Package $package
     *
     * @throws UserException
     *
     * @return JsonResponse
     *
     * @todo
     */
    protected function reuseTranslation($access, Translation $translation, Package $package = null)
    {
        if ($translation->isCurrent()) {
            throw new UserException(t('The selected translation is already the current one'));
        }
        $currentTranslation = $this->app->make('community_translation/translation')->findOneBy([
            'tLocale' => $translation->getLocale(),
            'tTranslatable' => $translation->getTranslatable(),
            'tCurrent' => true,
        ]);
        $em = $this->app->make('community_translation/em');
        $sendCurrent = true;
        $message = null;
        $notify = false;
        if ($currentTranslation !== null && $currentTranslation->isReviewed() && $access < Access::ADMIN) {
            $sendCurrent = false;
            $translation->setNeedReview(true);
            $translation->setIsReviewed(false);
            $em->persist($translation);
            $message = t('Since the current translation is approved, you have to wait that this new translation will be approved');
            $notify = true;
        } else {
            if ($currentTranslation !== null) {
                $currentTranslation->setIsCurrent(false);
                $em->persist($currentTranslation);
                $em->flush();
            }
            $translation->setNeedReview(false);
            $translation->setIsReviewed($access >= Access::ADMIN);
            $translation->setIsCurrent(true);
            $em->persist($translation);
        }
        $em->flush();
        $this->app->make('community_translation/stats')->resetForTranslation($translation);
        $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
        if ($sendCurrent === false) {
            unset($result['current']);
        }
        if ($message !== null) {
            $result['message'] = $message;
        }
    
        if ($notify === true) {
            $this->app->make(NotificationRepository::class)->translationsNeedApproval($translation->getLocale(), 1, $package, 'current');
        }
    
        return JsonResponse::create($result);
    }
    
    /**
     * @param int $access
     * @param Locale $locale
     * @param Translatable $translatable
     * @param Package $package
     *
     * @throws UserException
     *
     * @return JsonResponse
     *
     * @todo
     */
    protected function setTranslationFromEditor($access, Locale $locale, Translatable $translatable, Package $package = null)
    {
        $translation = null;
        $strings = $this->post('translated');
        $numStrings = ($translatable->getPlural() === '') ? 1 : $locale->getPluralCount();
        if (is_array($strings)) {
            $strings = array_values($strings);
            if (count($strings) === $numStrings) {
                $translation = Translation::create($locale, $translatable);
                foreach ($strings as $index => $string) {
                    if (!is_string($string) || trim($string) === '') {
                        $translation = null;
                        break;
                    }
                    $translation->{"setText$index"}($string);
                }
            }
        }
        if ($translation === null) {
            throw new UserException(t('Please specify the translations'));
        }
        if ($access >= Access::ADMIN) {
            if ($this->post('approved') === '1') {
                $approved = true;
            } elseif ($this->post('approved') === '0') {
                $approved = false;
            } else {
                throw new UserException(t('Missing parameter: %s', 'approved'));
            }
        } else {
            $approved = false;
        }
        foreach ($this->app->make('community_translation/translation')->findBy(['tLocale' => $locale, 'tTranslatable' => $translatable]) as $t) {
            $same = true;
            for ($index = 0; $index < $numStrings; ++$index) {
                if ($translation->{"getText$index"}() !== $t->{"getText$index"}()) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                $translation = $t;
                break;
            }
        }
        if ($translation->isCurrent()) {
            $currentTranslation = $translation;
        } else {
            $currentTranslation = $this->app->make('community_translation/translation')->findOneBy([
                'tLocale' => $translation->getLocale(),
                'tTranslatable' => $translation->getTranslatable(),
                'tCurrent' => true,
            ]);
        }
        $em = $this->app->make('community_translation/em');
        $message = null;
        $notify = false;
        if ($translation === $currentTranslation) {
            // No changes in the texts of the current translation.
            if ($access < Access::ADMIN || $translation->isReviewed() === $approved) {
                // Current translation is not changed at all
                $result = [];
            } else {
                // Let's change the 'reviewed' state of thecurrent translation
                $translation->setIsReviewed($approved);
                $em->persist($currentTranslation);
                $em->flush();
                $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
                unset($result['others']);
            }
        } elseif ($currentTranslation === null || !$currentTranslation->isReviewed() || $access >= Access::ADMIN) {
            // Let's make the new translation the current one
            if ($currentTranslation !== null) {
                $currentTranslation->setIsCurrent(false);
                $em->persist($currentTranslation);
                $em->flush();
            }
            $translation->setNeedReview(false);
            $translation->setIsReviewed($approved);
            $translation->setIsCurrent(true);
            $em->persist($translation);
            $em->flush();
            $this->app->make('community_translation/stats')->resetForTranslation($translation);
            $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
        } else {
            // Let's keep the current translation, but let's mark the new one as to be reviewed
            $translation->setNeedReview(true);
            $translation->setIsReviewed(false);
            $em->persist($translation);
            $em->flush();
            $this->app->make('community_translation/stats')->resetForTranslation($translation);
            $result = $this->app->make('community_translation/editor')->getTranslations($translation->getLocale(), $translation->getTranslatable());
            $result['message'] = t('Since the current translation is approved, you have to wait that this new translation will be approved');
            $notify = true;
        }
    
        if ($notify === true) {
            $this->app->make(NotificationRepository::class)->translationsNeedApproval($translation->getLocale(), 1, $package, 'current');
        }
    
        return JsonResponse::create($result);
    }
    
    /**
     * @param int $access
     * @param Locale $locale
     * @param Translatable $translatable
     *
     * @throws UserException
     *
     * @return JsonResponse
     *
     * @todo
     */
    protected function unsetTranslationFromEditor($access, Locale $locale, Translatable $translatable)
    {
        $currentTranslation = $this->app->make('community_translation/translation')->findOneBy([
            'tLocale' => $translation->getLocale(),
            'tTranslatable' => $translation->getTranslatable(),
            'tCurrent' => true,
        ]);
        if ($currentTranslation !== null) {
            $em = $this->app->make('community_translation/em');
            if ($currentTranslation->isReviewed() && $access < Access::ADMIN) {
                throw new UserException(t("The current translation is marked as reviewed, so you can't remove it."));
            }
            $currentTranslation->setIsCurrent(false);
            $em->persist($currentTranslation);
            $em->flush();
            $this->app->make('community_translation/stats')->resetForTranslation($translation);
            $result = $this->app->make('community_translation/editor')->getTranslations($locale, $translatable);
        } else {
            $result = [];
        }
    
        return JsonResponse::create($result);
    }
}
