<?php

declare(strict_types=1);

namespace CommunityTranslation\Api\EntryPoint;

use CommunityTranslation\Api\EntryPoint;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use CommunityTranslation\Translation\Exporter as TranslationExporter;
use CommunityTranslation\TranslationsConverter\Provider as TranslationsConverterProvider;
use Concrete\Core\Error\UserMessageException;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Fill-in translations that we already know.
 *
 * @return Response
 *
 * @example POST request to http://www.example.com/api/fill-translations/po in multipart/form-data format with these fields:
 * - file: the file containing the translations to be filled-in
 */
class FillTranslations extends EntryPoint
{
    public const ACCESS_KEY = 'fillTranslations';

    public function __invoke(string $formatHandle): Response
    {
        return $this->handle(
            function () use ($formatHandle): Response {
                $this->userControl->checkGenericAccess(static::ACCESS_KEY);
                $format = $this->app->make(TranslationsConverterProvider::class)->getByHandle($formatHandle);
                if ($format === null) {
                    throw new UserMessageException(t('Unable to find the specified translations format'), Response::HTTP_NOT_FOUND);
                }
                if (!$format->canUnserializeTranslations()) {
                    throw new UserMessageException(t('The specified translations format does not support unserialization'), Response::HTTP_NOT_ACCEPTABLE);
                }
                if (!$format->canSerializeTranslations()) {
                    throw new UserMessageException(t('The specified translations format does not support serialization'), Response::HTTP_NOT_ACCEPTABLE);
                }
                if (!$format->supportLanguageHeader()) {
                    throw new UserMessageException(t('The specified translations format does not support a language header'), Response::HTTP_NOT_ACCEPTABLE);
                }
                $file = $this->request->files->get('file');
                if ($file === null) {
                    throw new UserMessageException(t('The file with strings to be translated has not been specified'), Response::HTTP_NOT_ACCEPTABLE);
                }
                /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
                if (!$file->isValid()) {
                    throw new UserMessageException(t('The file with strings to be translated has not been received correctly: %s', $file->getErrorMessage()), Response::HTTP_NOT_ACCEPTABLE);
                }
                $translations = $format->loadTranslationsFromFile($file->getPathname());
                $localeID = (string) $translations->getLanguage();
                if ($localeID === '') {
                    throw new UserMessageException(t('The file with strings to be translated does not specify a language ID'));
                }
                $locale = $this->app->make(LocaleRepository::class)->findApproved($localeID);
                if ($locale === null) {
                    throw new UserMessageException(t('The file with strings to be translated specifies an unknown language ID (%s)', $localeID));
                }
                $translationExporter = $this->app->make(TranslationExporter::class);
                $translations = $translationExporter->fromPot($translations, $locale);

                return $this->responseFactory->create(
                    $format->serializeTranslations($translations),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => 'application/octet-stream',
                        'Content-Transfer-Encoding' => 'binary',
                        'Content-Disposition' => 'attachment; filename="translations.' . $format->getFileExtension() . '"',
                    ]
                );
            }
        );
    }
}
