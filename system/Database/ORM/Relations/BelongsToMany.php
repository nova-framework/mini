<?php

namespace System\Database\ORM\Relations;

use System\Database\ORM\Model;
use System\Database\ORM\ModelNotFoundException;
use System\Database\ORM\Pivot;
use System\Database\ORM\Relation;


class BelongsToMany extends Relation
{
    /**
     * The intermediate table for the relation.
     *
     * @var string
     */
    protected $table;

    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The associated key of the relation.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * The "name" of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * The pivot table columns to retrieve.
     *
     * @var array
     */
    protected $pivotColumns = array();

    /**
     * The class name of the custom pivot model to use for the relationship.
     *
     * @var string
     */
    protected $using;


    /**
     * Create a new has many relationship instance.
     *
     * @param  \System\Database\ORM\Builder  $query
     * @param  \System\Database\ORM\Model  $parent
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relationName
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $table, $foreignKey, $otherKey, $relationName = null)
    {
        $this->table = $table;
        $this->otherKey = $otherKey;
        $this->foreignKey = $foreignKey;
        $this->relationName = $relationName;

        parent::__construct($query, $parent);
    }

    /**
     * Specify the custom Pivot Model to use for the relationship.
     *
     * @param  string  $className
     * @return $this
     */
    public function using($className)
    {
        $this->using = $className;

        return $this;
    }

    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Get all of the model results.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        $columns = $this->query->getQuery()->columns ? array() : $columns;

        $query = $this->query->addSelect($this->getSelectColumns($columns));

        $models = $query->getModels(array('*'), function ($models)
        {
            return $this->hydratePivotRelation($models);
        });

