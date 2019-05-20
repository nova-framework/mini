<?php

namespace Mini\Database\ORM\Relations;


class HasMany extends HasOneOrMany
{

    /**
     * Get the properly hydrated results for the relationship.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->query->get();
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
     * Match eagerly loaded child models to their parent models.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string $relation
     * @return array
     */
    public function match(array $models, array $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }
}
