<?php

namespace CommunityTranslation\TranslationsConverter;

use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;
use Punic\Comparer;

class Provider
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The registered converters.
     *
     * @var ConverterInterface[]
     */
    protected $converters;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->converters = [];
    }

    /**
     * Register a converter.
     *
     * @param ConverterInterface $converter
     */
    public function register(ConverterInterface $converter)
    {
        $this->converters[$converter->getHandle()] = $converter;
    }

    /**
     * Check if a converter handle is registered.
     *
     * @param string $handle
     *
     * @return bool
     */
    public function isRegistered($handle)
    {
        return isset($this->converters[$handle]);
    }

    /**
     * Get a converter given its handle.
     *
     * @param string $handle
     *
     * @return ConverterInterface|null
     */
    public function getByHandle($handle)
    {
        return isset($this->converters[$handle]) ? $this->converters[$handle] : null;
    }

    /**
     * Get the converter given a file extension.
     *
     * @param string $fileExtension
     *
     * @return ConverterInterface[]
     */
    public function getByFileExtension($fileExtension)
    {
        $fileExtension = ltrim($fileExtension);
        $result = [];
        foreach ($this->converters as $converter) {
            if (strcasecmp($fileExtension, $converter->getFileExtension()) === 0) {
                $result[] = $converter;
            }
        }

        return $result;
    }

    /**
     * Get the list of registered converters.
     *
     * @throws UserMessageException
     *
     * @return ConverterInterface[]
     */
    public function getRegisteredConverters()
    {
        $result = $this->converters;
        $comparer = new Comparer();
        usort($result, function (ConverterInterface $a, ConverterInterface $b) use ($comparer) {
            return $comparer->compare($a->getName(), $b->getName());
        });

        return $result;
    }
}
