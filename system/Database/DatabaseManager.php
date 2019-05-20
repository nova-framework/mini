<?php

namespace Mini\Database;

use Mini\Container\Container;
use Mini\Database\Connection;

use InvalidArgumentException;


class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * The application instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;

    /**
     * The Connection instances.
     *
     * @var \Mini\Database\Connection[]
     */
    protected $instances = array();


    /**
     * Create a new database manager instance.
     *
     * @param  \Nova\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function connection($name = null)
    {
        if (is_null($name)) {
            $name = $this->getDefaultConnection();
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $config = $this->getConfig($name);

        // Create a new Connection instance.
        $this->instances[$name] = $connection = new Connection($config);

        // Set the fetch mode on Connection.
        $fetchMode = $this->container['config']['database.fetch'];

        $connection->setFetchMode($fetchMode);

        return $connection;
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getConfig($name)
    {
        $connections = $this->container['config']['database.connections'];

        if (is_null($config = array_get($connections, $name))) {
            throw new InvalidArgumentException("Database [$name] not configured.");
        }

        return $config;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->container['config']['database.default'];
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultConnection($name)
    {
        $this->container['config']['database.default'] = $name;
    }

    public function __call($method, $parameters)
    {
        $instance = $this->connection();

        return call_user_func_array(array($instance, $method), $parameters);
    }
}
