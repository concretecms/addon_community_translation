<?php

declare(strict_types=1);

namespace CommunityTranslation\Api;

use Concrete\Core\Database\Connection\Connection;
use Doctrine\DBAL\Statement;

defined('C5_EXECUTE') or die('Access Denied.');

final class Token
{
    /**
     * The API Token dictionary.
     *
     * @var string
     */
    private const DICTIONARY = 'abcefghijklmnopqrstuvwxyz1234567890_-.';

    /**
     * The API Token length.
     *
     * @var int
     */
    private const LENGTH = 32;

    /**
     * A regular expression that newly generated tokens must satisfy.
     *
     * @var string
     */
    private const TOKEN_GENERATION_REGEX = '/^[a-zA-Z0-9].*[a-zA-Z0-9]$/';

    private Connection $connection;

    private ?Statement $insertQuery = null;

    private ?Statement $searchQuery = null;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Generate a new API token.
     */
    public function generate(): string
    {
        $insertQuery = $this->getInsertQuery();
        $pickChars = str_repeat(self::DICTIONARY, self::LENGTH);
        for (;;) {
            $value = substr(str_shuffle($pickChars), 0, self::LENGTH);
            if (preg_match(self::TOKEN_GENERATION_REGEX, $value)) {
                if ($insertQuery->executeStatement([$value]) === 1) {
                    return $value;
                }
            }
        }
    }

    /**
     * Check if a token has been generated.
     */
    public function isGenerated(string $token): bool
    {
        if (strlen($token) !== self::LENGTH) {
            return false;
        }
        $rs = $this->getSearchQuery()->executeQuery([$token]);

        return $rs->fetchOne() !== false;
    }

    private function getInsertQuery(): Statement
    {
        if ($this->insertQuery === null) {
            $this->insertQuery = $this->connection->prepare('INSERT IGNORE INTO CommunityTranslationGeneratedApiTokens (token) VALUES (?)');
        }

        return $this->insertQuery;
    }

    private function getSearchQuery(): Statement
    {
        if ($this->searchQuery === null) {
            $this->searchQuery = $this->connection->prepare('SELECT token FROM CommunityTranslationGeneratedApiTokens WHERE token = ? LIMIT 1');
        }

        return $this->searchQuery;
    }
}
