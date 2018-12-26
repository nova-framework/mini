<?php

namespace System\Session;

use System\Support\Manager;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;


class SessionManager extends Manager
{
    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->buildSession(parent::callCustomCreator($driver));
    }

    /**
     * Create an instance of the "array" session driver.
     *
     * @return \System\Session\Store
     */
    protected function createArrayDriver()
    {
        return $this->buildSession(
            new NullSessionHandler()
        );
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \System\Session\Store
     */
    protected function createFileDriver()
    {
        $path = $this->app['config']['session.files'];

        $lifetime = $this->app['config']['session.lifetime'];

        return $this->buildSession(
            new FileSessionHandler($this->app['files'], $path, $lifetime)
        );
    }

    /**
     * Create an instance of the database session driver.
     *
     * @return \System\Session\Store
     */
    protected function createDatabaseDriver()
    {
        $connection = $this->getDatabaseConnection();

        $table = $this->app['config']['session.table'];

        return $this->buildSession(
            new DatabaseSessionHandler($connection, $table)
        );
    }

    /**
     * Get the database connection for the database driver.
     *
     * @return \System\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->app['config']['session.connection'];

        return $this->app['db']->connection($connection);
    }

    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \System\Session\Store
     */
    protected function buildSession($handler)
    {
        return new Store($this->app['config']['session.cookie'], $handler);
    }

    /**
     * Get the session configuration.
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->app['config']['session'];
    }

    /**
     * Get the default session driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['session.driver'];
    }

    /**
     * Set the default session driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['session.driver'] = $name;
    }

}
