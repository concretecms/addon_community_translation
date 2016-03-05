<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\Translatable\Translatable;
use Concrete\Package\CommunityTranslation\Src\Translatable\Comment\Comment;

class Editor implements \Concrete\Core\Application\ApplicationAwareInterface
{
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
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Returns the data to be used in the editor when editing a string.
     *
     * @param Locale $locale The current editor locale.
     * @param Translatable $translatable The source string that's being translated.
     * @param bool $initial Set to true when a string is first loaded, false after it has been saved.
     *
     * @return array
     */
    public function getTranslatableData(Locale $locale, Translatable $translatable, $initial)
    {
        $result = array();
        $result['translations'] = $this->getTranslations($locale, $translatable);
        if ($initial) {
            $result['comments'] = $this->getComments($locale, $translatable);
            $result['glossary'] = $this->getGlossaryTerms($locale, $translatable);
        }

        return $result;
    }

    /**
     * Search all the translations associated to a translatable string.
     *
     * @param Locale $locale
     * @param Translatable $translatable
     *
     * @return array
     */
    public function getTranslations(Locale $locale, Translatable $translatable)
    {
        $numPlurals = $locale->getPluralCount();

        $result = array();
        $translations = $this->app->make('community_translation/translation')->findBy(array('tTranslatable' => $translatable, 'tLocale' => $locale), array('tCreatedOn' => 'DESC'));
        foreach ($translations as $translation) {
            $texts = array();
            switch ($numPlurals) {
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
            $item = array(
                'id' => $translation->getID(),
                'created' => $translation->getCreatedOn(),
                'createdBy' => $translation->getCreatedBy(),
                'current' => $translation->isCurrent(),
                'currentSince' => $translation->isCurrentSince(),
                'reviewed' => $translation->isReviewed(),
                'texts' => array_reverse($texts),
            );
        }
        $rs->closeCursor();

        return $result;
    }

    /**
     * Get the comments associated to a translatable strings.
     *
     * @param Locale $locale
     * @param Translatable $translatable
     */
    public function getComments(Locale $locale, Translatable $translatable, Comment $parentComment = null)
    {
        $repo = $this->app->make('community_translation/translatable/comment');
        if ($parentComment === null) {
            $comments = $repo->findBy(
                array(
                    'tcTranslatable' => $translatable,
                    'tcParentComment' => null,
                    '$or' => array(
                        array('tcLocale' => $locale),
                        array('tcLocale' => null),
                    ),
                ),
                array('tcPostedOn' => 'ASC')
            );
        } else {
            $comments = $repo->findBy(
                array('tcParentComment' => $parentComment),
                array('tcPostedOn' => 'ASC')
            );
        }
        $result = array();
        foreach ($comments as $comment) {
            $result[] = array(
                'id' => $comment->getID(),
                'date' => $comment->getPostedOn(),
                'by' => $comment->getPostedBy(),
                'text' => $comment->getText(),
                'childComments' => $this->getComments($locale, $translatable, $comment),
            );
        }

        return $result;
    }

    /**
     * Search the glossary entries to show when translating a string in a specific locale.
     *
     * @param Locale $locale The current editor locale.
     * @param Translatable $translatable The source string that's being translated.
     *
     * @return array
     */
    public function getGlossaryTerms(Locale $locale, Translatable $translatable)
    {
        $result = array();
        $connection = $this->app->make('community_translation/em')->getConnection();
        $rs = $connection->executeQuery(
            '
                select
                    geID,
                    geTerm,
                    geType,
                    geComments,
                    gleTranslation,
                    gleComments,
                    match(geTerm) against (:search in natural language mode) as relevance
                from
                    GlossaryEntries
                    left join GlossaryLocalizedEntries on GlossaryEntries.geID = GlossaryLocalizedEntries.gleEntry and :locale = GlossaryLocalizedEntries.gleLocale
                having
                    relevance > 0
                order by
                    relevance desc,
                    geTerm asc
                limit
                    0, '.((int) self::MAX_GLOSSARY_ENTRIES).'
            ',
            array(
                'search' => $translatable->getText(),
                'locale' => $locale->getID(),
            )
        );
        while ($row = $rs->fetch()) {
            $item = array(
                'id' => (int) $row['geID'],
                'term' => $row['geTerm'],
                'type' => $row['geType'],
                'globalComments' => $row['geComments'],
            );
            if ($row['gleTranslation'] !== null) {
                $item['translation'] = $row['gleTranslation'];
                $item['localeComments'] = $row['gleComments'];
            }
            $result[] = $item;
        }
        $rs->closeCursor();

        return $result;
    }
}
