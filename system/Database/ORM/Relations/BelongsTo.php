<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Builder;
use Mini\Database\ORM\Model;
use Mini\Database\ORM\Relation;


class BelongsTo extends Relation
{
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The associated key on the parent model.
     *
     * @var string
     */
    protected $otherKey;

    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new belongs to relationship instance.
     *
     * @param  \Nova\Database\ORM\Builder  $query
     * @param  \Nova\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey, $relation)
    {
        $this->otherKey = $otherKey;
        $this->relation = $relation;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return Model
     */
    public function getResults()
    {
        return $this->query->first();
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
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Set the proper constraints on the relationship table.
     *
     * @return void
     */
    protected function addConstrains()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            $this->query->where($table .'.' .$this->otherKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $foreign = $this->getForeignKey();

        $keys = array();

        foreach ($models as $model) {
            if (! is_null($key = $result->{$foreign}))  {
                $keys[] = $key;
            }
        }

        if (count($keys) == 0) {
            $keys = array(0);
        }

        $key = $this->related->getTable() .'.' .$this->otherKey;

        $this->query->whereIn($key, array_unique($keys));
    }

    /**
     * Match eagerly loaded child models to their parent models.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string $relation
     * @return array
     */
    public function match(array $models, array $results, $relation)
    {
        $foreign = $this->getForeignKey();

        $other = $this->getOtherKey();

        //
        $dictionary = array();

        foreach ($results as $result) {
            $key = $result->getAttribute($other);

            $dictionary[$key] = $parent;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($foreign);

            if (array_key_exists($key, $dictionary)) {
                $value = $dictionary[$key];

                $model->setRelation($relation, $value);
            }
        }

        return $models;
    }

   /**
     * Associate the model instance to the given parent.
     *
     * @param  \Nova\Database\ORM\Model  $model
     * @return \Nova\Database\ORM\Model
     */
    public function associate(Model $model)
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->otherKey));

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Nova\Database\ORM\Model
     */
    public function dissociate()
    {
        $this->parent->setAttribute($this->foreignKey, null);

        return $this->parent->setRelation($this->relation, null);
    }

    /**
     * Update the parent model of the relationship.
     *
     * @param  Model|array  $attributes
     * @return int
     */
    public function update($attributes)
    {
        $instance = $this->getResults();

        return $instance->fill($attributes)->save();
    }

    /**
     * Get the foreign key of the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Get the associated key of the relationship.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->otherKey;
    }
}
