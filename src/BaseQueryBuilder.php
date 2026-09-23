<?php

namespace Ellephanty\Model;

use Ellephanty\Model\Model;

class BaseQueryBuilder
{
    protected $model;
    protected $wheres = [];
    protected $with = [];
    protected $limit;
    protected $offset;
    protected $syntax;
    protected $orderBy;
    protected $attributes = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->syntax = include __DIR__ . '/../config/syntaxis.php';
    }

    public function findAll($options = [])
    {
        $query = $this->buildQuery($options);

        $stmt = Model::connection()->prepare($query);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($this->with)) {
            $result = $this->eagerLoad($result);
        }

        return $this->model->newCollection($result);
    }

    protected function eagerLoad($rows)
    {
        foreach ($this->with as $name => $callback) {
            if (!method_exists($this->model, $name)) {
                continue;
            }

            $relation = $this->model->$name()->setName($name);

            $rows = $relation->eagerLoad($rows, $callback);
        }

        return $rows;
    }

    public function findOne($options = [])
    {
        $query = $this->buildQuery($options);

        $stmt = $this->model->connection()->prepare($query);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return $this->model->entity($result);
    }

    protected function buildQuery($options = [])
    {
        $query = $this->getQueryTemplate();

        $query = $this->buildAttributes($query, $options);
        $query = $this->buildWhere($query);
        $query = $this->buildOrder($query, $options);
        $query = $this->buildDistinct($query, $options);
        $query = $this->buildLimit($query);
        $query = $this->buildOffset($query);

        return $this->normalizeQuery($query);
    }

    protected function getQueryTemplate()
    {
        switch (getenv('DB_DSN')) {
            case 'dblib':
                return "SELECT <limit> <distinct> <attributes>
                    FROM {$this->model->table()}
                    WHERE <where>
                    <order>
                    <offset>";

            case 'mysql':
                return "SELECT <distinct> <attributes>
                    FROM {$this->model->table()}
                    WHERE <where>
                    <order>
                    <limit>
                    <offset>";

            default:
                throw new \Exception(
                    'No se ha configurado un driver de base de datos válido en DB_DSN.'
                );
        }
    }

    protected function buildAttributes($query, $options)
    {
        $attributes = isset($options['attributes'])
            ? $options['attributes']
            : $this->attributes;

        if (empty($attributes)) {
            return str_replace(
                '<attributes>',
                '*',
                $query
            );
        }

        foreach ($attributes as $key => $attribute) {
            if (is_array($attribute)) {
                $attributes[$key] =
                    $attribute[0] . ' AS ' . $attribute[1];
            }
        }

        return str_replace(
            '<attributes>',
            implode(', ', $attributes),
            $query
        );
    }

    protected function buildWhere($query)
    {
        $conditions = $this->buildConditions();

        if (!empty($this->whereHas)) {
            foreach ($this->whereHas as $whereHas) {
                $condition = $this->buildWhereHas(
                    $whereHas['relation'],
                    $whereHas['callback']
                );

                if (!empty($conditions)) {
                    $condition = 'AND ' . $condition;
                }

                $conditions[] = $condition;
            }
        }

        if (empty($conditions)) {
            return str_replace(
                ' WHERE <where>',
                '',
                $query
            );
        }

        return str_replace(
            '<where>',
            implode(' ', $conditions),
            $query
        );
    }

    protected function buildOrder($query, $options)
    {
        if (isset($options['order'])) {
            $this->orderBy = $options['order'][0];
        }

        if (!isset($this->orderBy)) {
            return str_replace(
                '<order>',
                '',
                $query
            );
        }

        return str_replace(
            '<order>',
            ' ORDER BY ' .
                $this->orderBy[0] .
                ' ' .
                $this->orderBy[1],
            $query
        );
    }

    protected function buildDistinct($query, $options)
    {
        $distinct = isset($options['distinct']) &&
            $options['distinct'] == true;

        return str_replace(
            '<distinct>',
            $distinct ? 'DISTINCT' : '',
            $query
        );
    }

    protected function buildLimit($query)
    {
        if (!isset($this->limit)) {
            return str_replace(
                '<limit>',
                '',
                $query
            );
        }

        $limit = $this->syntax[getenv('DB_DSN')]['LIMIT'];

        return str_replace(
            '<limit>',
            $limit . ' ' . $this->limit,
            $query
        );
    }

    protected function buildOffset($query)
    {
        if (!isset($this->offset)) {
            return str_replace(
                '<offset>',
                '',
                $query
            );
        }

        $offset = $this->offset;

        switch (getenv('DB_DSN')) {
            case 'dblib':
                $offsetQuery = "OFFSET {$offset} ROWS";

                if (isset($this->limit)) {
                    $offsetQuery .=
                        " FETCH NEXT {$this->limit} ROWS ONLY";
                }

                break;

            case 'mysql':
                $offsetQuery = "OFFSET {$offset}";

                break;

            default:
                throw new \Exception(
                    'No se ha configurado un driver de base de datos válido en DB_DSN.'
                );
        }

        return str_replace(
            '<offset>',
            $offsetQuery,
            $query
        );
    }

    protected function normalizeQuery($query)
    {
        return trim(
            preg_replace('/\s+/', ' ', $query)
        );
    }

    protected function buildWhereHas($relationName, $callback = null)
    {
        if (!method_exists($this->model, $relationName)) {
            throw new \Exception(
                "The relation {$relationName} does not exist in " .
                    get_class($this->model)
            );
        }

        $relation = $this->model->$relationName();

        $relatedModelClass = $relation->model();

        $relatedModel = new $relatedModelClass();

        $relatedBuilder = $relatedModelClass::query();

        if ($callback) {
            call_user_func(
                $callback,
                $relatedBuilder
            );
        }

        $conditions = [];

        $relatedTable = $relatedModel->table();
        $parentTable = $this->model->table();

        $conditions[] =
            "{$relatedTable}.{$relation->foreignKey()} = " .
            "{$parentTable}.{$relation->localKey()}";

        $relatedConditions = $relatedBuilder->getConditions();

        foreach ($relatedConditions as $condition) {
            $conditions[] = $condition;
        }

        return "EXISTS (
            SELECT 1
            FROM {$relatedTable}
            WHERE " . implode(' AND ', $conditions) . "
        )";
    }

    protected function buildConditions($wheres = null)
    {
        if ($wheres === null) {
            $wheres = $this->wheres;
        }

        $conditions = [];

        foreach ($wheres as $where) {
            $condition = $this->buildWhereCondition($where);

            if ($condition === null) {
                continue;
            }

            $boolean = isset($where['boolean'])
                ? $where['boolean']
                : 'AND';

            if (!empty($conditions)) {
                $condition = $boolean . ' ' . $condition;
            }

            $conditions[] = $condition;
        }

        return $conditions;
    }

    protected function buildWhereCondition($where)
    {
        switch ($where['type']) {
            case 'where':
                return $this->buildSimpleWhereCondition($where);

            case 'whereIn':
                return $this->buildWhereInCondition($where);

            case 'group':
                return $this->buildGroupCondition($where);

            default:
                throw new \Exception(
                    "Unknown where type: {$where['type']}"
                );
        }
    }

    protected function buildSimpleWhereCondition($where)
    {
        $column = $where['column'];

        if (isset($where['conditions'])) {
            $conditions = [];

            foreach ($where['conditions'] as $operator => $value) {
                $operator = strtolower($operator);

                if ($operator === 'length') {
                    $conditions[] =
                        "LEN($column) = " . intval($value);

                    continue;
                }

                if ($operator === 'numerico') {
                    if ($value) {
                        $conditions[] =
                            "$column NOT LIKE '%[^0-9]%'";
                    }

                    continue;
                }

                $conditions[] = $this->buildOperatorCondition(
                    $column,
                    $operator,
                    $value
                );
            }

            if (empty($conditions)) {
                return null;
            }

            return implode(' AND ', $conditions);
        }

        return $this->buildOperatorCondition(
            $column,
            $where['operator'],
            $where['value']
        );
    }

    protected function buildGroupCondition($where)
    {
        $conditions = $this->buildConditions(
            $where['wheres']
        );

        if (empty($conditions)) {
            return null;
        }

        return '(' . implode(' ', $conditions) . ')';
    }

    protected function formatConditions($conditions)
    {
        $result = [];

        foreach ($conditions as $index => $condition) {
            if ($index === 0) {
                $result[] = $condition['condition'];
                continue;
            }

            $result[] =
                $condition['boolean'] . ' ' .
                $condition['condition'];
        }

        return $result;
    }

    protected function buildColumnConditions($column, $whereValue)
    {
        $conditions = [];

        if (!is_array($whereValue)) {
            $conditions[] = $this->buildSimpleCondition(
                $column,
                $whereValue
            );

            return $conditions;
        }

        if (isset($whereValue['length'])) {
            $conditions[] =
                "LEN($column) = " .
                intval($whereValue['length']);
        }

        if (!empty($whereValue['numerico'])) {
            $conditions[] =
                "$column NOT LIKE '%[^0-9]%'";
        }

        $conditions = array_merge(
            $conditions,
            $this->buildOperatorConditions(
                $column,
                $whereValue
            )
        );

        return $conditions;
    }

    protected function buildSimpleCondition($column, $value)
    {
        if (is_int($value)) {
            return "$column = $value";
        }

        return "$column = '" .
            addslashes($value) .
            "'";
    }

    protected function buildOperatorCondition(
        $column,
        $operator,
        $value
    ) {
        $operator = strtoupper($operator);

        $operators = [
            '=',
            '>',
            '<',
            '>=',
            '<=',
            '<>',
            'LIKE',
            '!='
        ];

        if (!in_array($operator, $operators)) {
            throw new \Exception(
                "Unsupported operator: {$operator}"
            );
        }

        if (is_null($value)) {
            if ($operator === '=') {
                return "{$column} IS NULL";
            }

            if ($operator === '!=' || $operator === '<>') {
                return "{$column} IS NOT NULL";
            }
        }

        if (is_int($value) || is_float($value)) {
            return "{$column} {$operator} {$value}";
        }

        return "{$column} {$operator} '" .
            addslashes($value) .
            "'";
    }

    protected function buildWhereInCondition($where)
    {
        $values = $this->cleanWhereInValues(
            $where['values']
        );

        if (empty($values)) {
            return null;
        }

        return $where['column'] .
            ' IN (' .
            implode(', ', $values) .
            ')';
    }

    public function getWheres()
    {
        return $this->wheres;
    }

    protected function cleanWhereInValues($values)
    {
        $cleanValues = [];

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $cleanValues[] = $value;
                continue;
            }

            if (is_string($value)) {
                $cleanValues[] =
                    "'" . addslashes($value) . "'";
                continue;
            }

            if (is_null($value)) {
                $cleanValues[] = 'NULL';
            }
        }

        return $cleanValues;
    }

    public function getConditions()
    {
        return $this->buildConditions();
    }

    public function query()
    {
        return $this->buildQuery();
    }
}
