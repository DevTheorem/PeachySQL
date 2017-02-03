<?php

declare(strict_types=1);

namespace PeachySQL\QueryBuilder;

use PeachySQL\BaseOptions;

/**
 * Base class used for query generation and validation
 */
class Query
{
    protected $options;

    private static $operatorMap = [
        'eq' => '=',
        'ne' => '<>',
        'lt' => '<',
        'le' => '<=',
        'gt' => '>',
        'ge' => '>=',
        'lk' => 'LIKE',
        'nl' => 'NOT LIKE',
        'nu' => 'IS NULL',
        'nn' => 'IS NOT NULL',
    ];

    public function __construct(BaseOptions $options)
    {
        $this->options = $options;
    }

    /**
     * @param string[] $columns
     * @return string[]
     */
    protected function escapeColumns(array $columns): array
    {
        return array_map([$this->options, 'escapeIdentifier'], $columns);
    }

    /**
     * @throws \Exception if a column filter is empty
     */
    public function buildWhereClause(array $columnVals): SqlParams
    {
        if (empty($columnVals)) {
            return new SqlParams('', []);
        }

        $conditions = $params = [];

        foreach ($columnVals as $column => $value) {
            $column = $this->options->escapeIdentifier($column);

            if (is_array($value) && count($value) === 0) {
                throw new \Exception("Filter conditions cannot be empty for {$column} column");
            } elseif (!is_array($value) || isset($value[0])) {
                // same as eq operator - handle below
                $value = ['eq' => $value];
            }

            foreach ($value as $shorthand => $val) {
                if (!isset(self::$operatorMap[$shorthand])) {
                    throw new \Exception("{$shorthand} is not a valid operator");
                }

                if ($val === null) {
                    if ($shorthand === 'eq') {
                        trigger_error('Use the "nu" operator to filter by null values', E_USER_DEPRECATED);
                        $conditions[] =  "{$column} IS NULL";
                    } elseif ($shorthand === 'ne') {
                        trigger_error('Use the "nn" operator to filter out null values', E_USER_DEPRECATED);
                        $conditions[] =  "{$column} IS NOT NULL";
                    } else {
                        throw new \Exception("{$shorthand} operator cannot be used with a null value");
                    }
                } elseif ($shorthand === 'nu' || $shorthand === 'nn') {
                    if ($val !== '') {
                        throw new \Exception("{$shorthand} operator can only be used with a blank value");
                    }

                    $conditions[] = $column . ' ' . self::$operatorMap[$shorthand];
                } elseif (!is_array($val)) {
                    $comparison = self::$operatorMap[$shorthand];
                    $conditions[] = "{$column} {$comparison} ?";
                    $params[] = $val;
                } elseif ($shorthand === 'eq' || $shorthand === 'ne') {
                    // use IN(...) syntax
                    $conditions[] = $column . ($shorthand === 'ne' ? ' NOT IN(' : ' IN(')
                        . str_repeat('?,', count($val) - 1) . '?)';
                    $params = array_merge($params, $val);
                } elseif ($shorthand === 'lk' || $shorthand === 'nl') {
                    foreach ($val as $condition) {
                        $conditions[] = $column . ' ' . self::$operatorMap[$shorthand] . ' ?';
                        $params[] = $condition;
                    }
                } else {
                    // it doesn't make sense to use greater than or less than operators with multiple values
                    throw new \Exception("{$shorthand} operator cannot be used with an array");
                }
            }
        }

        return new SqlParams(' WHERE ' . implode(' AND ', $conditions), $params);
    }
}
