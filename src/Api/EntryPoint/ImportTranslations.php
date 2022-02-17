<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\AccessDeniedException;
use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Repository\Notification as NotificationRepository;
use CommunityTranslation\Translation\Importer as TranslationImporter;
use CommunityTranslation\Translation\ImportOptions as TranslationImportOptions;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Error\UserMessageException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Import the translations for a specific locale.
 *
 * @example POST request to http://www.example.com/api/translations/it_IT/po/0 in multipart/form-data format with these fields:
 * - file: the file containing the translations (required)
 * - packageName: the name of the package (required when creating a new package, optional if the package already exists)
 */
class ImportTranslations extends EntryPoint
{
    public const ACCESS_KEY_WITHOUTAPPROVE = 'importTranslations';

    public const ACCESS_KEY_WITHAPPROVE = 'importTranslations_approve';

    public function __invoke(string $localeID, string $formatHandle, string $approve): Response
    {
        return $this->handle(
            function () use ($localeID, $formatHandle, $approve): Response {
                $approve = (bool) $approve;
                $accessibleLocales = $this->userControl->checkLocaleAccess($approve ? static::ACCESS_KEY_WITHAPPROVE : static::ACCESS_KEY_WITHOUTAPPROVE);
                $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
                if ($locale === null) {
                    throw new UserMessageException(t('Unable to find the specified locale'), Response::HTTP_NOT_FOUND);
                }
                if (!in_array($locale, $accessibleLocales, true)) {
                    throw new AccessDeniedException(t('Access denied to the specified locale'));
                }
                $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
                if ($format === null) {
                    throw new UserMessageException(t('Unable to find the specified translations format'), 404);
                }
                $file = $this->request->files->get('file');
                if ($file === null) {
                    throw new UserMessageException(t('The file with translated strings has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                if (!$file->isValid()) {
                    throw new UserMessageException(t('The file with translated strings has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
                }
                $translations = $format->loadTranslationsFromFile($file->getPathname());
                if (count($translations) < 1) {
                    throw new UserMessageException(t('No translations found in the received file'));
                }
                if (!$translations->getLanguage()) {
                    throw new UserMessageException(t('The translation file does not contain a language header'));
                }
                if (strcasecmp($translations->getLanguage(), $locale->getID()) !== 0) {
                    throw new UserMessageException(t("The translation file is for the '%1\$s' language, not for '%2\$s'", $translations->getLanguage(), $locale->getID()));
                }
                $pluralForms = $translations->getPluralForms();
                if ($pluralForms === null) {
                    throw new UserMessageException(t('The translation file does not define the plural rules'));
                }
                if ($pluralForms[0] !== $locale->getPluralCount()) {
                    throw new UserMessageException(t('The translation file defines %1$s plural forms instead of %2$s', $pluralForms[0], $locale->getPluralCount()));
                }
                $importer = $this->app->make(TranslationImporter::class);
                $userAssociatedWithTranslations = $this->userControl->getAssociatedUserEntity();
                $imported = $importer->import($translations, $locale, $userAssociatedWithTranslations, $approve ? TranslationImportOptions::forAdministrators() : TranslationImportOptions::forTranslators());
                if ($imported->newApprovalNeeded > 0) {
                    $this->app->make(NotificationRepository::class)->translationsNeedApproval(
                        $locale,
                        $imported->newApprovalNeeded,
                        (int) $userAssociatedWithTranslations->getUserID(),
                        null
                    );
                }

                return $this->buildJsonResponse($imported);
            }
        );
    }
}
