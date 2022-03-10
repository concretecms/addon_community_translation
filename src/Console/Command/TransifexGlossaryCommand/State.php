<?php

declare(strict_types=1);

namespace CommunityTranslation\Console\Command\TransifexGlossaryCommand;

defined('C5_EXECUTE') or die('Access Denied.');

class State
{
    public array $sourceFields = ['term' => 'setTerm', 'pos' => 'setType', 'comment' => 'setComments'];

    public array $localizedFields = ['translation' => 'text', 'comment' => 'comments'];

    public string $rxLocalizedFields;

    public array $existingLocales;

    public array $map;

    public int $numFields;

    public int $rowIndex = 0;

    /**
     * @param \CommunityTranslation\Entity\Locale[] $locales
     */
    public function __construct(array $locales)
    {
        $quotedKeys = array_map(
            static function (string $key): string {
                return preg_quote($key, '/');
            },
            array_keys($this->localizedFields)
        );
        $this->rxLocalizedFields = '/^(' . implode('|', $quotedKeys) . ')_(.+)$/';
        $existingLocales = [];
        foreach ($locales as $locale) {
            $existingLocales[$locale->getID()] = $locale;
        }
        $this->existingLocales = $existingLocales;
    }
}
