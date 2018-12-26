<?php

namespace System\Database\Query;

use System\Database\Connection;
use System\Pagination\Paginator;
use System\Pagination\SimplePaginator;

use PDO;
use Closure;
use InvalidArgumentException;


class Builder
{
    /**
     * The database connection instance.
     *
     * @var \System\Database\Connection
     */
    protected $connection;

    /**
     * The database query grammar instance.
     *
     * @var \System\Database\Query\Grammar
     */
    protected $grammar;

    /**
     * The current query value bindings.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * An aggregate function and column to be run.
     *
     * @var array
     */
    public $aggregate;

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns;

    /**
     * Indicates if the query returns distinct results.
     *
     * @var bool
     */
    public $distinct = false;

    /**
     * The table which the query is targeting.
     *
     * @var string
     */
    public $from;

    /**
     * The table joins for the query.
     *
     * @var array
     */
    public $joins;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres;

    /**
     * The groupings for the query.
     *
     * @var array
     */
    public $groups;

    /**
     * The having constraints for the query.
     *
     * @var array
     */
    public $havings;

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public $orders;

    /**
     * The maximum number of records to return.
     *
     * @var int
     */
    public $limit;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public $offset;

    /**
     * The backups of fields while doing a pagination count.
     *
     * @var array
     */
    protected $backups = array();

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = array(
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
    );