        return $models;
    }

    /**
     * Hydrate the pivot table relationship on the models.
     *
     * @param  array  $models
     * @return void
     */
    protected function hydratePivotRelation(array $models)
    {
        foreach ($models as $model) {
            $attributes = array();

            foreach ($model->getAttributes() as $attribute => $value) {
                if (strpos($attribute, 'pivot_') === 0) {
                    $key = substr($key, 6);

                    $attributes[$key] = $value;

                    unset($model->{$attribute});
                }
            }

            $model->setRelation('pivot', $this->newPivot($attributes, true));
        }

        return $models;
    }

    /**
     * Save a new model and attach it to the parent model.
     *
     * @param  \System\Database\ORM\Model  $model
     * @param  array  $joining
     * @return \System\Database\ORM\Model
     */
    public function save(Model $model, array $joining = array())
    {
        $model->save();

        $this->attach($model->getKey(), $joining);

        return $model;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @param  array  $joining
     * @return \System\Database\ORM\Model
     */
    public function create(array $attributes, array $joining = array())
    {
        $instance = $this->related->newInstance($attributes);

        $instance->save();

        $this->attach($instance->getKey(), $joining);

        return $instance;
    }

    /**
     * Sync the joining table with the array of given IDs.
     *
     * @param  array  $ids
     * @return bool
     */
    public function sync($ids)
    {
        $currentIds = $this->newPivotQuery()->lists($this->otherKey);

        $ids = (array) $ids;

        //
        $attachIds = array_diff($ids, $currentIds);

        if (count($attachIds) > 0) {
            $this->attach($attachIds);
        }

        $detachIds = array_diff($currentIds, $ids);

        if (count($detachIds) > 0) {
            $this->detach($detachIds);
        }
    }

    /**
     * Insert a new record into the joining table of the association.
     *
     * @param  array|Model|int    $id
     * @param  array  $attributes
     * @return bool
     */
    public function attach($id, $attributes = array())
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $query = $this->newPivotStatement();

        $records = array_map(function ($id) use ($attributes)
        {
            $record = $this->createAttachRecord($id);

            return array_merge($record, $attributes);

        }, (array) $id);

        $query->insert($records);
    }

    /**
     * Create a new pivot attachment record.
     *
     * @param  int   $id
     * @return array
     */
    protected function createAttachRecord($id)
    {
        $record = array(
            $this->foreignKey => $this->parent->getKey(),
            $this->otherKey   => $id,
        );

        $timestamp = $this->parent->freshTimestampString();

        if (in_array($key = $this->createdAt(), $this->pivotColumns)) {
            $record[$key] = $timestamp;
        }

        if (in_array($key = $this->updatedAt(), $this->pivotColumns)) {
            $record[$key] = $timestamp;
        }

        return $record;
    }

    /**
     * Detach a record from the joining table of the association.
     *
     * @param  array|Model|int   $ids
     * @return bool
     */
    public function detach($ids = array())
    {
        if ($ids instanceof Model) {
            $ids = $ids->getKey();
        }

        $ids = (array) $ids;

        //
        $query = $this->newPivotQuery();

        if (count($ids) > 0) {
            $query->whereIn($this->otherKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Set the proper constraints on the relationship table.
     *
     * @return void
     */
    protected function addConstraints()
    {
        $this->setJoin();

        if (static::$constraints) {
            $this->query->where($this->getForeignKey(), '=', $this->parent->getKey());
        }
    }

    /**
     * Get the SELECT clause on the query builder for the relationship.
     *
     * @param  array  $columns
     * @return array
     */
    protected function getSelectColumns(array $columns = array('*'))
    {
        if ($columns == array('*')) {
            $columns = array($this->related->getTable() .'.*');
        }

        $defaults = array($this->foreignKey, $this->otherKey);

        foreach (array_merge($defaults, $this->pivotColumns) as $column) {
            $columns[] = $this->table .'.' .$column .' as pivot_' .$column;
        }

        return array_unique($columns);
    }

    /**
     * Set the JOIN clause on the query builder for the relationship.
     *
     * @param  \System\Database\ORM\Builder|null
     * @return $this
     */
    protected function setJoin($query = null)
    {
        $query = $query ?: $this->query;

        $key = $this->related->getTable() .'.' .$this->related->getKeyName();

        $query->join($this->table, $key, '=', $this->getOtherKey());

        return $this;
    }

    /**
     * Initialize a relationship on an array of parent models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return void
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
             $model->setRelation($relation, array());
        }

        return $models;
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->whereIn($this->table .'.' .$this->getForeignKey(), $this->getKeys($models));
    }

    /**
     * Match eagerly loaded child models to their parent models.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string $relation
     * @return array
     */
    public function match($models, $results, $relation)
    {
        $foreign = $this->getForeignKey();

        //
        $dictionary = array();

        foreach ($results as $result) {
            $key = $result->pivot->getAttribute($foreign);

            $dictionary[$key][] = $result;
        }

        foreach ($models as $model) {
            if (array_key_exists($key = $model->getKey(), $dictionary)) {
                $value = $dictionary[$key];

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \System\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        $query = $this->newPivotStatement();

        return $query->where($this->foreignKey, $this->parent->getKey());
    }

    /**
     * Get a new plain query builder for the pivot table.
     *
     * @return \System\Database\Query\Builder
     */
    public function newPivotStatement()
    {
        return $this->query->getQuery()->newQuery()->from($this->table);
    }

    /**
     * Create a new pivot model instance.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return \System\Database\ORM\Relations\Pivot
     */
    public function newPivot(array $attributes = array(), $exists = false)
    {
        $pivot = $this->related->newPivot($this->parent, $attributes, $this->table, $exists, $this->using);

        return $pivot->setPivotKeys($this->foreignKey, $this->otherKey);
    }

    /**
     * Set the columns on the pivot table to retrieve.
     *
     * @param  mixed  $columns
     * @return $this
     */
    public function withPivot($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        return $this;
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param  mixed  $createdAt
     * @param  mixed  $updatedAt
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        return $this->withPivot($createdAt ?: $this->createdAt(), $updatedAt ?: $this->updatedAt());
    }

    /**
     * Get the fully qualified foreign key for the relation.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->table.'.'.$this->foreignKey;
    }

    /**
     * Get the fully qualified "other key" for the relation.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->table .'.' .$this->otherKey;
    }

    /**
     * Get the intermediate table for the relationship.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }
}
