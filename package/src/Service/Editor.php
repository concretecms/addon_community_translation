<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Concrete\Package\CommunityTranslation\Src\Translatable\Translatable;

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
        if ($initial) {
            $result['glossary'] = $this->getGlossaryTerms($locale, $translatable);
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
