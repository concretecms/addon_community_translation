<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\Frontend;

use CommunityTranslation\Entity\Glossary\Entry as GlossaryEntryEntity;
use CommunityTranslation\Entity\Glossary\Entry\Localized as GlossaryEntryLocalizedEntity;
use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package as PackageEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\PackageSubscription as PackageSubscriptionEntity;
use CommunityTranslation\Entity\PackageVersionSubscription as PackageVersionSubscriptionEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
use CommunityTranslation\Entity\Translation as TranslationEntity;
use CommunityTranslation\Glossary\EntryType;
use CommunityTranslation\Repository\Glossary\Entry as GlossaryEntryRepository;
use CommunityTranslation\Repository\Glossary\Entry\Localized as GlossaryEntryLocalizedRepository;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Repository\Package as PackageRepository;
use CommunityTranslation\Repository\Package\Version as PackageVersionRepository;
use CommunityTranslation\Repository\PackageSubscription as PackageSubscriptionRepository;
use CommunityTranslation\Repository\PackageVersionSubscription as PackageVersionSubscriptionRepository;
use CommunityTranslation\Repository\Stats as StatsRepository;
use CommunityTranslation\Repository\Translatable as TranslatableRepository;
use CommunityTranslation\Repository\Translatable\Comment as TranslatableCommentRepository;
use CommunityTranslation\Repository\Translation as TranslationRepository;
use CommunityTranslation\Service\Access as AccessService;
use CommunityTranslation\Service\Editor;
use CommunityTranslation\Service\User as UserService;
use CommunityTranslation\Translation\Exporter;
use CommunityTranslation\Translation\FileExporter as TranslationFileExporter;
use CommunityTranslation\Translation\Importer;
use CommunityTranslation\Translation\ImportOptions;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Block\Block;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Entity\User\User as UserEntity;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Events\EventDispatcher;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\View\View;
use Doctrine\ORM\EntityManager;
use Gettext\Translations as GettextTranslations;
use Punic\Comparer;
use Punic\Misc;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class OnlineTranslation extends Controller
{
    public const PACKAGEVERSION_UNREVIEWED = 'unreviewed';

    private ?AccessService $accessService = null;

    private ?UserService $userService = null;

    private ?EntityManager $entityManager = null;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Controller\AbstractController::on_start()
     */
    public function on_start()
    {
        $this->controllerActionPath = $this->getOnlineTranslationPath() . '/action';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Controller\Controller::getViewObject()
     */
    public function getViewObject()
    {
        $v = new View('frontend/online_translation');
        $v->setPackageHandle('community_translation');
        $v->setViewTheme(null);

        return $v;
    }

    public function view($packageVersionID, $localeID): ?Response
    {
        if ($this->getUserService()->isLoggedIn() === false) {
            return $this->app->make(ResponseFactoryInterface::class)->forbidden(
                $this->request->getUri()
            );
        }
        try {
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            $packageVersion = null;
            if ($packageVersionID === self::PACKAGEVERSION_UNREVIEWED) {
                if ($access >= AccessService::ADMIN) {
                    $packageVersion = self::PACKAGEVERSION_UNREVIEWED;
                }
            } else {
                $packageVersionID = is_numeric($packageVersionID) ? (int) $packageVersionID : 0;
                $packageVersion = $packageVersionID > 0 ? $this->app->make(PackageVersionRepository::class)->find($packageVersionID) : null;
            }
            if ($packageVersion === null) {
                throw new UserMessageException(t('Invalid translated package version identifier received'));
            }
        } catch (UserMessageException $x) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('An unexpected error occurred.'),
                h($x->getMessage())
            );
        }
        $urlManager = $this->app->make(ResolverManagerInterface::class);
        $this->requireAsset('community_translation/online-translation');
        if ($packageVersion === self::PACKAGEVERSION_UNREVIEWED) {
            $allVersions = null;
        } else {
            $allVersions = $this->getVersionsMenu($packageVersion, $locale);
        }
        $this->set('packageVersion', $packageVersion);
        $this->set('allVersions', $allVersions);
        $this->set('allLocales', $this->getLocalesMenu($packageVersionID, $locale));
        $this->set('onlineTranslationPath', $this->getOnlineTranslationPath());
        $this->set('token', $this->app->make('token'));
        $this->set('canApprove', $access >= AccessService::ADMIN);
        $this->set('locale', $locale);
        $this->set('canEditGlossary', $access >= AccessService::ADMIN);
        $pluralCases = [];
        foreach ($locale->getPluralForms() as $pluralForm) {
            [$pluralFormKey, $pluralFormExamples] = explode(':', $pluralForm);
            $pluralCases[$pluralFormKey] = $pluralFormExamples;
        }
        $this->set('pluralCases', $pluralCases);
        $viewUnreviewedUrl = '';
        $packageSubscription = null;
        $packageVersionSubscriptions = null;
        if ($packageVersion === static::PACKAGEVERSION_UNREVIEWED) {
            $this->set('packageVersionID', static::PACKAGEVERSION_UNREVIEWED);
            $this->set('translations', $this->app->make(Editor::class)->getUnreviewedInitialTranslations($locale));
            $this->set('pageTitle', t(/*i18n: %s is a language name*/'Strings awaiting review in %s', $locale->getDisplayName()));
            $this->set('pageTitleShort', tc(/*i18n: %s is a language name*/'Language', 'Reviewing %s', $locale->getDisplayName()));
        } else {
            $this->set('packageVersionID', $packageVersion->getID());
            $this->set('translations', $this->app->make(Editor::class)->getInitialTranslations($packageVersion, $locale));
            $this->set('pageTitle', t(/*i18n: %1$s is a package name, %2$s is a language name*/'Translating %1$s in %2$s', $packageVersion->getDisplayName(), $locale->getDisplayName()));
            $this->set('pageTitleShort', sprintf('%s %s @ %s', $packageVersion->getPackage()->getDisplayName(), $packageVersion->getVersion(), $locale->getID()));
            if ($access >= AccessService::ADMIN) {
                if ($this->app->make(Exporter::class)->localeHasPendingApprovals($locale)) {
                    $viewUnreviewedUrl = (string) $urlManager->resolve([$this->getOnlineTranslationPath(), self::PACKAGEVERSION_UNREVIEWED, $locale->getID()]);
                }
            }
            $packageSubscription = $this->getPackageSubscription($packageVersion->getPackage());
            $packageVersionSubscriptions = $this->getPackageVersionSubscriptions($packageVersion->getPackage());
        }
        $this->set('viewUnreviewedUrl', $viewUnreviewedUrl);
        $this->set('packageSubscription', $packageSubscription);
        $this->set('packageVersionSubscriptions', $packageVersionSubscriptions);
        $translationFormats = [];
        foreach ($this->app->make(TranslationsConverterProvider::class)->getRegisteredConverters() as $tf) {
            if ($tf->supportLanguageHeader() && $tf->supportPlurals() && $tf->canSerializeTranslations() && $tf->canUnserializeTranslations()) {
                $translationFormats[] = $tf;
            }
        }
        $this->set('translationFormats', $translationFormats);
        $session = $this->app->make('session');
        $showDialogAtStartup = null;
        if ($session->has('comtraShowDialogAtStartup')) {
            $showDialogAtStartup = $session->get('comtraShowDialogAtStartup');
            $session->remove('comtraShowDialogAtStartup');
        }
        $this->set('showDialogAtStartup', $showDialogAtStartup);
        $urlChunks = ['/'];
        $block = Block::getByName('CommunityTranslation Search Packages');
        if ($block && $block->getBlockID()) {
            $page = $block->getOriginalCollection();
            if ($page !== null) {
                $urlChunks = [$page];
                if ($packageVersion instanceof PackageVersionEntity) {
                    $urlChunks[] = 'package/' . $packageVersion->getPackage()->getHandle() . '/' . $packageVersion->getVersion();
                }
            }
        }
        $this->set('exitURL', $urlManager->resolve($urlChunks));
        $this->set('textDirection', Misc::getCharacterOrder($locale->getID()) === 'right-to-left' ? 'rtl' : 'ltr');
        $this->set('allTranslators', $this->getAllTranslators($locale));

        return null;
    }

    public function load_translation($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-load-translation' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $locale = $this->getUserRequestedLocale($localeID);
            $translatableID = $this->request->request->get('translatableID');
            $translatable = $translatableID && is_numeric($translatableID) ? $this->app->make(TranslatableRepository::class)->find((int) $translatableID) : null;
            if ($translatable === null) {
                throw new UserMessageException(t('Invalid translatable string identifier received'));
            }
            $packageVersion = null;
            $packageVersionID = $this->request->request->get('packageVersionID');
            if ($packageVersionID === static::PACKAGEVERSION_UNREVIEWED) {
                $packageVersion = null;
            } else {
                $packageVersion = $packageVersionID && is_numeric($packageVersionID) ? $this->app->make(PackageVersionRepository::class)->find((int) $packageVersionID) : null;
                if ($packageVersion === null) {
                    throw new UserMessageException(t('Invalid translated package version identifier received'));
                }
            }

            return $rf->json(
                $this->app->make(Editor::class)->getTranslatableData($locale, $translatable, $packageVersion)
            );
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function save_comment($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-save-comment' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $locale = $this->getUserRequestedLocale($localeID);
            $packageVersionID = $this->request->request->get('packageVersionID');
            $packageVersion = $packageVersionID && is_numeric($packageVersionID) ? $this->app->make(PackageVersionRepository::class)->find($packageVersionID) : null;
            if ($packageVersion === null) {
                throw new UserMessageException(t('Invalid translated package version identifier received'));
            }
            $postedBy = $this->getUserService()->getUserEntity(UserService::CURRENT_USER_KEY);
            $text = $this->request->request->get('text');
            $text = is_string($text) ? trim($text) : '';
            if ($text === '') {
                throw new UserMessageException(t('Please specify the comment text.'));
            }
            $id = $this->request->request->get('id');
            if ($id === 'new') {
                $isNew = true;
                $parentID = $this->request->request->get('parent');
                if ($parentID === 'root') {
                    $parent = null;
                    $translatableID = $this->request->request->get('translatable');
                    $translatable = $translatableID ? $this->app->make(TranslatableRepository::class)->find($translatableID) : null;
                    if ($translatable === null) {
                        throw new UserMessageException(t('Unable to find the specified translatable string.'));
                    }
                    switch ($this->request->request->get('visibility')) {
                        case 'locale':
                            $commentLocale = $locale;
                            break;
                        case 'global':
                            $commentLocale = null;
                            break;
                        default:
                            throw new UserMessageException(t('Please specify the comment visibility.'));
                    }
                } else {
                    $parent = $parentID ? $this->app->make(TranslatableCommentRepository::class)->find($parentID) : null;
                    if ($parent === null) {
                        throw new UserMessageException(t('Unable to find the specified parent comment.'));
                    }
                    $translatable = $parent->getTranslatable();
                    $commentLocale = null;
                }
                $comment = new TranslatableCommentEntity($translatable, $postedBy, $text, $commentLocale, $parent);
            } else {
                $isNew = false;
                $comment = $id && is_numeric($id) ? $this->app->make(TranslatableCommentRepository::class)->find((int) $id) : null;
                if ($comment === null) {
                    throw new UserMessageException(t('Unable to find the specified comment.'));
                }
                if ($comment->getPostedBy() !== $postedBy) {
                    throw new UserMessageException(t('Access denied to this comment.'));
                }
                if ($comment->getParentComment() === null) {
                    switch ($this->request->request->get('visibility')) {
                        case 'locale':
                            $commentLocale = $locale;
                            break;
                        case 'global':
                            $commentLocale = null;
                            break;
                        default:
                            throw new UserMessageException(t('Please specify the comment visibility.'));
                    }
                    $comment->setLocale($commentLocale);
                }
                $comment->setText($text);
            }
            $em = $this->getEntityManager();
            $em->persist($comment);
            $em->flush();
            $this->app->make(NotificationRepository::class)->translatableCommentSubmitted($comment, $packageVersion, $locale);

            $response = [
                'id' => $comment->getID(),
                'date' => $this->app->make('helper/date')->formatPrettyDateTime($comment->getPostedOn(), true, true),
                'mine' => true,
                'by' => $this->getUserService()->format($comment->getPostedBy(), true),
                'text' => $comment->getText(),
            ];
            if ($isNew) {
                $response['comments'] = [];
            }
            if ($comment->getParentComment() === null) {
                $response += [
                    'isGlobal' => $comment->getLocale() === null,
                ];
            }

            return $rf->json($response);
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function delete_comment($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-delete-comment' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $this->getUserRequestedLocale($localeID);
            $id = $this->request->request->get('id');
            $comment = $id && is_numeric($id) ? $this->app->make(TranslatableCommentRepository::class)->find($id) : null;
            if ($comment === null) {
                throw new UserMessageException(t('Unable to find the specified comment.'));
            }
            if (count($comment->getChildComments()) > 0) {
                throw new UserMessageException(t("This comment has some replies, so it can't be deleted."));
            }
            $em = $this->getEntityManager();
            $em->remove($comment);
            $em->flush();

            return $rf->json(
                true
            );
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function save_glossary_entry($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-save-glossary-entry' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            if ($access < AccessService::ADMIN) {
                throw new UserMessageException(t('Access denied.'));
            }
            $term = $this->request->request->get('term');
            $term = is_string($term) ? trim($term) : '';
            if ($term === '') {
                throw new UserMessageException(t('Please specify the term.'));
            }
            $id = $this->request->request->get('id');
            if ($id === 'new') {
                $editing = new GlossaryEntryEntity($term);
            } else {
                $editing = $id && is_numeric($id) ? $this->app->make(GlossaryEntryRepository::class)->find($id) : null;
                if ($editing === null) {
                    throw new UserMessageException(t('Unable to find the specified gossary entry.'));
                }
                $editing->setTerm($term);
            }
            $type = $this->request->request->get('type');
            $type = is_string($type) ? trim($type) : '';
            if ($type !== '' && !EntryType::isValidType($type)) {
                throw new UserMessageException(t('Please specify a valid entry type.'));
            }
            $editing->setType($type);
            $existing = $this->app->make(GlossaryEntryRepository::class)->findOneBy(['term' => $editing->getTerm(), 'type' => $editing->getType()]);
            if ($existing !== null && $existing->getID() !== $editing->getID()) {
                throw new UserMessageException(t('The term "%1$s" already exists for the type "%2$s"', $editing->getTerm(), $editing->getType()));
            }
            $termComments = $this->request->request->get('termComments');
            $editing->setComments(is_string($termComments) ? trim($termComments) : '');
            $em = $this->getEntityManager();
            $em->beginTransaction();
            $rollback = true;
            try {
                $em->persist($editing);
                $em->flush();
                $translation = $this->request->request->get('translation');
                $translation = is_string($translation) ? trim($translation) : '';
                $localized = $editing->getID() ? $this->app->make(GlossaryEntryLocalizedRepository::class)->find(['entry' => $editing->getID(), 'locale' => $locale->getID()]) : null;
                if ($translation === '') {
                    if ($localized !== null) {
                        $em->remove($localized);
                        $localized = null;
                    }
                } else {
                    if ($localized === null) {
                        $localized = new GlossaryEntryLocalizedEntity($editing, $locale, $translation);
                    } else {
                        $localized->setTranslation($translation);
                    }
                    $translationComments = $this->request->request->get('translationComments');
                    $localized->setComments(is_string($translationComments) ? trim($translationComments) : '');
                    $em->persist($localized);
                }
                $em->flush();
                $em->commit();
                $rollback = false;

                return $rf->json(
                    [
                        'id' => $editing->getID(),
                        'term' => $editing->getTerm(),
                        'type' => $editing->getType(),
                        'termComments' => $editing->getComments(),
                        'translation' => $localized === null ? '' : $localized->getTranslation(),
                        'translationComments' => $localized === null ? '' : $localized->getComments(),
                    ]
                );
            } finally {
                if ($rollback) {
                    try {
                        $em->rollback();
                    } catch (Throwable $foo) {
                    }
                }
            }
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function delete_glossary_entry($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-delete-glossary-entry' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            if ($access < AccessService::ADMIN) {
                throw new UserMessageException(t('Access denied.'));
            }
            $id = $this->request->request->get('id');
            $term = $id && is_numeric($id) ? $this->app->make(GlossaryEntryRepository::class)->find($id) : null;
            if ($term === null) {
                throw new UserMessageException(t('Unable to find the specified gossary entry.'));
            }
            $otherLocaleNames = [];
            foreach ($term->getTranslations() as $translation) {
                if ($translation->getLocale() !== $locale) {
                    $otherLocaleNames[] = $translation->getLocale()->getDisplayName();
                }
            }
            if ($otherLocaleNames !== []) {
                if (count($otherLocaleNames) < 5) {
                    throw new UserMessageException(t("It's not possible to delete this entry since it's translated in these languages too:", "\n- %s" . implode("\n- ", $otherLocaleNames)));
                }
                throw new UserMessageException(t("It's not possible to delete this entry since it's translated in %d other languages too.", count($otherLocaleNames)));
            }
            $em = $this->getEntityManager();
            $em->remove($term);
            $em->flush();

            return $rf->json(true);
        } catch (UserMessageException $x) {
            return $rf->json(['error' => $x->getMessage()], 400);
        }
    }

    public function load_all_places($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-load-all-places' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $this->getUserRequestedLocale($localeID);
            $id = $this->request->request->get('id');
            $translatable = $id && is_numeric($id) ? $this->app->make(TranslatableRepository::class)->find($id) : null;
            if ($translatable === null) {
                throw new UserMessageException(t('Unable to find the specified translatable string.'));
            }
            $editorService = $this->app->make(Editor::class);
            $result = [];
            foreach ($translatable->getPlaces() as $place) {
                $result[] = [
                    'packageVersion' => $place->getPackageVersion(),
                    'packageVersionDisplayName' => $place->getPackageVersion()->getDisplayName(),
                    'comments' => $place->getComments(),
                    'references' => $editorService->expandReferences($place->getLocations(), $place->getPackageVersion()),
                ];
            }
            usort($result, function (array $a, array $b) {
                $packageVersionA = $a['packageVersion'];
                $packageVersionB = $b['packageVersion'];
                $cmp = strcasecmp($packageVersionA->getPackage()->getDisplayName(), $packageVersionA->getPackage()->getDisplayName());
                if ($cmp === 0) {
                    $isDevA = strpos($packageVersionA->getVersion(), PackageVersionEntity::DEV_PREFIX) === 0;
                    $isDevB = strpos($packageVersionB->getVersion(), PackageVersionEntity::DEV_PREFIX) === 0;
                    if ($isDevA === $isDevB) {
                        $cmp = version_compare($packageVersionB->getVersion(), $packageVersionA->getVersion());
                    } else {
                        $cmp = $isDevA ? -1 : 1;
                    }
                }

                return $cmp;
            });
            foreach (array_keys($result) as $i) {
                unset($result[$i]['packageVersion']);
            }

            return $rf->json(
                $result
            );
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function process_translation($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-process-translation' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            $userService = $this->getUserService();
            $translatableID = $this->request->request->get('id');
            $translatable = $translatableID && is_numeric($translatableID) ? $this->app->make(TranslatableRepository::class)->find($translatableID) : null;
            if ($translatable === null) {
                throw new UserMessageException(t('Unable to find the specified translatable string.'));
            }
            $packageVersionID = $this->request->request->get('packageVersionID');
            if ($packageVersionID === self::PACKAGEVERSION_UNREVIEWED) {
                $packageVersion = null;
            } else {
                $packageVersion = $packageVersionID && is_numeric($packageVersionID) ? $this->app->make(PackageVersionRepository::class)->find($packageVersionID) : null;
                if ($packageVersion === null) {
                    throw new UserMessageException(t('Invalid translated package version identifier received'));
                }
                $packageVersionID = $packageVersion->getID();
            }
            $operation = $this->request->request->get('operation');
            if (!is_string($operation) || $operation === '') {
                throw new UserMessageException(t('Missing operation identifier'));
            }
            $processTranslationID = $this->request->request->get('translationID');
            if ($processTranslationID === null || $processTranslationID === '') {
                $processTranslation = null;
            } else {
                $processTranslation = $processTranslationID && is_numeric($processTranslationID) ? $this->app->make(TranslationRepository::class)->find($processTranslationID) : null;
                if ($processTranslation === null) {
                    throw new UserMessageException(t('Unable to find the specified translation.'));
                }
                if ($processTranslation->getTranslatable() !== $translatable) {
                    throw new UserMessageException(t('The specified translation is not for the correct string.'));
                }
                if ($processTranslation->getLocale() !== $locale) {
                    throw new UserMessageException(t('The specified translation is not for the correct language.'));
                }
            }
            switch ($operation) {
                case 'approve':
                    return $this->approveTranslation($access, $processTranslation, $userService->getUserEntity(UserService::CURRENT_USER_KEY));
                case 'deny':
                    return $this->denyTranslation($access, $processTranslation, $userService->getUserEntity(UserService::CURRENT_USER_KEY));
                case 'reuse':
                    return $this->reuseTranslation($access, $processTranslation, $userService->getUserEntity(UserService::CURRENT_USER_KEY), $packageVersionID);
                case 'save-current':
                    return $this->setTranslationFromEditor($access, $locale, $translatable, $userService->getUserEntity(UserService::CURRENT_USER_KEY), $packageVersion);
                case 'clear-current':
                    return $this->unsetTranslationFromEditor($access, $locale, $translatable);
                default:
                    throw new UserMessageException(t('Invalid operation identifier received: %s', $operation));
            }
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    public function download($localeID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-download-translations' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            $formatHandle = $this->request->request->get('download-format');
            $format = is_string($formatHandle) && $formatHandle !== '' ? $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle) : null;
            if ($format === null) {
                throw new UserMessageException(t('Invalid format identifier received'));
            }
            $packageVersionID = $this->request->request->get('packageVersion');
            if ($packageVersionID === self::PACKAGEVERSION_UNREVIEWED) {
                if ($access < AccessService::ADMIN) {
                    throw new UserMessageException(t('Invalid translated package version identifier received'));
                }
                $translations = $this->app->make(Exporter::class)->unreviewed($locale);
                $serializedTranslations = $format->serializeTranslations($translations);
                unset($translations);

                return $rf->create(
                    $serializedTranslations,
                    200,
                    [
                        'Content-Type' => 'application/octet-stream',
                        'Content-Disposition' => 'attachment; filename=translations-' . $locale->getID() . '.' . $format->getFileExtension(),
                        'Content-Transfer-Encoding' => 'binary',
                        'Content-Length' => strlen($serializedTranslations),
                        'Expires' => '0',
                    ]
                );
            }
            $packageVersion = $packageVersionID && is_numeric($packageVersionID) ? $this->app->make(PackageVersionRepository::class)->find($packageVersionID) : null;
            if ($packageVersion === null) {
                throw new UserMessageException(t('Invalid translated package version identifier received'));
            }

            return $this->app->make(TranslationFileExporter::class)->buildSerializedTranslationsFileResponse($packageVersion, $locale, $format);
        } catch (UserMessageException $x) {
            return $rf->error($x->getMessage());
        }
    }

    public function upload($localeID): Response
    {
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-upload-translations' . $localeID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $access = null;
            $locale = $this->getUserRequestedLocale($localeID, $access);
            $packageVersionID = $this->request->request->get('packageVersion');
            if ($packageVersionID !== self::PACKAGEVERSION_UNREVIEWED) {
                $packageVersionID = $packageVersionID && is_numeric($packageVersionID) ? (int) $packageVersionID : null;
            }
        } catch (UserMessageException $x) {
            return $this->app->make('helper/concrete/ui')->buildErrorResponse(
                t('An unexpected error occurred.'),
                h($x->getMessage())
            );
        }
        $session = $this->app->make('session');
        try {
            $file = $this->request->files->get('file');
            if ($file === null) {
                throw new UserMessageException(t('Please specify the file to be analyzed'));
            }
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
            if (!$file->isValid()) {
                throw new UserMessageException($file->getErrorMessage());
            }
            $converters = [];
            foreach ($this->app->make(TranslationsConverterProvider::class)->getByFileExtension($file->getClientOriginalExtension()) as $converter) {
                if ($converter->supportLanguageHeader() && $converter->supportPlurals() && $converter->canSerializeTranslations() && $converter->canUnserializeTranslations()) {
                    $converters[] = $converter;
                }
            }
            $err = null;
            $translations = null;
            foreach ($converters as $converter) {
                $t = $converter->loadTranslationsFromFile($file->getPathname());
                if (count($t) < 1) {
                    if ($err === null) {
                        $err = new UserMessageException(t('No translations found in uploaded file'));
                    }
                    continue;
                }
                if (!$t->getLanguage()) {
                    $err = new UserMessageException(t('The translation file does not contain a language header'));
                } elseif (strcasecmp($t->getLanguage(), $locale->getID()) !== 0) {
                    $err = new UserMessageException(t("The translation file is for the '%1\$s' language, not for '%2\$s'", $t->getLanguage(), $locale->getID()));
                } else {
                    $pf = $t->getPluralForms();
                    if ($pf === null) {
                        $err = new UserMessageException(t('The translation file does not define the plural rules'));
                    } elseif ($pf[0] !== $locale->getPluralCount()) {
                        $err = new UserMessageException(t('The translation file defines %1$s plural forms instead of %2$s', $pf[0], $locale->getPluralCount()));
                    } else {
                        $translations = $t;
                        break;
                    }
                }
            }
            if ($translations === null) {
                if ($err === null) {
                    throw new UserMessageException(t('Unknown file extension'));
                }
                throw $err;
            }
            $importer = $this->app->make(Importer::class);
            $me = $this->getUserService()->getUserEntity(UserService::CURRENT_USER_KEY);
            if ($access >= AccessService::ADMIN) {
                $importOptions = new ImportOptions(
                    $this->request->request->get('all-fuzzy') ? true : false,
                    $this->request->request->get('fuzzy-unapprove') ? true : false
                );
            } else {
                $importOptions = ImportOptions::forTranslators();
            }
            $imported = $importer->import($translations, $locale, $me, $importOptions);
            if ($imported->newApprovalNeeded > 0) {
                $this->app->make(NotificationRepository::class)->translationsNeedApproval(
                    $locale,
                    $imported->newApprovalNeeded,
                    (int) $me->getUserID(),
                    $packageVersionID === self::PACKAGEVERSION_UNREVIEWED ? null : $packageVersionID
                );
            }
            $session->set(
                'comtraShowDialogAtStartup',
                '
<table class="table table-condensed">
    <tbody>
        ' . ($imported->emptyTranslations > 0 ? ('<tr><td class="warning">' . t('Number of strings not translated (skipped)') . '</td><td> ' . $imported->emptyTranslations . '</td></tr>') : '') . '
        ' . ($imported->unknownStrings > 0 ? ('<tr><td class="danger">' . t('Number of translations for unknown translatable strings (skipped)') . '</td><td> ' . $imported->unknownStrings . '</td></tr>') : '') . '
        ' . ($imported->addedAsCurrent > 0 ? ('<tr><td class="success">' . t('Number of new translations added and marked as the current ones') . '</td><td> ' . $imported->addedAsCurrent . '</td></tr>') : '') . '
        ' . ($imported->addedNotAsCurrent > 0 ? ('<tr><td>' . t('Number of new translations added but not marked as the current ones') . '</td><td> ' . $imported->addedNotAsCurrent . '</td></tr>') : '') . '
        ' . ($imported->existingCurrentUntouched > 0 ? ('<tr><td>' . t('Number of already current translations untouched') . '</td><td> ' . $imported->existingCurrentUntouched . '</td></tr>') : '') . '
        ' . ($imported->existingCurrentApproved > 0 ? ('<tr><td class="success">' . t('Number of current translations marked as approved') . '</td><td> ' . $imported->existingCurrentApproved . '</td></tr>') : '') . '
        ' . ($imported->existingCurrentUnapproved > 0 ? ('<tr><td class="warning">' . t('Number of current translations marked as not approved') . '</td><td> ' . $imported->existingCurrentUnapproved . '</td></tr>') : '') . '
        ' . ($imported->existingActivated > 0 ? ('<tr><td class="success">' . t('Number of previous translations that have been activated (made current)') . '</td><td> ' . $imported->existingActivated . '</td></tr>') : '') . '
        ' . ($imported->existingNotCurrentUntouched > 0 ? ('<tr><td>' . t('Number of translations untouched') . '</td><td> ' . $imported->existingNotCurrentUntouched . '</td></tr>') : '') . '
        ' . ($imported->newApprovalNeeded > 0 ? ('<tr><td class="warning">' . t('Number of new translations needing approval') . '</td><td> ' . $imported->newApprovalNeeded . '</td></tr>') : '') . '
    </tbody>
</table>'
            );
        } catch (UserMessageException $x) {
            $session->set('comtraShowDialogAtStartup', '<div class="alert alert-danger">' . nl2br(h($x->getMessage())) . '</div>');
        }

        return $this->buildRedirect([$this->getOnlineTranslationPath(), $packageVersionID, $locale->getID()]);
    }

    public function save_notifications($packageID): Response
    {
        $rf = $this->app->make(ResponseFactoryInterface::class);
        try {
            $valt = $this->app->make('token');
            if (!$valt->validate('comtra-save-notifications' . $packageID)) {
                throw new UserMessageException($valt->getErrorMessage());
            }
            $package = $this->app->make(PackageRepository::class)->find($packageID);
            if ($package === null) {
                throw new UserMessageException(t('Invalid translated package identifier received'));
            }
            $packageVersions = [];
            foreach ($package->getVersions() as $pv) {
                $packageVersions[$pv->getID()] = $pv;
            }
            $post = $this->request->request;
            switch ((string) $post->get('newVersions')) {
                case '0':
                    $newVersions = false;
                    break;
                case '1':
                    $newVersions = true;
                    break;
                default:
                    throw new UserMessageException(t('Invalid parameter received (%s)', 'newVersions'));
            }
            switch ((string) $post->get('allVersions')) {
                case 'yes':
                    $notificationForVersions = array_values($packageVersions);
                    break;
                case 'no':
                    $notificationForVersions = [];
                    break;
                case 'custom':
                    $notificationForVersions = [];
                    foreach (explode(',', (string) $post->get('versions')) as $v) {
                        $v = (int) $v;
                        if (!isset($packageVersions[$v])) {
                            throw new UserMessageException(t('Invalid parameter received (%s)', 'versions'));
                        }
                        $notificationForVersions[] = $packageVersions[$v];
                    }
                    break;
                default:
                    throw new UserMessageException(t('Invalid parameter received (%s)', 'allVersions'));
            }
            $em = $this->getEntityManager();
            $em->beginTransaction();
            $ps = $this->getPackageSubscription($package);
            $ps->setNotifyNewVersions($newVersions);
            $em->persist($ps);
            $em->flush($ps);
            foreach ($this->getPackageVersionSubscriptions($package) as $pvs) {
                $pvs->setNotifyUpdates(in_array($pvs->getPackageVersion(), $notificationForVersions, true));
                $em->persist($pvs);
                $em->flush($pvs);
            }
            $em->commit();

            return $rf->json(true);
        } catch (UserMessageException $x) {
            return $rf->json(
                [
                    'error' => $x->getMessage(),
                ],
                400
            );
        }
    }

    private function getAccessService(): AccessService
    {
        if ($this->accessService === null) {
            $this->accessService = $this->app->make(AccessService::class);
        }

        return $this->accessService;
    }

    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = $this->app->make(UserService::class);
        }

        return $this->userService;
    }

    private function getEntityManager(): EntityManager
    {
        if ($this->entityManager === null) {
            $this->entityManager = $this->app->make(EntityManager::class);
        }

        return $this->entityManager;
    }

    /**
     * @param string|mixed $localeID
     * @param int $userAccess (output)
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function getUserRequestedLocale($localeID, ?int & $userAccess = null): LocaleEntity
    {
        if ($this->getUserService()->isLoggedIn() === false) {
            throw new UserMessageException(t('You need to be logged in'));
        }
        if (is_string($localeID) && $localeID !== '') {
            $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
        } else {
            $locale = null;
        }
        if ($locale === null) {
            throw new UserMessageException(t('Invalid language identifier received'));
        }
        $userAccess = $this->getAccessService()->getLocaleAccess($locale);
        if ($userAccess <= AccessService::NOT_LOGGED_IN) {
            throw new UserMessageException(t('You need to log-in in order to translate'));
        }
        if ($userAccess < AccessService::TRANSLATE) {
            throw new UserMessageException(t("You don't belong to the %s translation group", $locale->getDisplayName()));
        }

        return $locale;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function approveTranslation(int $access, TranslationEntity $translation, UserEntity $user): Response
    {
        if ($access < AccessService::ADMIN) {
            throw new UserMessageException(t('Access denied'));
        }
        if ($translation->isCurrent()) {
            throw new UserMessageException(t('The selected translation is already the current one'));
        }
        $translationID = $translation->getID();
        $translations = $this->convertTranslationToGettext($translation, false);
        $importer = $this->app->make(Importer::class);
        $importer->import($translations, $translation->getLocale(), $user, ImportOptions::forAdministrators());
        $this->getEntityManager()->clear();
        $translation = $this->app->make(TranslationRepository::class)->find($translationID);
        $result = $this->app->make(Editor::class)->getTranslations($translation->getLocale(), $translation->getTranslatable());

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function denyTranslation(int $access, TranslationEntity $translation): Response
    {
        if ($access < AccessService::ADMIN) {
            throw new UserMessageException(t('Access denied'));
        }
        if ($translation->isCurrent()) {
            throw new UserMessageException(t('The selected translation is already the current one'));
        }
        $em = $this->getEntityManager();
        $translation->setIsApproved(false);
        $em->persist($translation);
        $em->flush();

        $result = $this->app->make(Editor::class)->getTranslations($translation->getLocale(), $translation->getTranslatable());
        unset($result['current']);

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    /**
     * @param int|'unreviewed' $packageVersionID
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function reuseTranslation(int $access, TranslationEntity $translation, UserEntity $user, $packageVersionID): Response
    {
        if ($translation->isCurrent()) {
            throw new UserMessageException(t('The selected translation is already the current one'));
        }

        $translationID = $translation->getID();
        $translations = $this->convertTranslationToGettext($translation, $access < AccessService::ADMIN);
        $importer = $this->app->make(Importer::class);
        $imported = $importer->import($translations, $translation->getLocale(), $user, ($access >= AccessService::ADMIN) ? ImportOptions::forAdministrators() : ImportOptions::forTranslators());
        $this->getEntityManager()->clear();
        $translation = $this->app->make(TranslationRepository::class)->find($translationID);
        if ($imported->newApprovalNeeded > 0) {
            $this->app->make(NotificationRepository::class)->translationsNeedApproval(
                $translation->getLocale(),
                $imported->newApprovalNeeded,
                (int) $user->getUserID(),
                $packageVersionID === self::PACKAGEVERSION_UNREVIEWED ? null : $packageVersionID
            );
        }
        $result = $this->app->make(Editor::class)->getTranslations($translation->getLocale(), $translation->getTranslatable());
        if ($imported->newApprovalNeeded && !$imported->addedAsCurrent) {
            $result['message'] = t('Since the current translation is approved, you have to wait that this new translation will be approved');
        }
        if ($imported->addedNotAsCurrent || $imported->existingNotCurrentUntouched) {
            unset($result['current']);
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function setTranslationFromEditor(int $access, LocaleEntity $locale, TranslatableEntity $translatable, UserEntity $user, ?PackageVersionEntity $packageVersion = null): Response
    {
        $numStrings = $translatable->getPlural() === '' ? 1 : $locale->getPluralCount();
        $strings = $this->getTranslatedStrings($numStrings);
        $translation = new TranslationEntity($locale, $translatable, $strings[0]);
        for ($index = 1; $index < $numStrings; $index++) {
            $translation->{"setText{$index}"}($strings[$index]);
        }
        if ($access >= AccessService::ADMIN) {
            if ($this->request->request->get('approved') === '1') {
                $approved = true;
            } elseif ($this->request->request->get('approved') === '0') {
                $approved = false;
            } else {
                throw new UserMessageException(t('Missing parameter: %s', 'approved'));
            }
        } else {
            $approved = false;
        }

        $translations = $this->convertTranslationToGettext($translation, !$approved);
        $importer = $this->app->make(Importer::class);
        $imported = $importer->import($translations, $locale, $user, ($access >= AccessService::ADMIN) ? ImportOptions::forAdministrators() : ImportOptions::forTranslators());
        $this->getEntityManager()->clear();
        $translatable = $this->app->make(TranslatableRepository::class)->find($translatable->getID());
        $locale = $this->app->make(LocaleRepository::class)->find($locale->getID());
        if ($imported->newApprovalNeeded > 0) {
            $this->app->make(NotificationRepository::class)->translationsNeedApproval(
                $locale,
                $imported->newApprovalNeeded,
                (int) $user->getUserID(),
                $packageVersion === null ? null : $packageVersion->getID()
            );
        }
        $result = $this->app->make(Editor::class)->getTranslations($locale, $translatable);
        if ($imported->newApprovalNeeded && !$imported->addedAsCurrent) {
            $result['message'] = t('Since the current translation is approved, you have to wait that this new translation will be approved');
        }
        $this->app->make(EventDispatcher::class)->dispatch('community_translation.translation_submitted', new GenericEvent($user));

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     */
    private function unsetTranslationFromEditor(int $access, LocaleEntity $locale, TranslatableEntity $translatable): Response
    {
        $currentTranslation = $this->app->make(TranslationRepository::class)->findOneBy([
            'locale' => $locale,
            'translatable' => $translatable,
            'current' => true,
        ]);
        if ($currentTranslation !== null) {
            $em = $this->getEntityManager();
            if ($currentTranslation->isApproved() && $access < AccessService::ADMIN) {
                throw new UserMessageException(t("The current translation is marked as reviewed, so you can't remove it."));
            }
            $currentTranslation->setIsCurrent(false);
            $em->persist($currentTranslation);
            $em->flush();
            $this->app->make(StatsRepository::class)->resetForTranslation($currentTranslation);
            $result = $this->app->make(Editor::class)->getTranslations($locale, $translatable);
        } else {
            $result = [];
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($result);
    }

    private function getVersionsMenu(PackageVersionEntity $packageVersion, LocaleEntity $locale): ?array
    {
        $onlineTranslationPath = $this->getOnlineTranslationPath();
        $urlManager = $this->app->make(ResolverManagerInterface::class);
        $result = [];
        foreach ($packageVersion->getPackage()->getSortedVersions(true) as $pv) {
            if ($pv === $packageVersion) {
                $url = '';
            } else {
                $url = (string) $urlManager->resolve([$onlineTranslationPath, $pv->getID(), $locale->getID()]);
            }
            $result[$url] = $pv->getDisplayVersion();
        }

        return count($result) < 2 ? null : $result;
    }

    /**
     * @param int|'unreviewed' $packageVersionID
     */
    private function getLocalesMenu($packageVersionID, LocaleEntity $locale): ?array
    {
        $accessService = $this->getAccessService();
        $onlineTranslationPath = $this->getOnlineTranslationPath();
        $urlManager = $this->app->make(ResolverManagerInterface::class);
        $result = [];
        foreach ($this->app->make(LocaleRepository::class)->getApprovedLocales() as $l) {
            if ($accessService->getLocaleAccess($locale) >= AccessService::TRANSLATE) {
                if ($l === $locale) {
                    $url = '';
                } else {
                    $url = (string) $urlManager->resolve([$onlineTranslationPath, $packageVersionID, $l->getID()]);
                }
                $result[$url] = $l->getDisplayName();
            }
        }

        return count($result) < 2 ? null : $result;
    }

    private function convertTranslationToGettext(TranslationEntity $translation, bool $markAsFuzzy): GettextTranslations
    {
        $translatable = $translation->getTranslatable();
        $locale = $translation->getLocale();
        $translations = new GettextTranslations();
        $translations->setLanguage($locale->getID());
        $translations->setPluralForms($locale->getPluralCount(), $locale->getPluralFormula());
        $t = $translations->insert($translatable->getContext(), $translatable->getText(), $translatable->getPlural());
        $t->setTranslation($translation->getText0());
        if ($translatable->getPlural() !== '') {
            $numPlurals = $locale->getPluralCount();
            if ($numPlurals >= 2) {
                $t->setPluralTranslation($translation->getText1(), 0);
                if ($numPlurals >= 3) {
                    $t->setPluralTranslation($translation->getText2(), 1);
                    if ($numPlurals >= 4) {
                        $t->setPluralTranslation($translation->getText3(), 2);
                        if ($numPlurals >= 5) {
                            $t->setPluralTranslation($translation->getText4(), 3);
                            if ($numPlurals >= 6) {
                                $t->setPluralTranslation($translation->getText5(), 4);
                            }
                        }
                    }
                }
            }
        }
        if ($markAsFuzzy) {
            $t->addFlag('fuzzy');
        }

        return $translations;
    }

    private function getPackageSubscription(PackageEntity $package): PackageSubscriptionEntity
    {
        $me = $this->getUserService()->getUserEntity(UserService::CURRENT_USER_KEY);
        $repo = $this->app->make(PackageSubscriptionRepository::class);
        $ps = $repo->find(['user' => $me, 'package' => $package]);
        if ($ps === null) {
            $ps = new PackageSubscriptionEntity($me, $package, false);
            $em = $this->getEntityManager();
            $em->persist($ps);
            $em->flush($ps);
        }

        return $ps;
    }

    /**
     * @return \CommunityTranslation\Entity\PackageVersionSubscription[]
     */
    private function getPackageVersionSubscriptions(PackageEntity $package): array
    {
        $result = [];
        $me = $this->getUserService()->getUserEntity(UserService::CURRENT_USER_KEY);
        $repo = $this->app->make(PackageVersionSubscriptionRepository::class);
        $pvsList = $repo->createQueryBuilder('s')
            ->innerJoin(PackageVersionEntity::class, 'pv', 'WITH', 's.packageVersion = pv.id')
            ->where('s.user = :user')->setParameter('user', $me)
            ->andWhere('pv.package = :package')->setParameter('package', $package)
            ->getQuery()
            ->execute()
        ;
        foreach ($package->getSortedVersions(true) as $packageVersion) {
            $pvs = null;
            foreach ($pvsList as $existing) {
                if ($existing->getPackageVersion() === $packageVersion) {
                    $pvs = $existing;
                    break;
                }
            }
            if ($pvs === null) {
                $pvs = new PackageVersionSubscriptionEntity($me, $packageVersion, false);
            }
            $result[] = $pvs;
        }

        return $result;
    }

    private function getOnlineTranslationPath(): string
    {
        $config = $this->app->make(Repository::class);

        return rtrim((string) $config->get('community_translation::paths.onlineTranslation'), '/');
    }

    private function getAllTranslators(LocaleEntity $locale): array
    {
        $result = [];
        $us = $this->getUserService();
        $rs = $this->getEntityManager()->getConnection()->executeQuery('SELECT DISTINCT createdBy FROM CommunityTranslationTranslations WHERE locale = :locale', ['locale' => $locale->getID()]);
        $deletedUserName = t('<deleted user>');
        $systemUserName = t('<system>');
        foreach ($rs->iterateColumn() as $userID) {
            $userName = $deletedUserName;
            if ($userID !== null) {
                $userID = (int) $userID;
                if ($userID === USER_SUPER_ID) {
                    $userName = $systemUserName;
                } else {
                    $user = $us->getUserObject($userID);
                    if ($user !== null) {
                        $userName = $user->getUserName();
                    } else {
                        $userName = t('<deleted user %s>', $userID);
                    }
                }
            }
            $result[] = [
                'id' => $userID,
                'name' => $userName,
            ];
        }
        $cmp = new Comparer();
        usort(
            $result,
            static function (array $a, array $b) use ($cmp): int {
                return $cmp->compare($a['name'], $b['name']);
            }
        );

        return $result;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return string[]
     */
    private function getTranslatedStrings(int $expectedCount): ?array
    {
        $base64Strings = $this->request->request->get('translatedB64');
        if (!is_array($base64Strings) || count($base64Strings) !== $expectedCount) {
            throw new UserMessageException(t('Please specify the translations'));
        }
        $result = [];
        for ($index = 0; $index < $expectedCount; $index++) {
            $string = '';
            $base64String = $base64Strings[$index] ?? null;
            if (is_string($base64String)) {
                set_error_handler(static function () {}, -1);
                try {
                    $urlEncodedString = base64_decode($base64String, true);
                    if (is_string($urlEncodedString)) {
                        $string = rawurldecode($urlEncodedString);
                    }
                } finally {
                    restore_error_handler();
                }
            }
            if (!is_string($string) || trim($string) === '') {
                throw new UserMessageException(t('Please specify the translations'));
            }
            $result[] = $string;
        }

        return $result;
    }
}
