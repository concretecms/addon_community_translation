<?php
namespace CommunityTranslation\Service;

use CommunityTranslation\Entity\Locale as LocaleEntity;
use CommunityTranslation\Entity\Package\Version as PackageVersionEntity;
use CommunityTranslation\Repository\GitRepository as GitRepositoryRepository;
use CommunityTranslation\Repository\Translatable\Comment as TranslatableCommentRepository;
use CommunityTranslation\Repository\Translatable\Place as TranslatablePlaceRepository;
use CommunityTranslation\Repository\Translation as TranslationRepository;
use CommunityTranslation\Service\User as UserService;
use CommunityTranslation\Translatable\Comment\Comment;
use CommunityTranslation\Translatable\Translatable;
use CommunityTranslation\Translation\Exporter as TranslationExporter;
use Concrete\Core\Application\Application;
use Doctrine\ORM\EntityManager;

class Editor
{
    /**
     * Maximum number of translation suggestions.
     *
     * @var int
     */
    const MAX_SUGGESTIONS = 15;

    /**
     * Maximum number of glossary terms to show in the editor.
     *
     * @var int
     */
    const MAX_GLOSSARY_ENTRIES = 15;

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * @param Application $application
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Returns the initial translations to be reviewed for the online editor, for a specific locale.
     *
     * @param LocaleEntity $locale
     *
     * @return array
     */
    public function getUnreviewedInitialTranslations(LocaleEntity $locale)
    {
        $rs = $this->app->make(TranslationExporter::class)->getUnreviewedSelectQuery($locale);

        return $this->buildInitialTranslations($locale, $rs);
    }

    /**
     * Returns the initial translations for the online editor, for a specific package version.
     *
     * @param PackageVersionEntity $packageVersion
     * @param LocaleEntity $locale
     *
     * @return array
     */
    public function getInitialTranslations(PackageVersionEntity $packageVersion, LocaleEntity $locale)
    {
        $rs = $this->app->make(TranslationExporter::class)->getPackageSelectQuery($packageVersion, $locale, false);

        return $this->buildInitialTranslations($locale, $rs);
    }

