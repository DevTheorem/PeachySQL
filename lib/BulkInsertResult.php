<?php

declare(strict_types=1);

namespace PeachySQL;

/**
 * Object returned when performing bulk insert queries
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class BulkInsertResult
{
    private $ids;
    private $affected;
    private $queryCount;

    public function __construct(array $ids, int $affected, int $queryCount = 1)
    {
        $this->ids = $ids;
        $this->affected = $affected;
        $this->queryCount = $queryCount;
    }

    /**
     * Returns the IDs of the inserted rows
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * Returns the number of affected rows
     */
    public function getAffected(): int
    {
        return $this->affected;
    }

    /**
     * Returns the number of individual queries used to perform the bulk insert
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }
}
