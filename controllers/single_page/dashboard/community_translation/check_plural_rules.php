<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Notification;
use CommunityTranslation\Repository\Locale as LocaleRepository;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Doctrine\DBAL\Connection;
use Punic\Comparer;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class CheckPluralRules extends DashboardPageController
{
    public function view(): ?Response
    {
        $repo = $this->app->make(LocaleRepository::class);
        $locales = [];
        foreach ($repo->findAll() as $locale) {
            $locales[] = $this->serializeLocale($locale);
        }
        $cmp = new Comparer();
        usort(
            $locales,
            static function (array $a, array $b) use ($cmp): int {
                if ($a['source'] !== $b['source']) {
                    return $a['source'] ? -1 : 1;
                }
                if ($a['approved'] !== $b['approved']) {
                    return $a['approved'] ? -1 : 1;
                }

                return $cmp->compare($a['name'], $b['name']);
            }
        );
        $this->set('locales', $locales);

        return null;
    }

    public function fix_locale(): Response
    {
        if (!$this->token->validate('comtra-plru-fix1')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $id = $this->request->request->get('id');
        $id = is_string($id) ? trim($id) : '';
        $locale = $id === '' ? null : $this->entityManager->find(LocaleEntity::class, $id);
        if ($locale === null) {
            throw new UserMessageException(t('Unable to find the requested locale.'));
        }
        $changedTranslations = 0;
        $this->fixLocale($locale, $changedTranslations);
        if ($changedTranslations > 0) {
            $this->entityManager->getRepository(Notification::class)->pluralChangedReapprovalNeeded($locale, $changedTranslations);
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($this->serializeLocale($locale));
    }

    private function serializeLocale(LocaleEntity $locale): array
    {
        $newLocale = new LocaleEntity($locale->getID());

        return [
            'id' => $locale->getID(),
            'name' => $locale->getDisplayName(),
            'source' => $locale->isSource(),
            'approved' => $locale->isApproved(),
            'pluralRules' => [
                'actual' => $locale->getPluralCount(),
                'expected' => $newLocale->getPluralCount(),
            ],
            'pluralFormula' => [
                'actual' => $locale->getPluralFormula(),
                'expected' => $newLocale->getPluralFormula(),
            ],
            'pluralForms' => [
                'actual' => $this->serializePluralForms($locale->getPluralForms()),
                'expected' => $this->serializePluralForms($newLocale->getPluralForms()),
            ],
        ];
    }

    private function serializePluralForms(array $forms): string
    {
        return implode("\n", $forms);
    }

    private function fixLocale(LocaleEntity $locale, ?int &$changedTranslations = null): void
    {
        $this->entityManager->getConnection()->transactional(
            function (Connection $cn) use ($locale, &$changedTranslations) {
                $newLocale = new LocaleEntity($locale->getID());
                $expectedPluralCount = $newLocale->getPluralCount();
                $expectedPluralFormula = $newLocale->getPluralFormula();
                $expectedPluralForms = $newLocale->getPluralForms();
                $changedTranslations = $this->fixLocalePluralCount($locale, $expectedPluralCount, $cn);
                if ($locale->getPluralFormula() !== $expectedPluralFormula) {
                    $locale->setPluralFormula($expectedPluralFormula);
                }
                if ($locale->getPluralForms() !== $expectedPluralForms) {
                    $locale->setPluralForms($expectedPluralForms);
                }
                $this->entityManager->flush();
            }
        );
    }

    /**
     * @return int The number of approved translations that have been updated and marked as not approved
     */
    private function fixLocalePluralCount(LocaleEntity $locale, int $expectedPluralCount, Connection $cn): int
    {
        $actualPluralCount = $locale->getPluralCount();
        $delta = $expectedPluralCount - $actualPluralCount;
        if ($delta === 0) {
            return 0;
        }
        $params0 = [
            'locale' => $locale->getID(),
        ];
        $wheres0 = [
            'CommunityTranslationTranslations.locale = :locale',
            "CommunityTranslationTranslatables.plural != ''",
        ];
        $sets0 = [];
        if ($delta > 0) {
            $previousLastField = 'text' . ($actualPluralCount - 1);
            for ($index = 0; $index < $actualPluralCount; $index++) {
                $wheres0[] = "CommunityTranslationTranslations.text{$index} != ''";
            }
            for ($index = $actualPluralCount; $index < $expectedPluralCount; $index++) {
                $wheres0[] = "CommunityTranslationTranslations.text{$index} = ''";
                $sets0[] = "CommunityTranslationTranslations.text{$index} = CommunityTranslationTranslations.{$previousLastField}";
            }
        } else {
            $previousLastField = 'text' . ($actualPluralCount - 1);
            $newLastField = 'text' . ($expectedPluralCount - 1);
            for ($index = 0; $index < $expectedPluralCount; $index++) {
                $wheres0[] = "CommunityTranslationTranslations.text{$index} != ''";
            }
            $wheres0[] = "CommunityTranslationTranslations.{$previousLastField} != ''";
            $sets0[] = "CommunityTranslationTranslations.{$newLastField} = CommunityTranslationTranslations.{$previousLastField}";
            for ($index = $expectedPluralCount; $index < $actualPluralCount; $index++) {
                $sets0[] = "CommunityTranslationTranslations.text{$index} = ''";
            }
        }
        foreach ([false, true] as $approved) {
            $params = $params0;
            $wheres = $wheres0;
            $sets = $sets0;
            if ($approved) {
                $wheres[] = 'CommunityTranslationTranslations.approved = 1';
                $sets[] = 'CommunityTranslationTranslations.approved = NULL';
            } else {
                $wheres[] = 'CommunityTranslationTranslations.approved IS NULL';
            }
            $sqlSets = implode(",\n    ", $sets);
            $sqlWheres = implode("\n    AND ", $wheres);
            $sql = <<<EOT
UPDATE
    CommunityTranslationTranslations INNER JOIN CommunityTranslationTranslatables ON CommunityTranslationTranslations.translatable = CommunityTranslationTranslatables.id
SET
    {$sqlSets}
WHERE
    {$sqlWheres}
EOT
            ;
            $affectedRows = $cn->executeStatement($sql, $params);
            if ($approved) {
                $result = $affectedRows;
            }
        }

        return $result;
    }
}
