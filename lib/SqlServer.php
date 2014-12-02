<?php

namespace PeachySQL;

use PeachySQL\QueryBuilder\Insert;

/**
 * Implements the standard PeachySQL features for SQL Server (using SQLSRV extension)
 * 
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class SqlServer extends PeachySql
{
    /**
     * Option key for specifying the table's ID column (used to retrieve insert IDs)
     */
    const OPT_IDCOL = "idCol";

    /**
     * A SQLSRV connection resource
     * @var resource
     */
    private $connection;

    /**
     * Default SQL Server-specific options
     * @var array
     */
    private $sqlServerOptions = [
        self::OPT_IDCOL => null,
    ];

    /**
     * @param resource $connection A SQLSRV connection resource
     * @param array $options Options used when querying the database
     */
    public function __construct($connection, array $options = [])
    {
        $this->setConnection($connection);
        $this->setOptions($options);
    }

    /**
     * Easily switch to a different SQL Server database connection
     * @param resource $connection
     */
    public function setConnection($connection)
    {
        if (!is_resource($connection) || get_resource_type($connection) !== 'SQL Server Connection') {
            throw new \InvalidArgumentException('Connection must be a SQL Server Connection resource');
        }

        $this->connection = $connection;
    }

    /**
     * Set options used to select, insert, update, and delete from the database
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->sqlServerOptions, $this->options);
        parent::setOptions($options);
    }

    /**
     * Begins a SQLSRV transaction
     * @throws SqlException if an error occurs
     */
    public function begin()
    {
        if (!sqlsrv_begin_transaction($this->connection)) {
            throw new SqlException("Failed to begin transaction", sqlsrv_errors());
        }
    }

    /**
     * Commits a transaction begun with begin()
     * @throws SqlException if an error occurs
     */
    public function commit()
    {
        if (!sqlsrv_commit($this->connection)) {
            throw new SqlException("Failed to commit transaction", sqlsrv_errors());
        }
    }

    /**
     * Rolls back a transaction begun with begin()
     * @throws SqlException if an error occurs
     */
    public function rollback()
    {
        if (!sqlsrv_rollback($this->connection)) {
            throw new SqlException("Failed to roll back transaction", sqlsrv_errors());
        }
    }

    /**
     * Executes a single SQL Server query
     *
     * @param string $sql
     * @param array  $params Values to bind to placeholders in the query string
     * @return SqlResult
     * @throws SqlException if an error occurs
     */
    public function query($sql, array $params = [])
    {
        if (!$stmt = sqlsrv_query($this->connection, $sql, $params)) {
            throw new SqlException("Query failed", sqlsrv_errors(), $sql, $params);
        }

        $rows = [];
        $affected = 0;

        do {
            // get any selected rows
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }

            $affectedRows = sqlsrv_rows_affected($stmt);

            if ($affectedRows > 0) {
                $affected += $affectedRows;
            }
        } while ($nextResult = sqlsrv_next_result($stmt));

        if ($nextResult === false) {
            throw new SqlException("Failed to get next result", sqlsrv_errors(), $sql, $params);
        }

        sqlsrv_free_stmt($stmt);
        return new SqlResult($rows, $affected, $sql);
    }

    /**
     * Inserts one or more rows into the table. If multiple rows are inserted 
     * (via nested arrays) an array of insert IDs will be passed to the callback. 
     * If inserting a single row with a flat array of values the insert ID will 
     * instead be passed as an integer.
     * 
     * @param string[] $columns  The columns to be inserted into. E.g. ["Username", "Password"].
     * @param array    $values   A flat array of values (to insert one row), or an array containing 
     *                           one or more subarrays (to bulk-insert multiple rows).
     *                           E.g. ["user", "pass"] or [ ["user1", "pass1"], ["user2", "pass2"] ].
     * @return int|int[]
     */
    public function insert(array $columns, array $values)
    {
        $query = Insert::buildQuery($this->options[self::OPT_TABLE], $columns, $this->options[self::OPT_COLUMNS], $values, $this->options[self::OPT_IDCOL]);
        $result = $this->query($query["sql"], $query["params"]);
        $rows = $result->getAll(); // contains any insert IDs

        if ($query['isBulk']) {
            return array_map(function ($row) { return $row["RowID"]; }, $rows);
        } else {
            return empty($rows) ? 0 : $rows[0]["RowID"]; // if no insert ID, return zero for consistency with mysqli
        }
    }
}
