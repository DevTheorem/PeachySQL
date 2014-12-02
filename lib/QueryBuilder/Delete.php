<?php

namespace PeachySQL\QueryBuilder;

/**
 * Class used for delete query generation
 * @author Theodore Brown <https://github.com/theodorejb>
 */
class Delete extends Query
{
    /**
     * @param string   $tableName
     * @param array    $where An array of columns/values to restrict the delete to
     * @param string[] $validCols An array of valid column names
     * @return array An array containing the SQL string and bound parameters
     */
    public static function buildQuery($tableName, array $where, array $validCols)
    {
        self::validateTableName($tableName);
        $whereClause = self::buildWhereClause($where, $validCols);
        $sql = "DELETE FROM $tableName" . $whereClause['sql'];
        return ['sql' => $sql, 'params' => $whereClause['params']];
    }
}