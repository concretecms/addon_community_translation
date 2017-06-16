<?php

namespace CommunityTranslation\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Http\Request;
use DateTime;
use IPLib\Address\AddressInterface;
use IPLib\Factory;

class IPControlLog
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Application $app
     * @param Request $request
     */
    public function __construct(Connection $connection, Request $request)
    {
        $this->connection = $connection;
        $this->request = $request;
    }

    /**
     * @var \IPLib\Address\AddressInterface|null
     */
    private $ipAddress = null;

    /**
     * @return \IPLib\Address\AddressInterface
     */
    public function getIPAddress()
    {
        if ($this->ipAddress === null) {
            $this->ipAddress = Factory::addressFromString($this->request->getClientIp());
        }

        return $this->ipAddress;
    }

    /**
     * Count a visit for a specified type.
     *
     * @param string $type The type identifier
     * @param int $count The number of visits to count [default: 1]
     * @param DateTime $dateTime The date/time to record [default: now]
     * @param AddressInterface $ipAddress The IP address to record [default: the current one]
     */
    public function addVisit($type, $count = 1, DateTime $dateTime = null, AddressInterface $ipAddress = null)
    {
        $count = (int) $count;
        if ($count > 0) {
            if ($dateTime === null) {
                $dateTime = new DateTime();
            }
            if ($ipAddress === null) {
                $ipAddress = $this->getIPAddress();
            }
            $this->connection->executeQuery(
                'insert into CommunityTranslationIPControl
                    (type, ip, dateTime, count)
                    values (?, ?, ?, ?)
                    on duplicate key update count = count + ?
                ',
                [
                    (string) $type,
                    (string) $ipAddress,
                    $dateTime->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()),
                    $count,
                    $count,
                ]
            );
        }
    }

    /**
     * Clear visits older than a specified date/time.
     *
     * @param DateTime $before
     * @param string|null $type The type identifier for the visits to be deleted. Set to null to delete logs for all types
     *
     * @return int Returns the number of deleted records
     */
    public function clearVisits(DateTime $before, $type = null)
    {
        $query = 'delete from CommunityTranslationIPControl where dateTime < ?';
        $params = [$before->format($this->connection->getDatabasePlatform()->getDateTimeFormatString())];
        if ($type !== null) {
            $query .= ' and type = ?';
            $params[] = (string) $type;
        }
        $rs = $this->connection->executeQuery($query, $params);

        return (int) $rs->rowCount();
    }

    /**
     * @param string $type The type identifier
     * @param DateTime $since From when we should count the visits
     * @param AddressInterface $ipAddress The IP address to look for [default: the current one]
     */
    public function countVisits($type, DateTime $since, AddressInterface $ipAddress = null)
    {
        if ($ipAddress === null) {
            $ipAddress = $this->getIPAddress();
        }
        $rs = $this->connection->executeQuery(
            '
                select sum(count)
                from CommunityTranslationIPControl
                where type = ?
                and ip = ?
                and dateTime >= ?
            ',
            [
                (string) $type,
                (string) $ipAddress,
                $since->format($this->connection->getDatabasePlatform()->getDateTimeFormatString()),
            ]
        );
        $result = (int) $rs->fetchColumn(0);
        $rs->closeCursor();

        return $result;
    }
}
