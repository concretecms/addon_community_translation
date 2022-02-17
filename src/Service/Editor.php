<?php

declare(strict_types=1);

namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Entity\Translatable as TranslatableEntity;
use CommunityTranslation\Entity\Translatable\Comment as TranslatableCommentEntity;
use CommunityTranslation\Repository\GitRepository as GitRepositoryRepository;
use CommunityTranslation\Repository\Translatable\Comment as TranslatableCommentRepository;
use CommunityTranslation\Repository\Translatable\Place as TranslatablePlaceRepository;
use CommunityTranslation\Repository\Translation as TranslationRepository;
use CommunityTranslation\Service\User as UserService;
use CommunityTranslation\Translation\Exporter as TranslationExporter;
use Concrete\Core\Application\Application;
use Concrete\Core\User\User as UserObject;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManager;

defined('C5_EXECUTE') or die('Access Denied.');

class Editor
{
    /**
     * Maximum number of translation suggestions.
     *
     * @var int
     */
    private const MAX_SUGGESTIONS = 15;

    /**
     * Maximum number of glossary terms to show in the editor.
     *
     * @var int
     */
    private const MAX_GLOSSARY_ENTRIES = 15;

    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Returns the initial translations for the online editor, for a specific package version.
     */
    public function getInitialTranslations(PackageVersionEntity $packageVersion, LocaleEntity $locale): array
    {
        $rs = $this->app->make(TranslationExporter::class)->getPackageSelectQuery($packageVersion, $locale, false);

        return $this->buildInitialTranslations($locale, $rs);
    }

    /**
     * Returns the initial translations to be reviewed for the online editor, for a specific locale.
     */
    public function getUnreviewedInitialTranslations(LocaleEntity $locale): array
    {
        $rs = $this->app->make(TranslationExporter::class)->getUnreviewedSelectQuery($locale);

        return $this->buildInitialTranslations($locale, $rs);
    }

    /**
     * Returns the data to be used in the editor when translating a string in a specific locale.
     *
     * @param \CommunityTranslation\Entity\Package\Version|null $packageVersion the package version where this string is used (if applicable)
     * @param bool $initial set to true when a string is first loaded, false after it has been saved
     */
    public function getTranslatableData(LocaleEntity $locale, TranslatableEntity $translatable, ?PackageVersionEntity $packageVersion = null, bool $initial = false): array
    {
        $result = [
            'id' => $translatable->getID(),
            'translations' => $this->getTranslations($locale, $translatable),
        ];
        if ($initial) {
            $place = $packageVersion === null ? null : $this->app->make(TranslatablePlaceRepository::class)->findOneBy(['packageVersion' => $packageVersion, 'translatable' => $translatable]);
            $result += [
                'extractedComments' => $place === null ? [] : $place->getComments(),
                'references' => $place === null ? [] : $this->expandReferences($place->getLocations(), $packageVersion),
                'comments' => $this->getComments($locale, $translatable),
                'suggestions' => $this->getSuggestions($locale, $translatable),
                'glossary' => $this->getGlossaryTerms($locale, $translatable),
            ];
        }

        return $result;
    }

    /**
     * Get all the translations in a specific locale associated to a specific translatable string.
     */
    public function getTranslations(LocaleEntity $locale, TranslatableEntity $translatable): array
    {
        $numPlurals = $locale->getPluralCount();

        $result = [
            'current' => null,
            'others' => [],
        ];
        $translations = $this->app->make(TranslationRepository::class)->findBy(['translatable' => $translatable, 'locale' => $locale], ['createdOn' => 'DESC']);
        $dh = $this->app->make('helper/date');
        $uh = $this->app->make(UserService::class);
        foreach ($translations as $translation) {
            /** @var \CommunityTranslation\Entity\Translation $translation */
            $texts = [];
            switch (($translatable->getPlural() === '') ? 1 : $numPlurals) {
                case 6:
                    $texts[] = $translation->getText5();
                    // no break
                case 5:
                    $texts[] = $translation->getText4();
                    // no break
                case 4:
                    $texts[] = $translation->getText3();
                    // no break
                case 3:
                    $texts[] = $translation->getText2();
                    // no break
                case 2:
                    $texts[] = $translation->getText1();
                    // no break
                case 1:
                default:
                    $texts[] = $translation->getText0();
                    break;
            }
            $item = [
                'id' => $translation->getID(),
                'createdOn' => $dh->formatPrettyDateTime($translation->getCreatedOn(), false, true),
                'createdBy' => $uh->format($translation->getCreatedBy(), true),
                'approved' => $translation->isApproved(),
                'translations' => array_reverse($texts),
            ];
            if ($translation->isCurrent()) {
                $item['currentSince'] = $dh->formatPrettyDateTime($translation->getCurrentSince(), false, true);
                $result['current'] = $item;
            } else {
                $result['others'][] = $item;
            }
        }

        return $result;
    }

