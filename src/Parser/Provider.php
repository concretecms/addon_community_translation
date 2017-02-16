<?php
namespace CommunityTranslation\Parser;

use CommunityTranslation\UserException;
use Concrete\Core\Application\Application;

class Provider
{
    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * The registered parsers.
     *
     * @var [ParserInterface|string]
     */
    protected $parsers;

    /**
     * The registered parsers.
     *
     * @var ParserInterface[]
     */
    protected $parserInstances;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->parsers = [];
        $this->parserInstances = null;
    }

    /**
     * Register a parser.
     *
     * @param ParserInterface|string $parser
     */
    public function register($parser)
    {
        $this->parsers[] = $parser;
        $this->parserInstances = null;
    }

    /**
     * Get the list of registered parsers.
     *
     * @throws UserException
     *
     * @return ParserInterface[]
     */
    public function getRegisteredParsers()
    {
        if ($this->parserInstances === null) {
            $result = [];
            foreach ($this->parsers as $parser) {
                if ($parser instanceof ParserInterface) {
                    $result[] = $parser;
                } else {
                    $instance = $this->app->make($parser);
                    if (!($instance instanceof ParserInterface)) {
                        throw new UserException(t(/*i18n: %1$s is the name of a PHP class, %2$s is the name of a PHP interface*/'%1$s does not implement %2$s', $parser, ParserInterface::class));
                    }
                    $result[] = $instance;
                }
            }
            $this->parserInstances = $result;
        } else {
            $result = $this->parserInstances;
        }

        return $result;
    }
}
