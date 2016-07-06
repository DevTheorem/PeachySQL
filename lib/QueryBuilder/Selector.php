<?php

namespace PeachySQL\QueryBuilder;

use PeachySQL\BaseOptions;

class Selector
{
    private $query;
    private $options;

    private $where = [];
    private $escapeWhere = true;
    private $orderBy = [];
    private $escapeOrderBy = true;
    private $limit;
    private $offset;

    /**
     * @param string $query
     * @param BaseOptions $options
     */
    public function __construct($query, BaseOptions $options)
    {
        $this->query = $query;
        $this->options = $options;
    }

    /**
     * @param array $filter
     * @param bool $escapeColumns
     * @return self
     * @throws \Exception if called more than once
     */
    public function where(array $filter, $escapeColumns = false)
    {
        if ($this->where !== []) {
            throw new \Exception('where method can only be called once');
        }

        $this->where = $filter;
        $this->escapeWhere = $escapeColumns;
        return $this;
    }

    /**
     * @param array $sort
     * @param bool $escapeColumns
     * @return self
     * @throws \Exception if called more than once
     */
    public function orderBy(array $sort, $escapeColumns = false)
    {
        if ($this->orderBy !== []) {
            throw new \Exception('orderBy method can only be called once');
        }

        $this->orderBy = $sort;
        $this->escapeOrderBy = $escapeColumns;
        return $this;
    }

    /**
     * @param int $page
     * @param int $pageSize
     * @param int $maxPageSize
     * @return self
     * @throws \Exception if page or pageSize are invalid
     */
    public function paginate($page, $pageSize, $maxPageSize = 1000)
    {
        if ($page < 1 || $pageSize < 1) {
            throw new \Exception('Page and page size must be positive');
        } elseif ($pageSize > $maxPageSize) {
            throw new \Exception("Page size cannot be greater than {$maxPageSize}");
        }

        $this->limit = $pageSize;
        $this->offset = ($page - 1) * $pageSize;
        return $this;
    }

    /**
     * @return SqlParams
     */
    public function getSqlParams()
    {
        $select = new Select($this->options);
        $where = $select->buildWhereClause($this->where, $this->escapeWhere);
        $orderBy = $select->buildOrderByClause($this->orderBy, $this->escapeOrderBy);
        $sql = $this->query . $where->getSql() . $orderBy;

        if ($this->limit !== null && $this->offset !== null) {
            $sql .= ' ' . $select->buildPagination($this->limit, $this->offset);
        }

        return new SqlParams($sql, $where->getParams());
    }
}