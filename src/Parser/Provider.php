<?php

declare(strict_types=1);

namespace CommunityTranslation\Parser;

use Concrete\Core\Application\Application;
use Concrete\Core\Error\UserMessageException;

defined('C5_EXECUTE') or die('Access Denied.');

class Provider
{
    /**
     * The application object.
     */
    protected Application $app;

    /**
     * The registered parsers.
     *
     * @var \CommunityTranslation\Parser\ParserInterface[]
     */
    private array $parsers = [];

    /**
     * @var string[]
     */
    private array $parsersToBeBuilt = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register a parser by its fully-qualified class name.
     *
     * @return $this
     */
    public function registerParserClass(string $parserClass): self
    {
        $this->parsersToBeBuilt[] = $parserClass;

        return $this;
    }

    /**
     * Register a parser instance.
     *
     * @return $this
     */
    public function registerParserInstance(ParserInterface $parser): self
    {
        $this->parsers[] = $parser;

        return $this;
    }

    /**
     * Get the list of registered parsers.
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \CommunityTranslation\Parser\ParserInterface[]
     */
    public function getRegisteredParsers(): array
    {
        while ($this->parsersToBeBuilt !== []) {
            $className = $this->parsersToBeBuilt[0];
            $instance = $this->app->make($className);
            if (!($instance instanceof ParserInterface)) {
                throw new UserMessageException(t(/*i18n: %1$s is the name of a PHP class, %2$s is the name of a PHP interface*/'%1$s does not implement %2$s', $className, ParserInterface::class));
            }
            $this->parsers[] = $instance;
            array_splice($this->parsersToBeBuilt, 0, 1);
        }

        return $this->parsers;
    }
}
