<?php

namespace Ellephanty\Model;

use Ellephanty\Model\BaseQueryBuilder;

class QueryBuilder extends BaseQueryBuilder
{

    public function where(array $where)
    {
        $this->wheres = array_merge($this->wheres ? $this->wheres : [], $where);
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function with($relations)
    {
        $this->with = is_array($relations)
            ? $relations
            : [$relations];

        return $this;
    }

    /**
     * @param string $column
     */
    public function whereIn($column, array $values)
    {
        $this->whereIns[$column] = $values;
        return $this;
    }

    public function orderBy($column, $order = 'ASC')
    {
        $this->orderBy = [$column, $order];
        return $this;
    }
}
