<?php

namespace Ellephanty\Model;

use Ellephanty\Model\Relation;

class HasMany extends Relation
{
    public function eagerLoad(array &$rows)
    {
        $foreignKey = $this->foreignKey;
        $localKey = $this->localKey;
        $relationName = $this->name;

        $ids = array_unique(
            array_column($rows, $localKey)
        );

        if (empty($ids)) {
            return $rows;
        }

        $modelClass = $this->model();

        $relatedRows = $modelClass::query()
            ->whereIn($foreignKey, $ids)
            ->findAll();

        $map = [];

        foreach ($relatedRows as $r) {

            $key = $r[$foreignKey];

            if (!isset($map[$key])) {
                $map[$key] = [];
            }

            $map[$key][] = $r;
        }

        foreach ($rows as &$row) {

            $key = $row[$localKey];

            $row[$relationName] = isset($map[$key])
                ? $map[$key]
                : [];
        }

        return $rows;
    }
}