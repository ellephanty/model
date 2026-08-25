<?php

namespace Ellephanty\Model;

use Ellephanty\Model\Relation;

class BelongsTo extends Relation
{

    public function eagerLoad(array &$rows, $callback = null)
    {
        $foreignKey = $this->foreignKey;
        $localKey = $this->localKey;
        $relationName = $this->name;

        $ids = array_unique(array_column($rows, $foreignKey));

        if (empty($ids)) {
            return $rows;
        }

        $modelClass = $this->model();

        $query = $modelClass::query();
        
        // Aplicar callback del with()
        if ($callback) {
            call_user_func($callback, $query);
        }

        $query = $query->whereIn($localKey, $ids);
        
        $relatedRows = $query->findAll();

        $map = [];

        foreach ($relatedRows as $r) {
            $map[$r[$localKey]] = $r;
        }

        foreach ($rows as &$row) {
            $key = $row[$foreignKey];

            $row[$relationName] = isset($map[$key])
                ? $map[$key]
                : null;
        }

        return $rows;
    }
}
