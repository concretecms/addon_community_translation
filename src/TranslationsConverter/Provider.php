<?php

declare(strict_types=1);

namespace CommunityTranslation\TranslationsConverter;

use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

final class Provider
{
    /**
     * The registered converters.
     *
     * @var \CommunityTranslation\TranslationsConverter\ConverterInterface[]
     */
    private array $converters = [];

    /**
     * Register a converter.
     *
     * @return $this
     */
    public function register(ConverterInterface $converter): self
    {
        $this->converters[$converter->getHandle()] = $converter;

        return $this;
    }

    /**
     * Check if a converter handle is registered.
     */
    public function isRegistered(string $handle): bool
    {
        return isset($this->converters[$handle]);
    }

    /**
     * Get a converter given its handle.
     */
    public function getByHandle(string $handle): ?ConverterInterface
    {
        return $this->converters[$handle] ?? null;
    }

    /**
     * Get the converters that support a specific file extension.
     *
     * @return \CommunityTranslation\TranslationsConverter\ConverterInterface[]
     */
    public function getByFileExtension(string $fileExtension): array
    {
        $fileExtension = ltrim($fileExtension, '.');
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
     * @return \CommunityTranslation\TranslationsConverter\ConverterInterface[]
     */
    public function getRegisteredConverters()
    {
        $result = $this->converters;
        $comparer = new Comparer();
        usort(
            $result,
            static function (ConverterInterface $a, ConverterInterface $b) use ($comparer): int {
                return $comparer->compare($a->getName(), $b->getName());
            }
        );

        return $result;
    }
}
