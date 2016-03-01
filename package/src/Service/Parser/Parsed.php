<?php
namespace Concrete\Package\CommunityTranslation\Src\Service\Parser;

use Concrete\Package\CommunityTranslation\Src\Locale\Locale;
use Gettext\Translations;

class Parsed
{
    /**
     * @var Translations|null
     */
    protected $pot;

    /**
     * @var array(array(Locale, Translations))
     */
    protected $po;

    public function __construct()
    {
        $this->pot = null;
        $this->po = array();
    }

    /**
     * @param Translations $pot
     */
    public function setPot(Translations $pot)
    {
        $this->pot = $pot;
    }

    /**
     * @param bool $buildIfNotSet
     *
     * @return Translations|null
     */
    public function getPot($buildIfNotSet = false)
    {
        $result = $this->pot;
        if ($result === null && $buildIfNotSet) {
            $result = new Translations();
            $result->setLanguage('en_US');
            $mergeMethod = Translations::MERGE_ADD | Translations::MERGE_PLURAL;
            foreach ($this->po as $po) {
                foreach ($po[1] as $key => $translation) {
                    if (!$result->offsetExists($key)) {
                        $newTranslation = $result->insert($translation->getContext(), $translation->getOriginal(), $translation->getPlural());
                        foreach ($translation->getExtractedComments() as $comment) {
                            $newTranslation->addExtractedComment($comment);
                        }
                        foreach ($translation->getReferences() as $reference) {
                            $newTranslation->addReference($reference[0], $reference[1]);
                        }
                    }
                }
            }
            $this->pot = $result;
        }

        return $result;
    }

    /**
     * @param Locale $locale
     * @param Translations $po
     */
    public function setPo(Locale $locale, Translations $po)
    {
        $this->po[$locale->getID()] = array($locale, $po);
    }

    public function getPo(Locale $locale, $buildIfNotSet = false)
    {
        $localeID = $locale->getID();
        $po = isset($this->po[$localeID]) ?  $this->po[$localeID][1] : null;
        if ($po === null) {
            $po = clone $this->getPot(true);
            $po->setLanguage($locale->getID());
            $this->setPo($locale, $po);
        }

        return $po;
    }

    /**
     * @return array(Locale, Translations)
     */
    public function getPoList()
    {
        return array_values($this->po);
    }

    public function mergeWith(Parsed $other)
    {
        $mergeMethod = Translations::MERGE_ADD | Translations::MERGE_COMMENTS | Translations::MERGE_REFERENCES | Translations::MERGE_PLURAL;
        if ($other->pot !== null) {
            if ($this->pot === null) {
                $this->pot = $other->pot;
            } else {
                $this->pot->mergeWith($other->pot, $mergeMethod);
            }
        }
        foreach ($other->po as $key => $data) {
            if (isset($this->po[$key])) {
                $this->po[$key][1]->mergeWith($data[1], $mergeMethod);
            } else {
                $this->po[$key] = $data;
            }
        }
    }
}
