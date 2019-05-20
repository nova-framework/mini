<?php

namespace Mini\Console;

use Mini\Container\Container;
use Mini\Console\Command;
use Mini\Events\Dispatcher;

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

use Exception;


class Application extends SymfonyApplication
{
    /**
     * The Mini MVC Framework version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The framework application instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;

    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;


    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public static function version()
    {
        return static::VERSION;
    }

    /**
     * Create and boot a new Console application.
     *
     * @param  \Nova\Foundation\Application  $app
     * @return \Nova\Console\Application
     */
    public static function start($app)
    {
        return static::make($app)->boot();
    }

    /**
     * Create a new Console application.
     *
     * @param  \Nova\Foundation\Application  $app
     * @return \Nova\Console\Application
     */
    public static function make($app)
    {
        $app->boot();

        $console = with($console = new static('Mini MVC Framework', $app::VERSION))
            ->setContainer($app)
            ->setAutoExit(false);

        $app->instance('forge', $console);

        return $console;
    }

    /**
     * Boot the Console application.
     *
     * @return $this
     */
    public function boot()
    {
        $path = $this->container['path'] .DS .'Console' .DS .'Bootstrap.php';

        if (is_readable($path)) require $path;

        if (isset($this->container['events'])) {
            $events = $this->container['events'];

            $events->dispatch('forge.start', array($this));
        }

        return $this;
    }


    /**
     * Run the console application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        try {
            return $this->run($input, $output);
        }
        catch (Exception $e) {
            $this->manageException($output, $e);

            return 1;
        }
        catch (Throwable $e) {
            $this->manageException($output, new FatalThrowableError($e));

            return 1;
        }
    }

    /**
     * Report and render the given exception.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  \Exception  $e
     * @return void
     */
    protected function manageException(OutputInterface $output, Exception $e)
    {
        $handler = $this->getExceptionHandler();

        $handler->report($e);

        $handler->renderForConsole($output, $e);
    }

    /**
     * Run an Nova console command by name.
     *
     * @param  string  $command
     * @param  array   $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function call($command, array $parameters = array(), OutputInterface $output = null)
    {
        $parameters['command'] = $command;

        //
        $this->lastOutput = new BufferedOutput;

        $this->setCatchExceptions(false);

        $result = $this->run(new ArrayInput($parameters), $this->lastOutput);

        $this->setCatchExceptions(true);

        return $result;
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->lastOutput ? $this->lastOutput->fetch() : '';
    }

    /**
     * Add a command to the console.
     *
     * @param  \Symfony\Component\Console\Command\Command  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function add(SymfonyCommand $command)
    {
        if ($command instanceof Command) {
            $command->setContainer($this->container);
        }

        return parent::add($command);
    }

    /**
     * Add a command, resolving through the application.
     *
     * @param  string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function resolve($command)
    {
        return $this->add($this->container[$command]);
    }

    /**
     * Resolve an array of commands through the application.
     *
     * @param  array|mixed  $commands
     * @return void
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        foreach ($commands as $command)  {
            $this->resolve($command);
        }
    }

    /**
     * Get the default input definitions for the applications.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }

    /**
     * Set the Laravel application instance.
     *
     * @param  \Mini\Foundation\Application  $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set whether the Console app should auto-exit when done.
     *
     * @param  bool  $boolean
     * @return $this
     */
    public function setAutoExit($boolean)
    {
        parent::setAutoExit($boolean);

        return $this;
    }

}
