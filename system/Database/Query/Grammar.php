<?php

namespace Mini\Database\Query;

use Mini\Database\Connection;


class Grammar
{
    /**
     * The grammar table prefix.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * The keyword identifier wrapper format.
     *
     * @var string
     */
    protected $wrapper = '`%s`';


    /**
     * Compile a select query into SQL.
     *
     * @param  Builder  $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (is_null($query->columns)) {
            $query->columns = array('*');
        }

        return trim($this->concatenate($this->compileComponents($query)));
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param  Builder  $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sql = array();

        $selectComponents = array(
            'aggregate',
            'columns',
            'from',
            'joins',
            'wheres',
            'groups',
            'havings',
            'orders',
            'limit',
            'offset'
        );

        foreach ($selectComponents as $component) {
            if (! is_null($payload = $query->{$component})) {
                $method = 'compile' .ucfirst($component);

                $sql[$component] = call_user_func(array($this, $method), $query, $payload);
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        if ($query->distinct && ($column !== '*')) {
            $column = 'distinct ' .$column;
        }

        $function = $aggregate['function'];

        return "select {$function}({$column}) as aggregate";
    }

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  Builder  $query
     * @param  array  $columns
     * @return string
     */
    protected function compileColumns(Builder $query, $columns)
    {
        if (is_null($query->aggregate)) {
            $select = $query->distinct ? 'select distinct ' : 'select ';

            return $select .$this->columnize($columns);
        }
    }

    /**
     * Compile the "from" portion of the query.
     *
     * @param  Builder  $query
     * @param  string  $table
     * @return string
     */
    protected function compileFrom(Builder $query, $table)
    {
        return 'from ' .$this->wrapTable($table);
    }

    /**
     * Compile the "join" portions of the query.
     *
     * @param  Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
        $sql = array();

        foreach ($joins as $join) {
            $table = $this->wrapTable($join->table);

            $clauses = array();

            foreach ($join->clauses as $clause) {
                $clauses[] = $this->compileJoinConstraint($clause);
            }

            $clauses = preg_replace('/and |or /', '', implode(' ', $clauses), 1);

            $type = $join->type;

            $sql[] = "{$type} join {$table} on {$clauses}";
        }

        return implode(' ', $sql);
    }

    /**
     * Create a join clause constraint segment.
     *
     * @param  array   $clause
     * @return string
     */
    protected function compileJoinConstraint(array $clause)
    {
        $first = $this->wrap($clause['first']);

        $second = ($clause['where'] === true) ? '?' : $this->wrap($clause['second']);

        return "{$clause['boolean']} $first {$clause['operator']} $second";
    }

    /**
     * Compile the "where" portions of the query.
     *
     * @param  Builder  $query
     * @return string
     */
    protected function compileWheres(Builder $query)
    {
        $sql = array();

        if (is_null($query->wheres)) {
            return '';
        }

        foreach ($query->wheres as $where) {
            $method = 'where' .$where['type'];

            $sql[] = $where['boolean'] .' ' .call_user_func(array($this, $method), $where);
        }

        if (count($sql) > 0) {
            $sql = implode(' ', $sql);

            return 'where ' .preg_replace('/and |or /', '', $sql, 1);
        }

        return '';
    }

