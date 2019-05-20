<?php

namespace Mini\Database\ORM\Relations;

use Mini\Database\ORM\Builder;
use Mini\Database\ORM\Model;
use Mini\Database\ORM\Relation;

use Carbon\Carbon;


class HasOneOrMany extends Relation
{
    /**
     * The foreign key of the parent model.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The local key of the parent model.
     *
     * @var string
     */
    protected $localKey;


    /**
     * Create a new has many relationship instance.
     *
     * @param  \Mini\Database\ORM\Builder  $query
     * @param  \Mini\Database\ORM\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return void
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->localKey   = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the proper constraints on the relationship table.
     *
     * @return void
     */
    protected function addConstraints()
    {
        if (static::$constraints) {
            $this->query->where($this->getForeignKey(), '=', $this->getParentKey());
        }
    }

    /**
     * Set the proper constraints on the relationship table for an eager load.
     *
     * @param  array  $results
     * @return void
     */
    public function addEagerConstraints($results)
    {
        $this->query->whereIn($this->getForeignKey(), $this->getKeys($results));
    }

    /**
     * Match eagerly loaded child models to their parent models.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string $relation
     * @param  string $type
     * @return array
     */
    public function matchOneOrMany(array $models, array $results, $relation, $type)
    {
        $foreign = $this->getPlainForeignKey();

        //
        $dictionary = array();

        foreach ($results as $result) {
            $key = $result->getAttribute($foreign);

            $dictionary[$key] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (array_key_exists($key, $dictionary)) {
                $value = $dictionary[$key];

                $model->setRelation($relation, ($type === 'one') ? reset($value) : $value);
            }
        }

        return $models;
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param  \Mini\Database\ORM\Model  $model
     * @return \Mini\Database\ORM\Model
     */
    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->getParentKey());

        return $model->save() ? $model : false;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Mini\Database\ORM\Model
     */
    public function create(array $attributes)
    {
        $instance = $this->related->newInstance($attributes);

        $instance->setAttribute($this->getPlainForeignKey(), $this->getParentKey());

        $instance->save();

        return $instance;
    }

    /**
     * Update a record for the association.
     *
     * @param  array  $attributes
     * @return bool
     */
    public function update(array $attributes)
    {
        if ($this->related->usesTimestamps()) {
            $key = $this->relatedUpdatedAt();

            $attributes[$key] = $this->related->freshTimestampString();
        }

        return $this->query->update($attributes);
    }

    /**
     * Get the foreign key for the relationship.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        $segments = explode('.', $this->getForeignKey());

        return end($segments);
    }

    /**
     * Get the key value of the parent's local key.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }
}
