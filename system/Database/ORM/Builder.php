<?php

namespace Mini\Database\ORM;

use Mini\Database\Query\Builder as QueryBuilder;
use Mini\Pagination\Paginator;
use Mini\Pagination\SimplePaginator;


class Builder
{
    /**
     * The model instance being queried.
     *
     * @var Mini\Database\ORM\Model
     */
    public $model;

    /**
     * The master relation instance, if any.
     *
     * @var Mini\Database\ORM\Relation
     */
    public $relation;

    /**
     * The fluent Query Builder for the query instance.
     *
     * @var \Mini\Database\Query\Builder
     */
    public $query;

    /**
     * The relationships that should be eagerly loaded by the query.
     *
     * @var array
     */
    public $eagerLoad = array();


    /**
     * The methods that should be returned from the fluent Query Builder.
     *
     * @var array
     */
    public $passthru = array(
        'lists', 'only', 'insert', 'insertGetId', 'update', 'increment',
        'delete', 'decrement', 'count', 'min', 'max', 'avg', 'sum', 'getGrammar'
    );


    /**
     * Create a new ORM query builder instance.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        $keyName = $this->model->getQualifiedKeyName();

        $this->query->where($keyName, '=', $id);

        return $this->first($columns);
    }

    /**
     * Find many models by their primary key.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return array
     */
    public function findMany(array $ids, $columns = array('*'))
    {
        if (empty($ids)) {
            return array();
        }

        $keyName = $this->model->getQualifiedKeyName();

        $this->query->whereIn($keyName, $ids);

        return $this->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Nova\Database\ORM\Model|static
     *
     * @throws \Nova\Database\ORM\ModelNotFoundException
     */
    public function findOrFail($id, $columns = array('*'))
    {
        if (! is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    /**
     * Get the first model result for the query.
     *
     * @param  array  $columns
     * @return mixed
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return (count($results) > 0) ? head($results) : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @param  array  $columns
     * @return \Nova\Database\ORM\Model|static
     *
     * @throws \Nova\Database\ORM\ModelNotFoundException
     */
    public function firstOrFail($columns = array('*'))
    {
        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_class($this->model));
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param  \Closure|array  $columns
     * @param  \Closure|null  $callback
     * @return \Mini\Database\ORM\Model|static|mixed
     */
    public function firstOr($columns = array('*'), Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            list ($callback, $columns) = array($columns, array('*'));
        }

        if (! is_null($model = $this->first($columns))) {
            return $model;
        }

        return call_user_func($callback);
    }

    /**
     * Get all of the model results for the query.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        if (isset($this->relation)) {
            return $this->relation->get($columns);
        }

        return $this->getModels($columns);
    }

    /**
     * Pluck a single column from the database.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = $this->first(array($column));

        if (! is_null($result)) {
            return $result->{$column};
        }
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @param  callable  $callback
     * @return void
     */
    public function chunk($count, callable $callback)
    {
        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            call_user_func($callback, $results);

            $page++;

            $results = $this->forPage($page, $count)->get();
        }
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        $results = $this->query->lists($column, $key);

        if ($this->model->hasGetMutator($column)) {
            foreach ($results as $key => &$value) {
                $attributes = array($column => $value);

                $model = $this->model->newFromBuilder($attributes);

                $value = $model->{$column};
            }
        }

        return $results;
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Mini\Pagination\Paginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = array('*'), $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $total = $this->query->getPaginationCount();

        //
        $results = ($total > 0) ? $this->forPage($page, $perPage)->get($columns) : array();

        return new Paginator($results, $total, $perPage, $page, array(
            'path'     => Paginator::resolveCurrentPath($pageName),
            'pageName' => $pageName,
        ));
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Mini\Pagination\SimplePaginator
     */
    public function simplePaginate($perPage = null, $columns = array('*'), $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        //
        $results = $this->skip(($page - 1) * $perPage)->take($perPage + 1)->get($columns);

        return new SimplePaginator($results, $perPage, $page, array(
            'path'     => Paginator::resolveCurrentPath($pageName),
            'pageName' => $pageName,
        ));
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array())
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->decrement($column, $amount, $extra);
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @param  array  $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps()) {
            return $values;
        }

        $column = $this->model->getUpdatedAtColumn();

        return array_add($values, $column, $this->model->freshTimestampString());
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     * @param  \Closure|null  $callback
     * @return \Mini\Database\ORM\Model[]
     */
    public function getModels($columns = array('*'), Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            list ($callback, $columns) = array($columns, array('*'));
        }

        $models = array();

        if (empty($results = $this->query->get($columns))) {
            return $models;
        }

        $connection = $this->model->getConnectionName();

        foreach ($results as $result) {
            $models[] = $model = $this->model->newFromBuilder((array) $result);

            $model->setConnection($connection);
        }

        if (! is_null($callback)) {
            $models = call_user_func($callback, $models);
        }

        return $this->eagerLoadRelations($models);
    }

    /**
     * Eager load the relationships for the models.
     *
     * @param  array  $models
     * @return array
     */
    public function eagerLoadRelations(array $models)
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->loadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Hydrate an eagerly loaded relationship on the model results.
     *
     * @param  array       $models
     * @param  string      $name
     * @param  array|null  $constraints
     * @return void
     */
    protected function loadRelation($models, $name, $constraints)
    {
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        if (! is_null($constraints)) {
            $relation->getQuery()->whereNested($constraints);
        }

        $models = $relation->initRelation($models, $name);

        return $relation->match($models, $relation->get(), $name);
    }

    /**
     * Get the relation instance for the given relation name.
     *
     * @param  string  $relation
     * @return \Mini\Database\ORM\Relations\Relation
     */
    public function getRelation($relation)
    {
        $query = Relation::noConstraints(function() use ($relation)
        {
            return $this->getModel()->$relation();
        });

        $nested = $this->nestedRelations($relation);

        if (count($nested) > 0) {
            $query->getQuery()->with($nested);
        }

        return $query;
    }

    /**
     * Gather the nested includes for a given relationship.
     *
     * @param  string  $relation
     * @return array
     */
    protected function nestedRelations($relation)
    {
        $nested = array();

        foreach ($this->eagerLoad as $name => $constraints) {
            if (starts_with($name, $relation .'.')) {
                $key = substr($name, strlen($relation .'.'));

                $nested[$key] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $eagers = $this->parseRelations($relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagers);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function without($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip($relations));

        return $this;
    }

    /**
     * Get the eagerly loaded relationships for the model.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseRelations(array $relations)
    {
        $results = array();

        foreach ($relations as $relation => $constraints) {
            if (is_numeric($relation)) {
                list ($relation, $constraints) = array($constraints, null);
            }

            $results[$relation] = $constraints;
        }

        return $results;
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return \Mini\Database\Query\Builder|static
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return void
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * Get the relationships being eagerly loaded.
     *
     * @return array
     */
    public function getEagerLoads()
    {
        return $this->eagerLoad;
    }

    /**
     * Set the relationships being eagerly loaded.
     *
     * @param  array  $eagerLoad
     * @return void
     */
    public function setEagerLoads(array $eagerLoad)
    {
        $this->eagerLoad = $eagerLoad;
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Mini\Database\ORM\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @param  \Mini\Database\ORM\Model  $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Set a master relation instance.
     *
     * @param  \Mini\Database\ORM\Relation  $relation
     * @return $this
     */
    public function setRelation(Relation $relation)
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * Handle dynamic method calls to the query.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        if (in_array($method, $this->passthru)) {
            return $result;
        }

        return $this;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}
