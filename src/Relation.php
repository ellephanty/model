<?php

namespace Ellephanty\Model;

abstract class Relation
{
    protected $model;
    protected $foreignKey;
    protected $localKey;
    protected $name;

    public function __construct($model, $foreignKey, $localKey)
    {
        $this->model = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function model()
    {
        return $this->model;
    }

    public function foreignKey()
    {
        return $this->foreignKey;
    }

    public function localKey()
    {
        return $this->localKey;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    abstract public function eagerLoad(array &$rows);
}
