<?php

namespace Ellephanty\Model;

use Ellephanty\Model\Model;
use Ellephanty\Collecty\Collection;

class BaseQueryBuilder
{
    protected $model;

    protected $wheres = [];

    protected $with = [];

    protected $whereIns = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function findAll($options = array())
    {
        $query = $this->buildQuery($options);

        $stmt = Model::connection()->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($this->with)) {
            $result = $this->eagerLoad($result, $this->with);
        }

        return new Collection($result);
    }

    protected function eagerLoad($rows, $relations)
    {
        foreach ($relations as $relation) {

            if (!method_exists($this->model, $relation)) {
                continue;
            }

            $relationMeta = $this->model->$relation();

            $relatedClass = $relationMeta['model'];
            $foreignKey = $relationMeta['foreignKey'];
            $localKey = $relationMeta['localKey'];

            $ids = array_column($rows, $localKey);
            $ids = array_unique($ids);

            if (empty($ids)) {
                continue;
            }

            $in = implode(',', array_map(function ($id) {
                return is_numeric($id) ? $id : "'$id'";
            }, $ids));

            $relatedRows = $relatedClass::query()
                ->whereIn($foreignKey, $ids)
                ->findAll();

            $map = [];
            foreach ($relatedRows as $r) {
                $map[$r[$foreignKey]] = $r;
            }

            foreach ($rows as &$row) {
                $row[$relation] = $map[$row[$localKey]] ? $map[$row[$localKey]] : null;
            }
        }

        return $rows;
    }
    public function findOne($options = array())
    {
        $query = $this->buildQuery($options);

        $stmt = $this->model->connection()->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result;
    }

    private function buildQuery($options = array())
    {
        $query = "SELECT <distinct> <attributes> FROM " . $this->model->table() . " WHERE <where>";

        // Las columnas que se quieren obtener
        if (isset($options["attributes"]) && count($options["attributes"]) > 0) {

            // Si el atributo es un array se sustituye el array por el nombre de la columna con el alias
            foreach ($options["attributes"] as $attribute) {
                if (is_array($attribute)) {
                    $options["attributes"][array_search($attribute, $options["attributes"])] = $attribute[0] . " AS " . $attribute[1];
                }
            }
            $query = str_replace("<attributes>", implode(", ", $options["attributes"]), $query);
        } else {
            $query = str_replace("<attributes>", "*", $query);
        }

        $conditions = array();

        // Las condiciones que se quieren aplicar
        if (isset($this->wheres) && count($this->wheres) > 0) {
            foreach ($this->wheres as $column => $whereValue) {

                // Ejemplo: ["CLAVE" => "1234"]
                if (!is_array($whereValue)) {
                    if (is_int($whereValue)) {
                        $conditions[] = "$column = $whereValue";
                    } else {
                        $conditions[] = "$column = '" . addslashes($whereValue) . "'";
                    }
                    continue;
                }

                // Ejemplo: ["CLAVE" => ["length" => 4]]
                if (isset($whereValue['length'])) {
                    $conditions[] = "LEN($column) = " . intval($whereValue['length']);
                }

                // Ejemplo: ["CLAVE" => ["numerico" => true]]
                if (!empty($whereValue['numerico'])) {
                    $conditions[] = "$column NOT LIKE '%[^0-9]%'";
                }

                // Ejemplo: ["CLAVE" => ["=" => "1234"]]
                $operators = ['=', '>', '<', '>=', '<=', '<>', 'LIKE', '!='];
                foreach ($operators as $operator) {
                    if (isset($whereValue[$operator])) {
                        $operatorValue = $whereValue[$operator];
                        if (is_int($operatorValue)) {
                            $conditions[] = "$column $operator $operatorValue";
                        } else {
                            $conditions[] = "$column $operator '" . addslashes($operatorValue) . "'";
                        }
                    }
                }
            }
            if (count($conditions) > 0) {
                $query = str_replace("<where>", implode(" AND ", $conditions), $query);
            } else {
                $query = str_replace(" WHERE <where>", "", $query);
            }
        } else {
            $query = str_replace(" WHERE <where>", "", $query);
        }

        // Si se paso un ordenamiento se aplica
        if (isset($options["order"])) {
            $query .= " ORDER BY " . $options["order"][0][0] . " " . $options["order"][0][1];
        }

        if (isset($options["distinct"]) && $options["distinct"] == true) {
            $query = str_replace("<distinct>", "DISTINCT", $query);
        } else {
            $query = str_replace("<distinct>", "", $query);
        }

        // Remueve doble espacio si hay
        $query = preg_replace('/\s+/', ' ', $query);

        return $query;
    }
}