    /**
     * Get the comments associated to a translatable strings in a specific locale.
     */
    public function getComments(LocaleEntity $locale, TranslatableEntity $translatable, ?TranslatableCommentEntity $parentComment = null): array
    {
        $repo = $this->app->make(TranslatableCommentRepository::class);
        if ($parentComment === null) {
            $qb = $repo->createQueryBuilder('c');
            $qb
                ->where('c.translatable = :translatable')
                ->andWhere('c.parentComment is null')
                ->andWhere($qb->expr()->orX(
                    'c.locale = :locale',
                    'c.locale is null'
                ))
                ->orderBy('c.postedOn', 'ASC')
                ->setParameter('translatable', $translatable)
                ->setParameter('locale', $locale)
                ;
            $comments = $qb->getQuery()->getResult();
        } else {
            $comments = $repo->findBy(
                ['parentComment' => $parentComment],
                ['postedOn' => 'ASC']
            );
        }
        $result = [];
        $uh = $this->app->make(UserService::class);
        $dh = $this->app->make('helper/date');
        $me = $this->app->make(UserObject::class);
        $myID = $me->isRegistered() ? (int) $me->getUserID() : null;
        foreach ($comments as $comment) {
            $result[] = [
                'id' => $comment->getID(),
                'date' => $dh->formatPrettyDateTime($comment->getPostedOn(), true, true),
                'mine' => $myID !== null && $comment->getPostedBy() !== null && $myID === (int) $comment->getPostedBy()->getUserID(),
                'by' => $uh->format($comment->getPostedBy(), true),
                'text' => $comment->getText(),
                'comments' => $this->getComments($locale, $translatable, $comment),
            ] + ($parentComment === null ? ['isGlobal' => $comment->getLocale() === null] : []);
        }

        return $result;
    }