    /**
     * Create a new query instance.
     *
     * @return void
     */
    public function __construct(Connection $connection, Grammar $grammar)
    {
        $this->connection = $connection;

        $this->grammar = $grammar;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array  $columns
     * @return Builder
     */
    public function select($columns = array('*'))
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Add a new "raw" select expression to the query.
     *
     * @param  string  $expression
     * @param  binding  $columns
     * @return Builder
     */
    public function selectRaw($expression, $bindings = array())
    {
        $this->columns = array(
            new Expression($expression)
        );

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return Builder
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $table
     * @return Builder
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Add a join clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @param  bool  $where
     * @return Builder
     */
    public function join($table, $one, $operator = null, $two = null, $type = 'inner', $where = false)
    {
        if ($one instanceof Closure) {
            $this->joins[] = $join = new JoinClause($this, $type, $table);

            call_user_func($one, $join);
        } else {
            $join = new JoinClause($this, $type, $table);

            $this->joins[] = $join->on($one, $operator, $two, 'and', $where);
        }

        return $this;
    }

    /**
     * Add a "join where" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @param  string  $type
     * @return Builder
     */
    public function joinWhere($table, $one, $operator, $two, $type = 'inner')
    {
        return $this->join($table, $one, $operator, $two, $type, true);
    }

    /**
     * Add a left join to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $second
     * @return Builder
     */
    public function leftJoin($table, $first, $operator = null, $second = null)
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a "join where" clause to the query.
     *
     * @param  string  $table
     * @param  string  $first
     * @param  string  $operator
     * @param  string  $two
     * @return Builder
     */
    public function leftJoinWhere($table, $one, $operator, $two)
    {
        return $this->joinWhere($table, $one, $operator, $two, 'left');
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return Builder
     *
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            return $this->whereNested(function ($query) use ($column)
            {
                foreach ($column as $key => $value) {
                    $query->where($key, '=', $value);
                }

            }, $boolean);
        }

        if (func_num_args() == 2) {
            list($value, $operator) = array($operator, '=');
        } else if ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException("Value must be provided.");
        }

        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (! in_array(strtolower($operator), $this->operators, true)) {
            list ($value, $operator) = array($operator, '=');
        }

        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '=');
        }

        $type = 'Basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof Expression) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return Builder
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return $isOperator && ($operator != '=') && is_null($value);
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @param  string  $boolean
     * @return Builder
     */
    public function whereRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'Raw';

        $this->wheres[] = compact('type', 'sql', 'boolean');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Add a raw or where clause to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @return Builder
     */
    public function orWhereRaw($sql, array $bindings = array())
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'Between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'not');

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add an or where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return Builder
     */
    public function orWhereBetween($column, array $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return Builder
     */
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an or where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @return Builder
     */
    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add a nested where statement to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return Builder
     */
    public function whereNested(Closure $callback, $boolean = 'and')
    {
        $query = $this->newQuery()->from($this->from);

        call_user_func($callback, $query);

        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->mergeBindings($query);
        }

        return $this;
    }

    /**
     * Add a full sub-select to the query.
     *
     * @param  string   $column
     * @param  string   $operator
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return Builder
     */
    protected function whereSub($column, $operator, Closure $callback, $boolean)
    {
        $type = 'Sub';

        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = compact('type', 'column', 'operator', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return Builder
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return Builder
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return Builder
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @return Builder
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a where in with a sub-select to the query.
     *
     * @param  string   $column
     * @param  \Closure $callback
     * @param  string   $boolean
     * @param  bool     $not
     * @return Builder
     */
    protected function whereInSub($column, Closure $callback, $boolean, $not)
    {
        $type = $not ? 'NotInSub' : 'InSub';

        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = compact('type', 'column', 'query', 'boolean');

        $this->mergeBindings($query);

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return Builder
     */
    public function whereNull($column, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string  $column
     * @return Builder
     */
    public function orWhereNull($column)
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return Builder
     */
    public function whereNotNull($column, $boolean = 'and')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  string  $column
     * @return Builder
     */
    public function orWhereNotNull($column)
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add a "having" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  string  $value
     * @return Builder
     */
    public function having($column, $operator = null, $value = null)
    {
        $type = 'Basic';

        $this->havings[] = compact('type', 'column', 'operator', 'value');

        $this->bindings[] = $value;

        return $this;
    }
    /**
     * Add a raw having clause to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @param  string  $boolean
     * @return Builder
     */
    public function havingRaw($sql, array $bindings = array(), $boolean = 'and')
    {
        $type = 'Raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }
    /**
     * Add a raw or having clause to the query.
     *
     * @param  string  $sql
     * @param  array   $bindings
     * @return Builder
     */
    public function orHavingRaw($sql, array $bindings = array())
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $type = 'Basic';

        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';

        $this->orders[] = compact('type', 'column', 'direction');

        return $this;
    }

    /**
     * Add a raw "order by" clause to the query.
     *
     * @param  string  $sql
     * @param  array  $bindings
     * @return Builder
     */
    public function orderByRaw($sql, $bindings = array())
    {
        $type = 'Raw';

        $this->orders[] = compact('type', 'sql');

        $this->bindings = array_merge($this->bindings, $bindings);

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return Builder
     */
    public function offset($value)
    {
        $this->offset = max(0, $value);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return Builder
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return Builder
     */
    public function limit($value)
    {
        if ($value > 0) {
            $this->limit = $value;
        }

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return Builder
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * Pluck a single column's value from the first result of a query.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = $this->first(array($column));

        if (! is_null($result)) {
            $result = (array) $result;

            return (count($result) > 0) ? reset($result) : null;
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
        $columns = is_null($key) ? array($column) : array($column, $key);

        $results = $this->get($columns);

        return array_pluck($results, $column, $key);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array   $columns
     * @return mixed
     */
    public function first($columns = array('*'))
    {
        $results = $this->take(1)->get($columns);

        return (count($results) > 0) ? reset($results) : null;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        if (is_null($this->columns)) {
            $this->columns = $columns;
        }

        return $this->connection->select($this->toSql(), $this->bindings);
    }

    /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Nova\Pagination\Paginator
     */
    public function paginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $total = $this->getPaginationCount();

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
     * @return \Nova\Pagination\SimplePaginator
     */
    public function simplePaginate($perPage = 15, $columns = array('*'), $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $results = $this->skip(($page - 1) * $perPage)->take($perPage + 1)->get($columns);

        return new SimplePaginator($results, $perPage, $page, array(
            'path'     => Paginator::resolveCurrentPath($pageName),
            'pageName' => $pageName,
        ));
    }

    /**
     * Get the count of the total records for pagination.
     *
     * @return int
     */
    public function getPaginationCount()
    {
        foreach (array('orders', 'limit', 'offset') as $field) {
            $this->backups[$field] = $this->{$field};

            $this->{$field} = null;
        }

        $total = $this->count();

        foreach (array('orders', 'limit', 'offset') as $field) {
            $this->{$field} = $this->backups[$field];
        }

        $this->backups = array();

        return $total;
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return ($this->count() > 0);
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @param  string  $column
     * @return int
     */
    public function count($column = '*')
    {
        return (int) $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the minimum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the maximum value of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the sum of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Retrieve the average of the values of a given column.
     *
     * @param  string  $column
     * @return mixed
     */
    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, array($column));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        $results = $this->get($columns);

        $this->aggregate = null;

        $this->columns = $previousColumns;

        if (! empty($results)) {
            $result = reset($results);

            $result = array_change_key_case((array) $result);

            return $result['aggregate'];
        }
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        if (! is_array(reset($values))) {
            $values = array($values);
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $bindings = array();

        foreach ($values as $record) {
            foreach ($record as $value) {
                $bindings[] = $value;
            }
        }

        $sql = $this->grammar->compileInsert($this, $values);

        $bindings = $this->cleanBindings($bindings);

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string|null   $keyName
     * @return int
     */
    public function insertGetId(array $values, $keyName = null)
    {
        $sql = $this->grammar->compileInsert($this, $values);

        $values = $this->cleanBindings($values);

        $this->connection->insert($sql, $values);

        $id = $this->connection->getPdo()->lastInsertId($keyName);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $bindings = array_values(array_merge($values, $this->bindings));

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings($bindings));
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
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped + $amount")), $extra);

        return $this->update($columns);
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
        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge(array($column => $this->raw("$wrapped - $amount")), $extra);

        return $this->update($columns);
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        if (! is_null($id)) {
            $this->where('id', '=', $id);
        }

        $sql = $this->grammar->compileDelete($this);

        return $this->connection->delete($sql, $this->bindings);
    }

    /**
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        $statements = $this->grammar->compileTruncate($this);

        foreach ($statements as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  dynamic $columns
     * @return Builder
     */
    public function groupBy()
    {
        $this->groups = array_merge((array) $this->groups, func_get_args());

        return $this;
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->grammar);
    }

    /**
     * Merge an array of bindings into our bindings.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function mergeBindings(Builder $query)
    {
        $this->bindings = array_values(array_merge($this->bindings, $query->bindings));

        return $this;
    }

    /**
     * Remove all of the expressions from a list of bindings.
     *
     * @param  array  $bindings
     * @return array
     */
    protected function cleanBindings(array $bindings)
    {
        return array_values(array_filter($bindings, function ($binding)
        {
            return ! $binding instanceof Expression;
        }));
    }

    /**
     * Create a raw database expression.
     *
     * @param  mixed $value
     * @return \System\Database\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * Add a binding to the query.
     *
     * @param  mixed  $value
     * @return Builder
     */
    public function addBinding($value)
    {
        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Get the Connector instance.
     *
     * @return \System\Database\Query\Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }
}
