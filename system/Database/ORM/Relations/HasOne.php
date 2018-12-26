<?php

namespace System\Database\ORM\Relations;


class HasOne extends HasOneOrMany
{

    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return \System\Database\ORM\Model
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Initialize a relationship on an array of parent models.
     *
     * @param  array   $models
     * @param  string  $relationship
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
     * Match eagerly loaded child models to their parent models.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string $relation
     * @return array
     */
    public function match(array $models, array $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'one');
    }
}