    /**
     * Builds the initial translations array.
     *
     * @param \Concrete\Core\Database\Driver\PDOStatement $rs
     *
     * @return array
     */
    protected function buildInitialTranslations(LocaleEntity $locale, \Concrete\Core\Database\Driver\PDOStatement $rs)
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
                $translations = [];
                switch ($isPlural ? $numPlurals : 1) {
                    case 6:
                        $translations[] = $row['text5'];
                        /* @noinspection PhpMissingBreakStatementInspection */
                    case 5:
                        $translations[] = $row['text4'];
                        /* @noinspection PhpMissingBreakStatementInspection */
                    case 4:
                        $translations[] = $row['text3'];
                        /* @noinspection PhpMissingBreakStatementInspection */
                    case 3:
                        $translations[] = $row['text2'];
                        /* @noinspection PhpMissingBreakStatementInspection */
                    case 2:
                        $translations[] = $row['text1'];
                        /* @noinspection PhpMissingBreakStatementInspection */
                    case 1:
                        $translations[] = $row['text0'];
                        break;
                }
                $item['translations'] = array_reverse($translations);
            }
            $result[] = $item;
        }
        $rs->closeCursor();

        return $result;
    }

    /**
     * Returns the data to be used in the editor when editing a string.
     *
     * @param LocaleEntity $locale the current editor locale
     * @param Translatable $translatable the source string that's being translated
     * @param PackageVersionEntity $packageVersion the package version where this string is used
     * @param bool $initial set to true when a string is first loaded, false after it has been saved
     *
     * @return array
     */
    public function getTranslatableData(LocaleEntity $locale, Translatable $translatable, PackageVersionEntity $packageVersion = null, $initial = false)
    {
        $result = [
            'id' => $translatable->getID(),
            'translations' => $this->getTranslations($locale, $translatable),
        ];
        if ($initial) {
            $place = ($packageVersion === null) ? null : $this->app->make(TranslatablePlaceRepository::class)->findOneBy(['packageVersion' => $packageVersion, 'translatable' => $translatable]);
            if ($place !== null) {
                $extractedComments = $place->getComments();
                $references = $this->expandReferences($place->getLocations(), $packageVersion);
            } else {
                $extractedComments = [];
                $references = [];
            }
            $result['extractedComments'] = $extractedComments;
            $result['references'] = $references;
            $result['extractedComments'] = ($place === null) ? [] : $place->getComments();
            $result['comments'] = $this->getComments($locale, $translatable);
            $result['suggestions'] = $this->getSuggestions($locale, $translatable);
            $result['glossary'] = $this->getGlossaryTerms($locale, $translatable);
        }

        return $result;
    }

    /**
     * Search all the translations associated to a translatable string.
     *
     * @param LocaleEntity $locale
     * @param Translatable $translatable
     *
     * @return array
     */
    public function getTranslations(LocaleEntity $locale, Translatable $translatable)
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
            /* @var \CommunityTranslation\Translation\Translation $translation */
            $texts = [];
            switch (($translatable->getPlural() === '') ? 1 : $numPlurals) {
                case 6:
                    $texts[] = $translation->getText5();
                    /* @noinspection PhpMissingBreakStatementInspection */
                case 5:
                    $texts[] = $translation->getText4();
                    /* @noinspection PhpMissingBreakStatementInspection */
                case 4:
                    $texts[] = $translation->getText3();
                    /* @noinspection PhpMissingBreakStatementInspection */
                case 3:
                    $texts[] = $translation->getText2();
                    /* @noinspection PhpMissingBreakStatementInspection */
                case 2:
                    $texts[] = $translation->getText1();
                    /* @noinspection PhpMissingBreakStatementInspection */
                case 1:
                default:
                    $texts[] = $translation->getText0();
                    break;
            }
            $item = [
                'id' => $translation->getID(),
                'createdOn' => $dh->formatPrettyDateTime($translation->getCreatedOn(), false, true),
                'createdBy' => $uh->format($translation->getCreatedBy()),
                'reviewed' => $translation->isReviewed(),
                'translations' => array_reverse($texts),
            ];
            if ($translation->isCurrent()) {
                $item['currentSince'] = $dh->formatPrettyDateTime($translation->isCurrentSince(), false, true);
                $result['current'] = $item;
            } else {
                $item['needReview'] = $translation->needReview();
                $result['others'][] = $item;
            }
        }

        return $result;
    }

    /**
     * Get the comments associated to a translatable strings.
     *
     * @param LocaleEntity $locale
     * @param Translatable $translatable
     */
    public function getComments(LocaleEntity $locale, Translatable $translatable, Comment $parentComment = null)
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
        $me = new \User();
        $myID = $me->isRegistered() ? (int) $me->getUserID() : null;
        foreach ($comments as $comment) {
            $result[] = [
                'id' => $comment->getID(),
                'date' => $dh->formatPrettyDateTime($comment->getPostedOn(), true, true),
                'mine' => $myID && $myID === $comment->getPostedBy(),
                'by' => $uh->format($comment->getPostedBy()),
                'text' => $comment->getText(),
                'comments' => $this->getComments($locale, $translatable, $comment),
                'isGlobal' => $comment->getLocale() === null,
            ];
        }

        return $result;
    }

    /**
     * Search for similar translations.
     *
     * @param LocaleEntity $locale
     * @param Translatable $translatable
     *
     * @return array
     */
    public function getSuggestions(LocaleEntity $locale, Translatable $translatable)
    {
        $result = [];
        $connection = $this->app->make(EntityManager::class)->getConnection();
        $rs = $connection->executeQuery(
            '
                select distinct
                    Translatables.text,
                    Translations.text0,
                    match(Translatables.text) against (:search in natural language mode) as relevance
                from
                    Translations
                    inner join Translatables on Translations.translatable = Translatables.id and 1 = Translations.current and :locale = Translations.locale
                where
                    Translatables.id <> :currentTranslatableID
                    and length(Translatables.text) between :minLength and :maxLength
                having
                    relevance > 0
                order by
                    relevance desc,
                    text asc
                limit
                    0, ' . ((int) self::MAX_SUGGESTIONS) . '
            ',
            [
                'search' => $translatable->getText(),
                'locale' => $locale->getID(),
                'currentTranslatableID' => $translatable->getID(),
                'minLength' => (int) floor(strlen($translatable->getText()) * 0.75),
                'maxLength' => (int) ceil(strlen($translatable->getText()) * 1.33),
            ]
        );
        while ($row = $rs->fetch()) {
            $result[] = [
                'source' => $row['text'],
                'translation' => $row['text0'],
            ];
        }
        $rs->closeCursor();

        return $result;
    }

    /**
     * Search the glossary entries to show when translating a string in a specific locale.
     *
     * @param LocaleEntity $locale the current editor locale
     * @param Translatable $translatable the source string that's being translated
     *
     * @return array
     */
    public function getGlossaryTerms(LocaleEntity $locale, Translatable $translatable)
    {
        $result = [];
        $connection = $this->app->make(EntityManager::class)->getConnection();
        $rs = $connection->executeQuery(
            '
                select
                    id,
                    term,
                    type,
                    comments,
                    translation,
                    comments,
                    match(term) against (:search in natural language mode) as relevance
                from
                    GlossaryEntries
                    left join GlossaryLocalizedEntries on GlossaryEntries.id = GlossaryLocalizedEntries.entry and :locale = GlossaryLocalizedEntries.locale
                having
                    relevance > 0
                order by
                    relevance desc,
                    term asc
                limit
                    0, ' . ((int) self::MAX_GLOSSARY_ENTRIES) . '
            ',
            [
                'search' => $translatable->getText(),
                'locale' => $locale->getID(),
            ]
        );
        while ($row = $rs->fetch()) {
            $result[] = [
                'id' => (int) $row['id'],
                'term' => $row['term'],
                'type' => $row['type'],
                'termComments' => $row['comments'],
                'translation' => ($row['translation'] === null) ? '' : $row['translation'],
                'translationComments' => ($row['comments'] === null) ? '' : $row['comments'],
            ];
        }
        $rs->closeCursor();

        return $result;
    }

    /**
     * Expand translatable string references by adding a link to the online repository where they are defined.
     *
     * @param string[] $references
     * @param PackageVersionEntity $packageVersion
     *
     * @return string[]
     */
    public function expandReferences(array $references, PackageVersionEntity $packageVersion)
    {
        if (empty($references)) {
            return $references;
        }
        $repositories = $this->app->make(GitRepositoryRepository::class)->findBy(['packageVersion' => $packageVersion->getHandle()]);
        $applicableRepository = null;
        $gitSubDir = '';
        if (strpos($packageVersion->getVersion(), PackageVersionEntity::DEV_PREFIX) === 0) {
            foreach ($repositories as $repository) {
                foreach ($repository->getDevBranches() as $devBranch => $packageVersion) {
                    if ($packageVersion->getVersion() === $packageVersion) {
                        $gitSubDir = 'blob/' . $devBranch . '/';
                        $applicableRepository = $repository;
                        break;
                    }
                }
                if ($applicableRepository !== null) {
                    break;
                }
            }
        } else {
            foreach ($repositories as $repository) {
                $vx = $repository->getTagsFilterExpanded();
                if ($vx !== null && version_compare($packageVersion->getVersion(), $vx['version'], $vx['operator'])) {
                    $gitSubDir = 'blob/' . $packageVersion->getVersion() . '/';
                    $applicableRepository = $repository;
                    break;
                }
            }
        }
        if ($applicableRepository === null) {
            return $references;
        }
        if (!preg_match('/^(https?:\/\/github.com\/[^?]+)\.git($|\?)/i', $applicableRepository->getURL(), $matches)) {
            return $references;
        }
        $baseURL = $matches[1] . '/' . $gitSubDir;
        if ($applicableRepository->getDirectoryToParse() !== '') {
            $baseURL .= $applicableRepository->getDirectoryToParse() . '/';
        }
        foreach ($references as $index => $reference) {
            if (!preg_match('/^\w*:\/\//', $reference)) {
                $url = $baseURL . ltrim($reference, '/');
                if (preg_match('/^(.+):(\d+)$/', $url, $m)) {
                    $url = $m[1] . '#L' . $m[2];
                }
                $references[$index] = [$url, $reference];
            }
        }

        return $references;
    }
}