    /**
     * Compile a nested where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNested($where)
    {
        return '(' .substr($this->compileWheres($where['query']), 6) .')';
    }

    /**
     * Compile a where condition with a sub-select.
     *
     * @param  array   $where
     * @return string
     */
    protected function whereSub($where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) .' ' .$where['operator'] ." ({$select})";
    }

    /**
     * Compile a basic where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBasic($where)
    {
        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']) .' ' .$where['operator'] .' ' .$value;
    }

    /**
     * Compile a "between" where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereBetween($where)
    {
        $between = ($where['not'] === true) ? 'not between' : 'between';

        return $this->wrap($where['column']) ." {$between} ? and ?";
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereIn($where)
    {
        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) ." in ({$values})";
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotIn($where)
    {
        $values = $this->parameterize($where['values']);

        return $this->wrap($where['column']) ." not in ({$values})";
    }

    /**
     * Compile a where in sub-select clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereInSub($where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) ." in ({$select})";
    }

    /**
     * Compile a where not in sub-select clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotInSub($where)
    {
        $select = $this->compileSelect($where['query']);

        return $this->wrap($where['column']) ." not in ({$select})";
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNull($where)
    {
        return $this->wrap($where['column']) .' is null';
    }

    /**
     * Compile a "where not null" clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereNotNull($where)
    {
        return $this->wrap($where['column']) .' is not null';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  array  $where
     * @return string
     */
    protected function whereRaw($where)
    {
        return $where['sql'];
    }

    /**
     * Compile the "group by" portions of the query.
     *
     * @param  Builder  $query
     * @param  array  $groups
     * @return string
     */
    protected function compileGroups(Builder $query, $groups)
    {
        return 'group by ' .$this->columnize($groups);
    }

    /**
     * Compile the "having" portions of the query.
     *
     * @param  Builder  $query
     * @param  array  $havings
     * @return string
     */
    protected function compileHavings(Builder $query, $havings)
    {
        $sql = implode(' ', array_map(array($this, 'compileHaving'), $havings));

        return 'having ' .preg_replace('/and /', '', $sql, 1);
    }

    /**
     * Compile a single having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileHaving(array $having)
    {
        if ($having['type'] === 'Raw') {
            return $having['boolean'] .' ' .$having['sql'];
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     *
     * @param  array   $having
     * @return string
     */
    protected function compileBasicHaving($having)
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return 'and ' .$column .' ' .$having['operator'] .' ' .$parameter;
    }

    /**
     * Compile the "order by" portions of the query.
     *
     * @param  Builder  $query
     * @param  array  $orders
     * @return string
     */
    protected function compileOrders(Builder $query, $orders)
    {
        return 'order by ' .implode(', ', array_map(function ($order)
        {
            if (isset($order['sql'])) {
                return $order['sql'];
            }

            if (strtolower($order['column']) === 'rand()') {
                return $order['column'];
            }

            return $this->wrap($order['column']) .' ' .$order['direction'];

        }, $orders));
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit ' .(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'offset ' .(int) $offset;
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        if (! is_array(reset($values))) {
            $values = array($values);
        }

        $columns = $this->columnize(array_keys(reset($values)));

        $parameters = $this->parameterize(reset($values));

        $value = array_fill(0, count($values), "({$parameters})");

        $parameters = implode(', ', $value);

        return "insert into {$table} ({$columns}) values {$parameters}";
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = array();

        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) .' = ' .$this->parameter($value);
        }

        $columns = implode(', ', $columns);

        if (isset($this->joins)) {
            $joins = ' ' .$this->compileJoins($query, $query->joins);
        } else {
            $joins = '';
        }

        $where = $this->compileWheres($query);

        $sql = trim("update {$table}{$joins} set {$columns} {$where}");

        if (isset($this->orders)) {
            $sql .= ' '.$this->compileOrders($query, $query->orders);
        }

        if (isset($this->limit)) {
            $sql .= ' '.$this->compileLimit($query, $query->limit);
        }

        return rtrim($sql);
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        $sql = trim("delete from {$table} " .$where);

        if (isset($query->limit)) {
            $sql .= ' ' .$this->compileLimit($query, $query->limit);
        }

        return rtrim($sql);
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @param  Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return array('truncate ' .$this->wrapTable($query->from) => array());
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param  string  $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ($this->isExpression($table)) {
            return $this->getValue($table);
        }

        return $this->wrap($this->getTablePrefix() .$table);
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            $segments = explode(' ', $value);

            return $this->wrap($segments[0]) .' as ' .$this->wrap($segments[2]);
        }

        $wrapped = array();

        $segments = explode('.', $value);

        foreach ($segments as $key => $segment) {
            if (($key == 0) && (count($segments) > 1)) {
                $wrapped[] = $this->wrapTable($segment);
            } else {
                $wrapped[] = $this->wrapValue($segment);
            }
        }

        return implode('.', $wrapped);
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        return ($value !== '*') ? sprintf($this->wrapper, $value) : $value;
    }

    /**
     * Concatenate an array of segments, removing empties.
     *
     * @param  array   $segments
     * @return string
     */
    protected function concatenate($segments)
    {
        return implode(' ', array_filter($segments, function ($value)
        {
            return ((string) $value !== '');
        }));
    }

    /**
     * Create query parameter place-holders for an array.
     *
     * @param  array   $values
     * @return string
     */
    public function parameterize(array $values)
    {
        return implode(', ', array_map(array($this, 'parameter'), $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Convert an array of column names into a delimited string.
     *
     * @param  array   $columns
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map(array($this, 'wrap'), $columns));
    }

    /**
     * Get the value of a raw expression.
     *
     * @param  Builder $expression
     * @return string
     */
    public function getValue($expression)
    {
        return $expression->getValue();
    }

    /**
     * Determine if the given value is a raw expression.
     *
     * @param  mixed $value
     * @return bool
     */
    public function isExpression($value)
    {
        return $value instanceof Expression;
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the grammar's table prefix.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Set the grammar's table prefix.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

    /**
     * Get the grammar's table prefix.
     *
     * @return string
     */
    public function getWrapper()
    {
        return $this->wrapper;
    }

    /**
     * Set the grammar's table prefix.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setWrapper($wrapper)
    {
        $this->wrapper = $wrapper;

        return $this;
    }
}