    /**
     * Expand translatable string references by adding a link to the online repository where they are defined.
     *
     * @param string[] $references
     *
     * @return string[]
     */
    public function expandReferences(array $references, PackageVersionEntity $packageVersion): array
    {
        if ($references === []) {
            return [];
        }
        $gitRepositories = $this->app->make(GitRepositoryRepository::class)->findBy(['packageHandle' => $packageVersion->getPackage()->getHandle()]);
        $applicableRepository = null;
        $foundVersionData = null;
        foreach ($gitRepositories as $gitRepository) {
            $d = $gitRepository->getDetectedVersion($packageVersion->getVersion());
            if ($d !== null) {
                $applicableRepository = $gitRepository;
                $foundVersionData = $d;
                break;
            }
        }
        $pattern = null;
        $lineFormat = null;
        if ($applicableRepository !== null) {
            $matches = null;
            switch (true) {
                case preg_match('/^(?:\w+:\/\/|\w+@)github.com[:\/]([a-z0-9_.\-]+\/[a-z0-9_.\-]+)\.git$/i', $applicableRepository->getURL(), $matches) == 1:
                    switch ($foundVersionData['kind']) {
                        case 'tag':
                            $pattern = "https://github.com/{$matches[1]}/blob/{$foundVersionData['repoName']}/<<FILE>><<LINE>>";
                            $lineFormat = '#L%s';
                            break;
                        case 'branch':
                            $pattern = "https://github.com/{$matches[1]}/blob/{$foundVersionData['repoName']}/<<FILE>><<LINE>>";
                            $lineFormat = '#L%s';
                            break;
                    }
                    break;
            }
        }
        $result = $references;
        if ($pattern !== null) {
            $prefix = $applicableRepository->getDirectoryToParse();
            if ($prefix !== '') {
                $prefix .= '/';
            }
            $stripSuffix = $applicableRepository->getDirectoryForPlaces();
            if ($stripSuffix !== '') {
                $stripSuffix .= '/';
            }
            $m = null;
            foreach ($result as $index => $reference) {
                if (!preg_match('/^\w*:\/\//', $reference)) {
                    if (preg_match('/^(.+):(\d+)$/', $reference, $m)) {
                        $file = $m[1];
                        $line = $m[2];
                    } else {
                        $file = $reference;
                        $line = null;
                    }
                    $file = ltrim($file, '/');
                    $line = ($line === null || $lineFormat === null) ? '' : sprintf($lineFormat, $line);
                    if ($stripSuffix !== '' && strpos($file, $stripSuffix) === 0) {
                        $file = $prefix . substr($file, strlen($stripSuffix));
                    }
                    $result[$index] = [
                        str_replace(['<<FILE>>', '<<LINE>>'], [$file, $line], $pattern),
                        $reference,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Builds the initial translations array.
     */
    private function buildInitialTranslations(LocaleEntity $locale, Result $rs): array
    {
        $result = [];
        $numPlurals = $locale->getPluralCount();
        while (($row = $rs->fetch()) !== false) {
            $item = [
                'id' => (int) $row['id'],
                'original' => $row['text'],
            ];
            if ($row['context'] !== '') {
                $item['context'] = $row['context'];
            }
            $isPlural = $row['plural'] !== '';
            if ($isPlural) {
                $item['originalPlural'] = $row['plural'];
            }
            if ($row['text0'] !== null) {
                $item['isApproved'] = (bool) $row['approved'];
                $translations = [];
                switch ($isPlural ? $numPlurals : 1) {
                    case 6:
                        $translations[] = $row['text5'];
                        // no break
                    case 5:
                        $translations[] = $row['text4'];
                        // no break
                    case 4:
                        $translations[] = $row['text3'];
                        // no break
                    case 3:
                        $translations[] = $row['text2'];
                        // no break
                    case 2:
                        $translations[] = $row['text1'];
                        // no break
                    case 1:
                        $translations[] = $row['text0'];
                        break;
                }
                $item['translations'] = array_reverse($translations);
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Search for similar translations.
     */
    private function getSuggestions(LocaleEntity $locale, TranslatableEntity $translatable): array
    {
        $result = [];
        $connection = $this->app->make(EntityManager::class)->getConnection();
        $maxSuggestions = (int) self::MAX_SUGGESTIONS;
        $translatableTextLength = mb_strlen($translatable->getText());
        $rs = $connection->executeQuery(
            <<<EOT
SELECT DISTINCT
    CommunityTranslationTranslatables.text,
    CommunityTranslationTranslations.text0,
    MATCH(CommunityTranslationTranslatables.text) AGAINST (:search IN NATURAL LANGUAGE MODE) AS relevance
FROM
    CommunityTranslationTranslations
    INNER JOIN CommunityTranslationTranslatables
        ON CommunityTranslationTranslations.translatable = CommunityTranslationTranslatables.id
        AND 1 = CommunityTranslationTranslations.current
        AND :locale = CommunityTranslationTranslations.locale
WHERE
    CommunityTranslationTranslatables.id <> :currentTranslatableID
    AND CHAR_LENGTH(CommunityTranslationTranslatables.text) BETWEEN :minLength AND :maxLength
HAVING
    relevance > 0
ORDER BY
    relevance DESC,
    text ASC
LIMIT
    0, {$maxSuggestions}
EOT
            ,
            [
                'search' => $translatable->getText(),
                'locale' => $locale->getID(),
                'currentTranslatableID' => $translatable->getID(),
                'minLength' => (int) floor($translatableTextLength * 0.75),
                'maxLength' => (int) ceil($translatableTextLength * 1.33),
            ]
        );
        while (($row = $rs->fetchAssociative()) !== false) {
            $result[] = [
                'source' => $row['text'],
                'translation' => $row['text0'],
            ];
        }

        return $result;
    }

    /**
     * Search the glossary entries to be displayed when translating a string in a specific locale.
     */
    private function getGlossaryTerms(LocaleEntity $locale, TranslatableEntity $translatable): array
    {
        $result = [];
        $connection = $this->app->make(EntityManager::class)->getConnection();
        $maxGlossaryEntries = (int) self::MAX_GLOSSARY_ENTRIES;
        $rs = $connection->executeQuery(
            <<<EOT
SELECT
    CommunityTranslationGlossaryEntries.id,
    CommunityTranslationGlossaryEntries.term,
    CommunityTranslationGlossaryEntries.type,
    CommunityTranslationGlossaryEntries.comments AS commentsE,
    CommunityTranslationGlossaryEntriesLocalized.translation,
    CommunityTranslationGlossaryEntriesLocalized.comments AS commentsEL,
    MATCH(CommunityTranslationGlossaryEntries.term) AGAINST (:search IN NATURAL LANGUAGE MODE) AS relevance
FROM
    CommunityTranslationGlossaryEntries
    LEFT JOIN CommunityTranslationGlossaryEntriesLocalized
        ON CommunityTranslationGlossaryEntries.id = CommunityTranslationGlossaryEntriesLocalized.entry
        AND :locale = CommunityTranslationGlossaryEntriesLocalized.locale
HAVING
    relevance > 0
ORDER BY
    relevance DESC,
    CommunityTranslationGlossaryEntries.term ASC
LIMIT
    0, {$maxGlossaryEntries}

EOT
            ,
            [
                'search' => $translatable->getText(),
                'locale' => $locale->getID(),
            ]
        );
        while (($row = $rs->fetchAssociative()) !== false) {
            $result[] = [
                'id' => (int) $row['id'],
                'term' => $row['term'],
                'type' => $row['type'],
                'termComments' => $row['commentsE'],
                'translation' => (string) $row['translation'],
                'translationComments' => (string) $row['commentsEL'],
            ];
        }

        return $result;
    }
}
