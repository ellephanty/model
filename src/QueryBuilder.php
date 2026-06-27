<?php

namespace Ellephanty\Model;

use Ellephanty\Model\BaseQueryBuilder;

class QueryBuilder extends BaseQueryBuilder
{

    public function where(array $where)
    {
        $this->wheres = $where;

        return $this;
    }

    public function with($relations)
    {
        $this->with = is_array($relations)
            ? $relations
            : [$relations];

        return $this;
    }

    public function whereIn($column, array $values)
    {
        $this->whereIns[] = [
            'column' => $column,
            'values' => $values,
        ];

        return $this;
    }
}
