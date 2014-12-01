<?php

namespace PeachySQL;

use PeachySQL\QueryBuilder\Query;

/**
 * Provides reusable functionality and can be extended by database-specific classes
 *
 * @author Theodore Brown <https://github.com/theodorejb>
 */
abstract class PeachySql
{
    /**
     * Option key for specifying the table to select, insert, update, and delete from
     */
    const OPT_TABLE = "table";

    /**
     * Option key for specifying valid columns in the table. To prevent SQL injection,
     * this option must be set to generate queries which reference one or more columns.
     */
    const OPT_COLUMNS = "columns";

    /**
     * Default options
     * @var array
     */
    protected $options = [
        self::OPT_TABLE => null,
        self::OPT_COLUMNS => [],
    ];

    /** Begins a transaction */
    abstract public function begin();

    /** Commits a transaction begun with begin() */
    abstract public function commit();

    /** Rolls back a transaction begun with begin() */
    abstract public function rollback();

    /**
     * Executes a single query and passes a SqlResult object to the callback
     * @param string   $sql
     * @param array    $params
     * @param callable $callback
     * @return SqlResult|mixed The return value of the callback
     * @throws SqlException if an error occurs
     */
    abstract public function query($sql, array $params = [], callable $callback = null);

    /**
     * Inserts the specified values into the specified columns. Performs a bulk 
     * insert if $values is two-dimensional.
     * @param array $columns
     * @param array $values
     * @param callable $callback function ($ids, SqlResult $result)
     */
    abstract public function insert(array $columns, array $values, callable $callback = null);

    /**
     * Returns the current PeachySQL options.
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Allows PeachySQL options to be changed at any time
     * @param array $options
     * @throws \Exception if an option is invalid
     */
    public function setOptions(array $options)
    {
        $validKeys = array_keys($this->options);

        foreach (array_keys($options) as $key) {
            if (!in_array($key, $validKeys, true)) {
                throw new \Exception("Invalid option '$key'");
            }
        }

        $this->options = array_merge($this->options, $options);
    }

    /**
     * Selects the specified columns following the given where clause array.
     * Returns the return value of the callback.
     * @param string[] $columns  An array of columns to select (empty to select
     *                           all columns).
     * @param array    $where    An associative array of columns and values to
     *                           filter selected rows. E.g. ["id" => 3] to only
     *                           return rows where the id column is equal to 3.
     * @param callable $callback function (SqlResult $result)
     * @return array
     */
    public function select(array $columns = [], array $where = [], callable $callback = null)
    {
        if ($callback === null) {
            $callback = function (SqlResult $result) {
                return $result->getAll();
            };
        }

        $query = self::buildSelectQuery($this->options[self::OPT_TABLE], $columns, $this->options[self::OPT_COLUMNS], $where);
        return $this->query($query["sql"], $query["params"], $callback);
    }

    /**
     * Inserts a single row from an associative array of columns/values.
     * @param array    $colVals  E.g. ["Username => "user1", "Password" => "pass1"]
     * @param callable $callback function (int $insertId, SqlResult $result)
     * @return mixed The insert ID, or the return value of the callback
     */
    public function insertAssoc(array $colVals, callable $callback = null)
    {
        if ($callback === null) {
            $callback = function ($id) {
                return $id;
            };
        }

        return $this->insert(array_keys($colVals), array_values($colVals), $callback);
    }

    /**
     * Updates the specified columns and values in rows matching the where clause.
     * 
     * @param array    $set   E.g. ["Username" => "newName", "Password" => "newPass"]
     * @param array    $where E.g. ["id" => 3] to update the row where id is equal to 3
     * @param callable $callback function (SqlResult $result)
     * @return mixed The number of affected rows, or the return value of the callback
     */
    public function update(array $set, array $where, callable $callback = null)
    {
        if ($callback === null) {
            $callback = function (SqlResult $result) {
                return $result->getAffected();
            };
        }

        $query = self::buildUpdateQuery($this->options[self::OPT_TABLE], $set, $where, $this->options[self::OPT_COLUMNS]);
        return $this->query($query["sql"], $query["params"], $callback);
    }

    /**
     * Deletes columns from the table where the where clause matches.
     * Returns the return value of the callback function.
     * 
     * @param array    $where    E.g. ["id" => 3]
     * @param callable $callback function (SqlResult $result)
     * @return mixed The number of affected rows, or the return value of the callback
     */
    public function delete(array $where, callable $callback = null)
    {
        if ($callback === null) {
            $callback = function (SqlResult $result) {
                return $result->getAffected();
            };
        }

        $query = self::buildDeleteQuery($this->options[self::OPT_TABLE], $where, $this->options[self::OPT_COLUMNS]);
        return $this->query($query["sql"], $query["params"], $callback);
    }

