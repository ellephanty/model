<?php

namespace Ellephanty\Model;

use Ellephanty\Model\BaseQueryBuilder;

class QueryBuilder extends BaseQueryBuilder
{
    public function where($column, $operator = null, $value = null)
    {
        return $this->addWhere(
            $column,
            $operator,
            $value,
            'AND'
        );
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->addWhere(
            $column,
            $operator,
            $value,
            'OR'
        );
    }

    public function getWheres()
    {
        return $this->wheres;
    }

    protected function addWhere(
        $column,
        $operator = null,
        $value = null,
        $boolean = 'AND'
    ) {
        // where(function ($query) {})
        if (is_callable($column)) {
            $query = new static($this->model);

            call_user_func($column, $query);

            $this->wheres[] = [
                'type' => 'group',
                'boolean' => $boolean,
                'wheres' => $query->getWheres()
            ];

            return $this;
        }

        // where(['campo' => 'valor'])
        if (is_array($column)) {
            foreach ($column as $name => $condition) {
                if (is_array($condition)) {
                    $this->wheres[] = [
                        'type' => 'where',
                        'column' => $name,
                        'conditions' => $condition,
                        'boolean' => $boolean
                    ];

                    continue;
                }

                $this->wheres[] = [
                    'type' => 'where',
                    'column' => $name,
                    'operator' => '=',
                    'value' => $condition,
                    'boolean' => $boolean
                ];
            }

            return $this;
        }

        // where('campo', 'operador', 'valor')
        $this->wheres[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator ?: '=',
            'value' => $value,
            'boolean' => $boolean
        ];

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset($offset)
    {
        $this->offset = (int) $offset;

        return $this;
    }

    public function with($relations)
    {
        if (!is_array($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $name => $callback) {
            // with('relacion')
            if (is_int($name)) {
                $this->with[$callback] = null;
                continue;
            }

            // with(['relacion' => function ($query) {}])
            $this->with[$name] = $callback;
        }

        return $this;
    }

    /**
     * @param string $column
     */
    public function whereIn($column, array $values)
    {
        $this->wheres[] = [
            'type' => 'whereIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhereIn($column, array $values)
    {
        $this->wheres[] = [
            'type' => 'whereIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'OR'
        ];

        return $this;
    }

    public function orderBy($column, $order = 'ASC')
    {
        $this->orderBy = [$column, $order];

        return $this;
    }

    public function exists()
    {
        $this->limit = 1;

        $query = $this->buildQuery([
            'attributes' => ['1']
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

        $sql = "UPDATE {$this->model->table()} SET " .
            implode(', ', $set);

        if (!empty($this->wheres)) {
            $where = [];

            foreach ($this->wheres as $condition) {
                if ($condition['type'] !== 'where') {
                    throw new \Exception(
                        'update() actualmente solo soporta condiciones where simples.'
                    );
                }

                $where[] =
                    $condition['boolean'] . ' ' .
                    $condition['column'] . ' ' .
                    $condition['operator'] . ' ?';

                $bindings[] = $condition['value'];
            }

            $where[0] = preg_replace(
                '/^AND |^OR /',
                '',
                $where[0]
            );

            $sql .= ' WHERE ' . implode(' ', $where);
        }

        $stmt = $this->model->connection()->prepare($sql);

        return $stmt->execute($bindings);
    }

    public function max($column)
    {
        $sql = "SELECT MAX($column) FROM {$this->model->table()}";

        $stmt = $this->model->connection()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function count()
    {
        $limit = $this->limit;
        $offset = $this->offset;
        $orderBy = $this->orderBy;

        $this->limit = null;
        $this->offset = null;
        $this->orderBy = null;

        $query = $this->buildQuery([
            'attributes' => ['COUNT(*)']
        ]);

        $this->limit = $limit;
        $this->offset = $offset;
        $this->orderBy = $orderBy;

        $stmt = $this->model->connection()->prepare($query);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function whereHas($relation, callable $callback = null)
    {
        $this->whereHas[] = [
            'relation' => $relation,
            'callback' => $callback
        ];

        return $this;
    }
}
