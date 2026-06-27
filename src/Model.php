<?php

namespace Ellephanty\Model;

use Ellephanty\Database\Database;
use Ellephanty\Model\QueryBuilder;

class Model
{
    public $table;
    private static $database;

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

    public static function query()
    {
        return new QueryBuilder(new static());
    }

    public function table()
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
