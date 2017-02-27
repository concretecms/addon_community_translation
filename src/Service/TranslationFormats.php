<?php
namespace CommunityTranslation\Service;

use Punic\Comparer;

class TranslationFormats
{
    /**
     * JED format.
     *
     * @var string
     */
    const FORMAT_JED = 'Jed';

    /**
     * JSON Dictionary format.
     *
     * @var string
     */
    const FORMAT_JSONDICTIONARY = 'JsonDictionary';

    /**
     * Gettext MO format.
     *
     * @var string
     */
    const FORMAT_MO = 'Mo';

    /**
     * PHP Array.
     *
     * @var string
     */
    const FORMAT_PHPARRAY = 'PhpArray';

    /**
     * Gettext PO format.
     *
     * @var string
     */
    const FORMAT_PO = 'Po';

    /**
     * Get the list of available formats and their names.
     *
     * @return array
     */
    public function getList()
    {
        $list = [
            self::FORMAT_JED => t('JED format'),
            self::FORMAT_JSONDICTIONARY => t('JSON Dictionary'),
            self::FORMAT_MO => t('Gettext MO format'),
            self::FORMAT_PHPARRAY => t('PHP Array'),
            self::FORMAT_PO => t('Gettext PO format'),
        ];
        (new Comparer())->sort($list, true);

        return $list;
    }

    /**
     * Get the file extension for specific format.
     *
     * @param string $format One of the FORMAT_... constants
     *
     * @return string|null
     */
    public function getFileExtension($format)
    {
        switch ($format) {
            case self::FORMAT_JED:
                return 'jed';
            case self::FORMAT_JSONDICTIONARY:
                return 'json';
            case self::FORMAT_MO:
                return 'mo';
            case self::FORMAT_PHPARRAY:
                return 'php';
            case self::FORMAT_PO:
                return 'po';
        }

        return null;
    }

    /**
     * Get the file format (one of the FORMAT_... constants) given a file extension.
     *
     * @param string $fileExtension
     *
     * @return string|null
     */
    public function getFormatFromFileExtension($fileExtension)
    {
        $fileExtension = ltrim(strtolower($fileExtension), '.');
        switch ($fileExtension) {
            case 'jed':
                return self::FORMAT_JED;
            case 'json':
                return self::FORMAT_JSONDICTIONARY;
            case 'mo':
                return self::FORMAT_MO;
            case 'php':
                return self::FORMAT_PHPARRAY;
            case 'po':
                return self::FORMAT_PO;
        }

        return null;
    }
}
