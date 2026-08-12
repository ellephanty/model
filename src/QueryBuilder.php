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

    public function exists()
    {
        $query = $this->buildQuery([
            'attributes' => ['1'],
        ]);

        $this->limit = 1;

        $query = $this->buildQuery([
            'attributes' => ['1'],
        ]);

        $stmt = $this->model->connection()->prepare($query);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }

    public function select($columns)
    {
        $this->attributes = is_array($columns)
            ? $columns
            : func_get_args();

        return $this;
    }

    public function update(array $attributes)
    {
        $set = [];
        $bindings = [];

        foreach ($attributes as $column => $value) {
            $set[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->model->table()} SET " . implode(', ', $set);

        if (!empty($this->wheres)) {
            $where = [];

            foreach ($this->wheres as $column => $value) {
                $where[] = "{$column} = ?";
                $bindings[] = $value;
            }

            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->model->connection()->prepare($sql);

        return $stmt->execute($bindings);
    }

    public function max($column)
    {
        $stmt = $this->model->connection()->prepare("SELECT MAX($column) FROM {$this->model->table()}");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
