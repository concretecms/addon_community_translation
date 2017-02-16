<?php
namespace CommunityTranslation\Api;

use Concrete\Core\Database\Connection\Connection;

class Token
{
    /**
     * The API Token dictionary.
     *
     * @var string
     */
    const DICTIONARY = 'abcefghijklmnopqrstuvwxyz1234567890_-.';

    /**
     * The API Token length.
     *
     * @var int
     */
    const LENGTH = 32;

    /**
     * A regular expression that newly generated tokens must satisfy.
     *
     * @var string
     */
    const TOKEN_GENERATION_REGEX = '/^[a-zA-Z0-9].*[a-zA-Z0-9]$/';

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    protected $insertQuery;

    /**
     * @var \Doctrine\DBAL\Driver\Statement
     */
    protected $searchQuery;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->insertQuery = $this->connection->prepare('insert ignore into CommunityTranslationGeneratedApiTokens (token) values (?)');
        $this->searchQuery = $this->connection->prepare('select token from CommunityTranslationGeneratedApiTokens where token = ? limit 1');
    }

    /**
     * Generate a new API token.
     *
     * @return string
     */
    public function generate()
    {
        $pickChars = str_repeat(static::DICTIONARY, static::LENGTH);
        for (; ;) {
            $value = substr(str_shuffle($pickChars), 0, static::LENGTH);
            if (preg_match(static::TOKEN_GENERATION_REGEX, $value)) {
                $this->insertQuery->execute([$value]);
                if ($this->insertQuery->rowCount() === 1) {
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * Check if a token has been generated.
     *
     * @param string $token
     *
     * @return bool
     */
    public function isGenerated($token)
    {
        $result = false;
        $token = (string) $token;
        if ($token !== '') {
            $this->searchQuery->execute([$token]);
            $row = $this->searchQuery->fetch();
            $this->searchQuery->closeCursor();
            if ($row !== false) {
                $result = true;
            }
        }

        return $result;
    }
}
