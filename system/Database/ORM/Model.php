<?php

namespace Mini\Database\ORM;

use Mini\Database\Query\Builder as QueryBuilder;
use Mini\Database\ConnectionResolverInterface as Resolver;
use Mini\Events\Dispatcher;
use Mini\Support\Contracts\ArrayableInterface;
use Mini\Support\Contracts\JsonableInterface;
use Mini\Support\Str;

use Carbon\Carbon;

use DateTime;


class Model implements ArrayableInterface, JsonableInterface, \ArrayAccess
{
    /**
     * The Database Connection name.
     *
     * @var string
     */
    protected $connection = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * The model attribute's original state.
     *
     * @var array
     */
    protected $original = array();

    /**
     * The loaded relationships for the model.
     *
     * @var array
     */
    protected $relations = array();

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = array();

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = array();

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = array('*');

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = array();

    /**
     * User exposed observable events.
     *
     * @var array
     */
    protected $observables = array();

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = array();

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = false;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The connection resolver instance.
     *
     * @var \Mini\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * The event dispatcher instance.
     *
     * @var \Mini\Events\Dispatcher
     */
    protected static $dispatcher;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = array();

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutatorCache = array();

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';


    /**
     * Create a new Model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = array())
    {
        $this->bootIfNotBooted();

        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        $class = get_class($this);

        if (! isset(static::$booted[$class])) {
            static::$booted[$class] = true;

            $this->fireModelEvent('booting', false);

            static::boot();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        $class = get_called_class();

        static::$mutatorCache[$class] = array();

        foreach (get_class_methods($class) as $method) {
            if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
                static::$mutatorCache[$class][] = Str::snake($matches[1]);
            }
        }
    }

    /**
     * Register an observer with the Model.
     *
     * @param  object  $class
     * @return void
     */
    public static function observe($class)
    {
        $instance = new static;

        $className = is_string($class) ? $class : get_class($class);

        foreach ($instance->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className .'@' .$event);
            }
        }
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function create(array $attributes)
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * Begin querying the model.
     *
     * @return \Mini\Database\Query\Builder
     */
    public static function query()
    {
        $instance = new static;

        return $instance->newQuery();
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param  string  $connection
     * @return \Mini\Database\Query\Builder
     */
    public static function on($connection = null)
    {
        $instance = new static;

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array  $columns
     * @return array
     */
    public static function all($columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->get($columns);
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return Model
     */
    public static function find($id, $columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->find($id, $columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return array|static
     *
     * @throws \Mini\Database\ORM\ModelNotFoundException
     */
    public static function findOrFail($id, $columns = array('*'))
    {
        if (! is_null($model = static::find($id, $columns))) {
            return $model;
        }

        throw (new ModelNotFoundException)->setModel(get_called_class());
    }

    /**
     * Eager load relations on the model.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function load($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $query = $this->newQuery()->with($relations);

        $query->eagerLoadRelations(array($this));

        return $this;
    }

    /**
     * Being querying a model with eager loading.
     *
     * @param  array|string  $relations
     * @return \Mini\Database\ORM\Builder|static
     */
    public static function with($relations)
    {
        if (is_string($relations)) $relations = func_get_args();

        $instance = new static;

        return $instance->newQuery()->with($relations);
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Mini\Database\ORM\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasOne($instance->newQuery(), $this, $instance->getTable() .'.' .$foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Mini\Database\ORM\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        if (is_null($relation)) {
            list (, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) .'_id';
        }

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new Relations\BelongsTo($instance->newQuery(), $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Mini\Database\ORM\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasMany($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Mini\Database\ORM\Relations\BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $names = array(
                Str::snake(class_basename($related)),
                Str::snake(class_basename($this))
            );

            sort($names);

            $table = strtolower(implode('_', $names));
        }

        return new Relations\BelongsToMany($instance->newQuery(), $this, $table, $foreignKey, $otherKey, $relation);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set' .Str::studly($key) .'Attribute';

            return call_user_func(array($this, $method), $value);
        }

        // Dates
        else if (in_array($key, $this->getDates()) && ! empty($value)) {
            $value = $this->fromDateTime($value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        // Relationships
        else if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $camelKey = Str::camel($key))) {
            $relation = call_user_func(array($this, $camelKey));

            if (! $relation instanceof Relation) {
                throw new LogicException('Relationship method must return an instance of [Mini\Database\ORM\Relation]');
            }

            return $this->relations[$key] = $relation->getResults();
        }
    }

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return array_merge(array(
            'creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved',

        ), $this->observables);
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
    {
        $event = sprintf('database.model.%s: %s', $event, get_class($this));

        if (isset(static::$dispatcher)) {
            static::$dispatcher->listen($event, $callback);
        }
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool    $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        $event = sprintf('database.model.%s: %s', $event, get_class($this));

        if (isset(static::$dispatcher)) {
            $method = $halt ? 'until' : 'dispatch';

            return call_user_func(array(static::$dispatcher, $method), $event, $this);
        }

        return true;
    }

    /**
     * Save the model and all of its relations to the database.
     *
     * @return bool
     */
    public function push()
    {
        $this->save();

        foreach ($this->relations as $name => $models) {
            if (! is_array($models)) {
                $models = array($models);
            }

            foreach ($models as $model) {
                $model->push();
            }
        }
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save()
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $query = $this->newQuery();

        if ($this->exists) {
            $saved = $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) {
            $this->fireModelEvent('saved', false);

            $this->syncOriginal();
        }

        return $saved;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return bool
     */
    protected function performUpdate(Builder $query)
    {
        if (! empty($dirty = $this->getDirty())) {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            if ($this->timestamps) {
                $this->updateTimestamps();
            }

            if (! empty($dirty = $this->getDirty())) {
                $id = array_get($this->original, $keyName = $this->getKeyName(), $this->getAttribute($keyName));

                $query->where($keyName, $id)->update($dirty);

                $this->fireModelEvent('updated', false);
            }
        }

        return true;
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return bool
     */
    protected function performInsert(Builder $query)
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributes();

        if ($this->incrementing) {
            $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

            $this->setAttribute($keyName, $id);
        } else {
            $query->insert($attributes);
        }

        $this->exists = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Delete the model from the database.
     *
     * @return bool|null
     */
    public function delete()
    {
        if ($this->exists) {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }

            $this->newQuery()->where($this->getKeyName(), $this->getKey())->delete();

            $this->exists = false;

            $this->fireModelEvent('deleted', false);

            return true;
        }
    }

    /**
     * Update the model's update timestamp.
     *
     * @return bool
     */
    public function touch()
    {
        if (! $this->timestamps) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the creation and update timestamps.
     *
     * @return void
     */
    protected function updateTimestamps()
    {
        $timestamp = new Carbon();

        //
        $column = $this->getUpdatedAtColumn();

        if (! empty($column) && ! array_key_exists($column, $dirty = $this->getDirty())) {
            $this->setAttribute($column, $timestamp);
        }

        $column = $this->getCreatedAtColumn();

        if (! $this->exists && ! empty($column) && ! array_key_exists($column, $dirty)) {
            $this->setAttribute($column, $timestamp);
        }
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return string
     */
    public function freshTimestampString()
    {
        $timestamp = new Carbon();

        return $this->fromDateTime($timestamp);
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getTable() .'.' .$this->getKeyName();
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Get the number of models to return per page.
     *
     * @return int
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * Set the number of models to return per page.
     *
     * @param  int   $perPage
     * @return void
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        $name = class_basename($this);

        return sprintf('%s_id', Str::snake($name));
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        return array_filter($this->attributes, function ($value, $key)
        {
            return ! array_key_exists($key, $this->original) || ($value != $this->original[$key]);

        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get a plain attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeValue($key)
    {
        $value = array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;

        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // Dates
        else if (in_array($key, $this->getDates()) && ! empty($value)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        $method = sprintf('set%sAttribute', Str::studly($key));

        return method_exists($this, $method);
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        $method = sprintf('get%sAttribute', Str::studly($key));

        return method_exists($this, $method);
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        $method = sprintf('get%sAttribute', Str::studly($key));

        return call_user_func(array($this, $method), $value);
    }

    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array
     */
    public function getDates()
    {
        return array_merge(
            $this->dates, array(static::CREATED_AT, static::UPDATED_AT)
        );
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  \DateTime|int  $value
     * @return string
     */
    public function fromDateTime($value)
    {
        $format = $this->getDateFormat();

        if ($value instanceof DateTime) {
            //
        } else if (is_numeric($value)) {
            $value = Carbon::createFromTimestamp($value);
        } else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            $value = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } else {
            $value = Carbon::createFromFormat($format, $value);
        }

        return $value->format($format);
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        } else if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } else if (! $value instanceof DateTime) {
            $format = $this->getDateFormat();

            return Carbon::createFromFormat($format, $value);
        }

        return Carbon::instance($value);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return $this->getConnection()->getGrammar()->getDateFormat();
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return Model
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return Model
     */
    public function fill(array $attributes)
    {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array  $attributes
     * @return array
     */
    protected function fillableFromArray(array $attributes)
    {
        if ((count($this->fillable) > 0) && ! static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        return $attributes;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     *
     * @param  string  $key
     * @return bool
     */
    public function isFillable($key)
    {
        if (static::$unguarded || in_array($key, $this->fillable)) {
            return true;
        } else if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && ! Str::startsWith($key, '_');
    }

    /**
     * Determine if the given key is guarded.
     *
     * @param  string  $key
     * @return bool
     */
    public function isGuarded($key)
    {
        return in_array($key, $this->guarded) || ($this->guarded == array('*'));
    }

    /**
     * Get a new query for the model's table.
     *
     * @return \Mini\Database\Query\Builder
     */
    public function newQuery()
    {
        $query = new Builder(
            $this->newBaseQuery()
        );

        return $query->setModel($this)->with($this->with);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Mini\Database\Query\Builder
     */
    protected function newBaseQuery()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getGrammar());
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return Model
     */
    public function newInstance($attributes = array(), $exists = false)
    {
        $model = new static((array) $attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array  $attributes
     * @return Model
     */
    public function newFromBuilder($attributes = array())
    {
        $instance = $this->newInstance(array(), true);

        $instance->setRawAttributes((array) $attributes, true);

        return $instance;
    }

    /**
     * Create a new pivot model instance.
     *
     * @param  \Mini\Database\ORM\Model  $parent
     * @param  array   $attributes
     * @param  string  $table
     * @param  bool    $exists
     * @param  string|null  $using
     * @return \Mini\Database\ORM\Pivot
     */
    public function newPivot(Model $parent, array $attributes, $table, $exists, $using = null)
    {
        $className = $using ?: Pivot::class;

        return new $className($parent, $attributes, $table, $exists);
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool   $sync
     * @return void
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }
    }

    public function getTable()
    {
        if (! isset($this->table)) {
            return str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));
        }

        return $this->table;
    }

    /**
     * Disable all mass assignable restrictions.
     *
     * @return void
     */
    public static function unguard()
    {
        static::$unguarded = true;
    }

    /**
     * Enable the mass assignment restrictions.
     *
     * @return void
     */
    public static function reguard()
    {
        static::$unguarded = false;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     *
     * @param  string  $relation
     * @return mixed
     */
    public function getRelation($relation)
    {
        return $this->relations[$relation];
    }

    /**
     * Set the specific relationship in the model.
     *
     * @param  string  $relation
     * @param  mixed   $value
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Get the database Connection instance.
     *
     * @return \Mini\Database\Connection
     */
    public function getConnection()
    {
        return static::$resolver->connection($this->connection);
    }

    /**
     * Get the current Connection name for the Model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the Connection associated with the Model.
     *
     * @param  string  $name
     * @return \Mini\Database\Model
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Get the connection resolver instance.
     *
     * @return \Mini\Database\Manager
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     *
     * @param  \Mini\Database\ConnectionResolverInterface  $resolver
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for models.
     *
     * @return void
     */
    public static function unsetConnectionResolver()
    {
        static::$resolver = null;
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Mini\Events\Dispatcher
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Mini\Events\Dispatcher  $dispatcher
     * @return void
     */
    public static function setEventDispatcher(Dispatcher $dispatcher)
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = array_diff_key($this->attributes, array_flip($this->hidden));

        foreach ($this->getDates() as $key) {
            if (! empty($value = array_get($attributes, $key))) {
                $attributes[$key] = (string) $this->asDateTime($value);
            }
        }

        $mutatedAttributes = array_get(static::$mutatorCache, get_class($this), array());

        foreach ($mutatedAttributes as $key) {
            if (array_key_exists($key, $attributes)) {
                $value = $this->mutateAttribute($key, $attributes[$key]);

                $attributes[$key] = ($value instanceof ArrayableInterface) ? $value->toArray() : $value;
            }
        }

        foreach ($this->relations as $name => $value) {
            if (in_array($name, $this->hidden)) {
                continue;
            }

            $key = Str::snake($name);

            if (is_array($value)) {
                $attributes[$key] = array();

                foreach ($value as $id => $model) {
                    $attributes[$key][$id] = $model->toArray();
                }
            }

            // The value is not an array of models.
            else if ($value instanceof ArrayableInterface) {
                $attributes[$key] = $value->toArray();
            } else if (is_null($value)) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->{$offset});
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->{$offset};
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->{$offset} = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __isset($key)
    {
        return ((isset($this->attributes[$key]) || isset($this->relations[$key])) ||
                ($this->hasGetMutator($key) && ! is_null($this->getAttributeValue($key))));
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $query = $this->newQuery();

        return call_user_func_array(array($query, $method), $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        if (in_array($method, $instance->getObservableEvents())) {
            return static::registerModelEvent($method, array_shift($parameters));
        }

        return call_user_func_array(array($instance, $method), $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * When a model is being unserialized, check if it needs to be booted.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->bootIfNotBooted();
    }
}
