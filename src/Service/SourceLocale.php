<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class SourceLocale
{
    private EntityManagerInterface $em;

    private LocaleRepository $localeRepository;

    private ?LocaleEntity $locale = null;

    private bool $localeFetched = false;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->localeRepository = $this->em->getRepository(LocaleEntity::class);
    }

    /**
     * Get the ID of the current source locale (or an empty string if it's not defined yet).
     *
     * @return string
     */
    public function getSourceLocaleID(): string
    {
        $locale = $this->getSourceLocale(false);

        return $locale === null ? '' : $locale->getID();
    }

    public function getSourceLocale(): ?LocaleEntity
    {
        if ($this->localeFetched === false) {
            $this->locale = $this->localeRepository->findOneBy(['isSource' => true]);
            $this->localeFetched = true;
        }

        return $this->locale;
    }

    /**
     * Get the source locale, and throws an exception if it's not defined.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function getRequiredSourceLocale(): LocaleEntity
    {
        $sourceLocale = $this->getSourceLocale();
        if ($sourceLocale === null) {
            throw new UserMessageException(t('The source locale is not defined.'));
        }

        return $sourceLocale;
    }

    /**
     * Set the initial source locale, or change the current one.
     *
     * @throws \Concrete\Core\Error\UserMessageException if the new source locale doesn't pass the checkElibible() checks
     *
     * @return bool true if the new source locale has been set, false otherwise (if the new source locale is the same as the old one)
     *
     * @see \CommunityTranslation\Service\SourceLocale::checkElibible()
     */
    public function switchSourceLocale(LocaleEntity $newSourceLocale): bool
    {
        $this->checkElibible($newSourceLocale);
        $currentSourceLocale = $this->fetchSourceLocale();
        if (
            $currentSourceLocale->getID() === $newSourceLocale->getID()
            && $currentSourceLocale->getPluralCount() === $newSourceLocale->getPluralCount()
            && $currentSourceLocale->getPluralForms() === $newSourceLocale->getPluralForms()
            && $currentSourceLocale->getPluralFormula() === $newSourceLocale->getPluralFormula()
        ) {
            return false;
        }
        $this->em->transactional(function () use ($currentSourceLocale, $newSourceLocale): void {
            if ($currentSourceLocale !== null) {
                $this->em->remove($currentSourceLocale);
                $this->em->flush($currentSourceLocale);
            }
            $newSourceLocale
                ->setIsSource(true)
                ->setIsApproved(true)
            ;
            $this->em->persist($newSourceLocale);
            $this->em->flush($newSourceLocale);
        });
        $this->locale = $newSourceLocale;
        $this->localeFetched = true;

        return true;
    }

    /**
     * Check if a locale can be made the source locale.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     */
    public function checkElibible(LocaleEntity $newSourceLocale): void
    {
        $currentSourceLocale = $this->fetchSourceLocale();
        if ($currentSourceLocale !== null && $currentSourceLocale->getID() === $newSourceLocale->getID() && $currentSourceLocale->getPluralCount() === $newSourceLocale->getPluralCount()) {
            return;
        }
        if ($newSourceLocale->getPluralCount() !== 2) {
            throw new UserMessageException(t('Because of the gettext specifications, the source locale must have exactly 2 plural forms'));
        }
        $existingLocale = $this->localeRepository->find($newSourceLocale->getID());
        if ($existingLocale !== null) {
            throw new UserMessageException(t("There's already an existing locale with code %s that's not the current source locale", $newSourceLocale->getID()));
        }
    }
}
