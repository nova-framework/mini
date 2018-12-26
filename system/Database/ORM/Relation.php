<?php

namespace System\Database\ORM;

use System\Database\Query\Expression;

use Closure;


abstract class Relation
{
    /**
     * The ORM query builder instance.
     *
     * @var \System\Database\ORM\Builder
     */
    protected $query;

    /**
     * The parent model instance.
     *
     * @var \System\Database\ORM\Model
     */
    protected $parent;

    /**
     * The related model instance.
     *
     * @var \System\Database\ORM\Model
     */
    protected $related;

    /**
     * Indicates if the relation is adding constraints.
     *
     * @var bool
     */
    protected static $constraints = true;


    /**
     * Create a new relation instance.
     *
     * @param  \System\Database\ORM\Builder  $query
     * @param  \System\Database\ORM\Model  $parent
     * @return void
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query->setRelation($this);

        $this->parent = $parent;

        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    abstract public function addConstraints();

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    abstract public function addEagerConstraints(array $models);

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    abstract public function initRelation(array $models, $relation);

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  array   $results
     * @param  string  $relation
     * @return array
     */
    abstract public function match(array $models, array $results, $relation);

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();

    /**
     *  Get all of the model results.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        return $this->query->getModels($columns);
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * @return void
     */
    public function touch()
    {
        $related = $this->getRelated();

        $this->query->update(array(
            $related->getUpdatedAtColumn() => $related->freshTimestampString()
        ));
    }

    /**
     * Run a callback with constraints disabled on the relation.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function noConstraints(Closure $callback)
    {
        static::$constraints = false;

        //
        $results = call_user_func($callback);

        static::$constraints = true;

        return $results;
    }

    /**
     * Get all of the primary keys for an array of models.
     *
     * @param  array   $models
     * @param  string  $key
     * @return array
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_unique(array_values(array_map(function ($value) use ($key)
        {
            return $key ? $value->getAttribute($key) : $value->getKey();

        }, $models)));
    }

    /**
     * Get the underlying query for the relation.
     *
     * @return \System\Database\ORM\Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get the base query builder driving the ORM builder.
     *
     * @return \System\Database\Query\Builder
     */
    public function getBaseQuery()
    {
        return $this->query->getQuery();
    }

    /**
     * Get the parent model of the relation.
     *
     * @return \System\Database\ORM\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getQualifiedKeyName();
    }

    /**
     * Get the related model of the relation.
     *
     * @return \System\Database\ORM\Model
     */
    public function getRelated()
    {
        return $this->related;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the name of the related model's "updated at" column.
     *
     * @return string
     */
    public function relatedUpdatedAt()
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * Wrap the given value with the parent query's grammar.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        return $this->parent->getBaseQuery()->getGrammar()->wrap($value);
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        if ($result === $this->query) return $this;

        return $result;
    }

}
