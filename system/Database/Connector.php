<?php

namespace Mini\Database;

use PDO;


class Connector
{
    /**
     * The default PDO connection options.
     *
     * @var array
     */
    protected $options = array(
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    );


    /**
     * Establish a database connection.
     *
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        $connection = $this->createConnection($this->getDsn($config), $config, $options);

        // Setup the PDO connection.
        $query = "set names '{$config['charset']}'";

        if (! is_null($collation = array_get($config, 'collation'))) {
            $query .= " collate '{$collation}'";
        }

        $connection->prepare($query)->execute();

        if (isset($config['strict']) && ($config['strict'] === true)) {
            $query = "set session sql_mode='STRICT_ALL_TABLES'";

            $connection->prepare($query)->execute();
        }

        return $connection;
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param  array   $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        extract($config);

        $dsn = "mysql:host={$hostname};dbname={$database}";

        if (isset($config['port'])) {
            $dsn .= ";port={$port}";
        }

        if (isset($config['unix_socket'])) {
            $dsn .= ";unix_socket={$config['unix_socket']}";
        }

        return $dsn;
    }

    /**
     * Get the PDO options based on the configuration.
     *
     * @param  array  $config
     * @return array
     */
    public function getOptions(array $config)
    {
        $options = array_get($config, 'options', array());

        return array_diff_key($this->options, $options) + $options;
    }

    /**
     * Create a new PDO connection.
     *
     * @param  string  $dsn
     * @param  array   $config
     * @param  array   $options
     * @return PDO
     */
    public function createConnection($dsn, array $config, array $options)
    {
        $username = array_get($config, 'username');
        $password = array_get($config, 'password');

        return new PDO($dsn, $username, $password, $options);
    }
}
