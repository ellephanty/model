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

    protected $limit;

    protected $syntax;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->syntax = include __DIR__ . '/../config/syntaxis.php';
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

    protected function eagerLoad($rows)
    {
        foreach ($this->with as $name) {

            if (!method_exists($this->model, $name)) {
                continue;
            }

            $relation = $this->model->$name()->setName($name);

            $rows = $relation->eagerLoad($rows);
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
        switch (getenv("DB_DSN")) {
            case 'dblib':
                $query = "SELECT <limit> <distinct> <attributes> FROM {$this->model->table()} WHERE <where>";
                break;

            case 'mysql':
                $query = "SELECT <distinct> <attributes> FROM {$this->model->table()} WHERE <where> <limit>";
                break;

            default:
                throw new \Exception(
                    "No se ha configurado un driver de base de datos válido en DB_DSN."
                );
        }


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
        }

        if (!empty($this->whereIns)) {
            foreach ($this->whereIns as $column => $values) {

                if (!is_array($values) || empty($values)) {
                    continue;
                }

                $cleanValues = [];

                foreach ($values as $value) {

                    if (is_array($value) || is_object($value)) {
                        continue;
                    }

                    if (is_int($value) || is_float($value)) {
                        $cleanValues[] = $value;
                    } elseif (is_string($value)) {
                        $cleanValues[] = "'" . addslashes($value) . "'";
                    } elseif (is_null($value)) {
                        $cleanValues[] = "NULL";
                    }
                }

                if (!empty($cleanValues)) {
                    $conditions[] = "$column IN (" . implode(", ", $cleanValues) . ")";
                }
            }
        }

        if (count($conditions) > 0) {
            $query = str_replace("<where>", implode(" AND ", $conditions), $query);
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

        if (isset($this->limit)) {
            $query = str_replace("<limit>", $this->syntax[getenv('DB_DSN')]['LIMIT'] . " " . $this->limit, $query);
        } else {
            $query = str_replace("<limit>", "", $query);
        }

        // Remueve doble espacio si hay
        $query = preg_replace('/\s+/', ' ', $query);

        return $query;
    }

    public function query()
    {
        return $this->buildQuery();
    }
}
