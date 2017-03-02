<?php
namespace CommunityTranslation\TranslationsConverter;

use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;
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
     * @var [ConverterInterface|string]
     */
    protected $converters;

    /**
     * The registered converters.
     *
     * @var ConverterInterface[]
     */
    protected $converterInstances;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->converters = [];
        $this->converterInstances = [];
    }

    /**
     * Register a converter.
     *
     * @param string $handle
     * @param ConverterInterface|string $converter
     */
    public function register($handle, $converter)
    {
        $this->converters[$handle] = $converter;
        unset($this->converterInstances[$handle]);
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
     * Get a handle given its handle.
     *
     * @param string $handle
     *
     * @return ConverterInterface|null
     */
    public function getByHandle($handle)
    {
        $converters = $this->getRegisteredConverters();

        return isset($converters[$handle]) ? $converters[$handle] : null;
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
        foreach ($this->getRegisteredConverters() as $handle => $converter) {
            if (strcasecmp($fileExtension, $converter->getFileExtension()) === 0) {
                $result[$handle] = $converter;
            }
        }

        return $result;
    }

    /**
     * Get the list of registered converters.
     *
     * @throws UserException
     *
     * @return ConverterInterface[]
     */
    public function getRegisteredConverters()
    {
        foreach (array_diff_key($this->converters, $this->converterInstances) as $handle => $converter) {
            if ($converter instanceof ConverterInterface) {
                $this->converterInstances[$handle] = $converter;
            } else {
                $instance = $this->app->make($converter);
                if (!($instance instanceof ConverterInterface)) {
                    throw new UserException(t(/*i18n: %1$s is the name of a PHP class, %2$s is the name of a PHP interface*/'%1$s does not implement %2$s', $converter, ConverterInterface::class));
                }
                $this->converterInstances[$handle] = $instance;
            }
        }

        $comparer = new Comparer();
        $result = $this->converterInstances;
        uasort($result, function (ConverterInterface $a, ConverterInterface $b) use ($comparer) {
            return $comparer->compare($a->getName(), $b->getName());
        });

        return $result;
    }
}
