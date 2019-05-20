<?php

namespace Mini\Foundation\Console;

use Mini\Console\Command;

use Symfony\Component\Console\Input\InputOption;


class ServeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Serve the Application on the PHP development server";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->checkPhpVersion();

        chdir($this->container['path.base']);

        $host = $this->input->getOption('host');

        $port = $this->input->getOption('port');

        $public = $this->container['path.public'];

        $this->info("Mini MVC Framework development Server started on http://{$host}:{$port}");

        passthru('"'.PHP_BINARY.'"'." -S {$host}:{$port} -t \"{$public}\" server.php");
    }

    /**
     * Check the current PHP version is >= 5.4.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function checkPhpVersion()
    {
        if (version_compare(PHP_VERSION, '7.1.3', '<')) {
            throw new \Exception('This PHP binary is not version 7.1.3 or greater.');
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on.', 'localhost'),
            array('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on.', 8000),
        );
    }

}