    /**
     * Builds a selct query using the specified table name, columns, and where clause array.
     * @param  string   $tableName The name of the table to query
     * @param  string[] $columns   An array of columns to select from (all columns if empty)
     * @param  string[] $validCols An array of valid columns (to prevent SQL injection)
     * @param  array    $where     An array of columns/values to filter the select query
     * @return array    An array containing the SELECT query and bound parameters
     */
    public static function buildSelectQuery($tableName, array $columns = [], array $validCols = [], array $where = [])
    {
        Query::validateTableName($tableName);
        $whereClause = self::buildWhereClause($where, $validCols);

        if (!empty($columns)) {
            Query::validateColumns($columns, $validCols);
            $insertCols = implode(', ', $columns);
        } else {
            $insertCols = '*';
        }

        $sql = "SELECT $insertCols FROM $tableName" . $whereClause["sql"];
        return ["sql" => $sql, "params" => $whereClause["params"]];
    }

    /**
     * @param  string $tableName
     * @param  array  $where An array of columns/values to restrict the delete to.
     * @return array  An array containing the sql string and bound parameters.
     */
    public static function buildDeleteQuery($tableName, array $where, array $validCols)
    {
        Query::validateTableName($tableName);
        $whereClause = self::buildWhereClause($where, $validCols);
        $sql = "DELETE FROM $tableName" . $whereClause["sql"];
        return ["sql" => $sql, "params" => $whereClause["params"]];
    }

    /**
     * @param  string   $tableName The name of the table to update.
     * @param  array    $set       An array of columns/values to update
     * @param  array    $where     An array of columns/values to restrict the update to.
     * @param  string[] $validCols An array of valid columns
     * @return array An array containing the sql string and bound parameters.
     */
    public static function buildUpdateQuery($tableName, array $set, array $where, array $validCols)
    {
        if (empty($set) || empty($where)) {
            throw new \Exception("Set and where arrays cannot be empty");
        }

        Query::validateTableName($tableName);
        Query::validateColumns(array_keys($set), $validCols);

        $params = [];
        $sql = "UPDATE $tableName SET ";

        foreach ($set as $column => $value) {
            $sql .= "$column = ?, ";
            $params[] = $value;
        }

        $sql = substr_replace($sql, "", -2); // remove trailing comma
        $whereClause = self::buildWhereClause($where, $validCols);
        $sql .= $whereClause["sql"];
        $allParams = array_merge($params, $whereClause["params"]);

        return ["sql" => $sql, "params" => $allParams];
    }

    /**
     * @param array  $columnVals An associative array of columns and values to
     *                           filter selected rows. E.g. ["id" => 3] to only
     *                           return rows where id is equal to 3. If the value
     *                           is an array, an IN(...) clause will be used.
     * @param string[] $validCols An array of valid columns for the table
     * @return array An array containing the SQL WHERE clause and bound parameters.
     */
    private static function buildWhereClause(array $columnVals, array $validCols)
    {
        $sql = "";
        $params = [];

        if (!empty($columnVals)) {
            Query::validateColumns(array_keys($columnVals), $validCols);
            $sql .= " WHERE";

            foreach ($columnVals as $column => $value) {
                if ($value === null) {
                    $comparison = "IS NULL";
                } elseif (is_array($value) && !empty($value)) {
                    // use IN(...) syntax
                    $comparison = "IN(";

                    foreach ($value as $val) {
                        $comparison .= '?,';
                        $params[] = $val;
                    }

                    $comparison = substr_replace($comparison, ")", -1); // replace trailing comma
                } else {
                    $comparison = "= ?";
                    $params[] = $value;
                }

                $sql .= " $column $comparison AND";
            }

            $sql = substr_replace($sql, "", -4); // remove the trailing AND
        }

        return ["sql" => $sql, "params" => $params];
    }

    /**
     * Returns an associative array containing bound params and separate INSERT
     * and VALUES strings. Allows reusability across database implementations.
     * 
     * @param string   $tableName The name of the table to insert into
     * @param string[] $columns   An array of columns to insert into.
     * @param string[] $validCols An array of valid columns
     * @param array    $values    A two-dimensional array of values to insert into the columns.
     * @return array
     */
    protected static function buildInsertQueryComponents($tableName, array $columns, array $validCols, array $values)
    {
        // make sure columns and values are specified
        if (empty($columns) || empty($values[0])) {
            throw new \Exception("Columns and values to insert must be specified");
        }

        Query::validateTableName($tableName);
        Query::validateColumns($columns, $validCols);

        $insertCols = implode(', ', $columns);
        $insert = "INSERT INTO $tableName ($insertCols)";

        $bulkInsert = isset($values[0]) && is_array($values[0]);
        if (!$bulkInsert) {
            $values = [$values]; // make sure values is two-dimensional
        }

        $params = [];
        $valStr = ' VALUES';

        foreach ($values as $valArr) {
            $valStr .= ' (' . str_repeat('?,', count($valArr));
            $valStr = substr_replace($valStr, '),', -1); // replace trailing comma
            $params = array_merge($params, $valArr);
        }

        $valStr = substr_replace($valStr, '', -1); // remove trailing comma

        return [
            'insertStr' => $insert,
            'valStr'    => $valStr,
            'params'    => $params,
            'isBulk'    => $bulkInsert,
        ];
    }
}