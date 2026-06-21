<?php

namespace Ellephanty\Model;

include_once __DIR__ . '/../vendor/autoload.php';

use Ellephanty\Database\Database;

class Model
{
    public $table;
    private $where = array();
    private static $database;

    public function __construct($where = array())
    {
        $this->where = $where;
    }

    public static function connection()
    {
        if (self::$database == null) {
            self::$database = new Database;
        }

        if (!self::$database->connection()) {
            self::$database->connect();
        }

        return self::$database->connection();
    }

    public static function database()
    {
        return self::$database;
    }

    public static function where($array = array())
    {
        $class = get_called_class();
        return new $class($array);
    }

    private function find($options = array())
    {
        $query = "SELECT <distinct> <attributes> FROM " . $this->table() . " WHERE <where>";

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
        if (isset($this->where) && count($this->where) > 0) {
            foreach ($this->where as $column => $whereValue) {

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

    public function findAll($options = array())
    {
        $query = $this->find($options);

        $stmt = $this->connection()->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function findOne($options = array())
    {
        $query = $this->find($options);

        $stmt = $this->connection()->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    private function table()
    {
        if (!empty($this->table)) {
            return $this->table;
        }

        $className = static::class;

        $pos = strrpos($className, '\\');

        if ($pos !== false) {
            $className = substr($className, $pos + 1);
        }

        $className = preg_replace('/(?<!^)[A-Z]/', '_$0', $className);

        return strtoupper($className);
    }
}
