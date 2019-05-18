<?php

namespace System\Console\Scheduling;

use System\Container\Container;
use System\Foundation\Application;
use System\Mail\Mailer;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

use Carbon\Carbon;
use Cron\CronExpression;

use Closure;
use LogicException;


class Event
{
    /**
     * The command string.
     *
     * @var string
     */
    public $command;

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * * *';

    /**
     * The user the command should run as.
     *
     * @var string
     */
    public $user;

    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * The filter callback.
     *
     * @var \Closure
     */
    protected $filter;

    /**
     * The reject callback.
     *
     * @var \Closure
     */
    protected $reject;

    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    public $output = '/dev/null';

    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    protected $shouldAppendOutput = false;

    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array
     */
    protected $beforeCallbacks = array();

    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected $afterCallbacks = array();

    /**
     * The human readable description of the event.
     *
     * @var string
     */
    public $description;


    /**
     * Create a new event instance.
     *
     * @param  string  $command
     * @return void
     */
    public function __construct($command)
    {
        $this->command = $command;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string
     */
    protected function getDefaultOutput()
    {
        return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
    }

    /**
     * Run the given event.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    public function run(Container $container)
    {
        if ((count($this->afterCallbacks) > 0) || (count($this->beforeCallbacks) > 0)) {
            $this->runCommandInForeground($container);
        } else {
            $this->runCommandInBackground();
        }
    }

    /**
     * Run the command in the background using exec.
     *
     * @return void
     */
    protected function runCommandInBackground()
    {
        chdir(base_path());

        $command = $this->buildCommand();

        exec($command);
    }

    /**
     * Run the command in the foreground.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    protected function runCommandInForeground(Container $container)
    {
        $this->callBeforeCallbacks($container);

        $command = $this->buildCommand();

        //
        $process = new Process(trim($command, '& '), base_path(), null, null, null);

        $process->run();

        $this->callAfterCallbacks($container);
    }

    /**
     * Call all of the "before" callbacks for the event.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    protected function callBeforeCallbacks(Container $container)
    {
        foreach ($this->beforeCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Call all of the "after" callbacks for the event.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    protected function callAfterCallbacks(Container $container)
    {
        foreach ($this->afterCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = $this->compileCommand();

        if (! is_null($this->user) && ! windows_os()) {
            return 'sudo -u ' .$this->user .' -- sh -c \'' .$command .'\'';
        }

        return $command;
    }

    /**
     * Build a command string with mutex.
     *
     * @return string
     */
    protected function compileCommand()
    {
        $output = ProcessUtils::escapeArgument($this->output);

        $redirect = $this->shouldAppendOutput ? ' >> ' : ' > ';

        if (! $this->withoutOverlapping) {
            return $this->command .$redirect .$output .' 2>&1 &';
        }

        $mutexPath = $this->mutexPath();

        if (! windows_os()) {
            return '(touch ' .$mutexPath .'; ' .$this->command .'; rm ' .$mutexPath .')' .$redirect .$output .' 2>&1 &';
        } else {
            return '(echo \'\' > "' .$mutexPath .'" & ' .$this->command .' & del "'.$mutexPath .'")' .$redirect .$output .' 2>&1 &';
        }
    }

    /**
     * Get the mutex path for the scheduled command.
     *
     * @return string
     */
    protected function mutexPath()
    {
        $hash = sha1($this->expression .$this->command);

        return storage_path('schedule-' .$hash .'.lock');
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @param  \System\Foundation\Application  $app
     * @return bool
     */
    public function isDue(Application $app)
    {
        return $this->expressionPasses() && $this->filtersPass($app);
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses()
    {
        $date = Carbon::now();

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @param  \System\Foundation\Application  $app
     * @return bool
     */
    protected function filtersPass(Application $app)
    {
        if (isset($this->filter) && ! $app->call($this->filter)) {
            return false;
        } else if (isset($this->reject) && $app->call($this->reject)) {
            return false;
        }

        return true;
    }

    /**
     * The Cron expression representing the event's frequency.
     *
     * @param  string  $expression
     * @return $this
     */
    public function cron($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->cron('0 * * * * *');
    }

    /**
     * Schedule the event to run daily.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->cron('0 0 * * * *');
    }

    /**
     * Schedule the event to run weekly.
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->cron('0 0 * * 0 *');
    }

    /**
     * Schedule the event to run monthly.
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->cron('0 0 1 * * *');
    }

    /**
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->cron('* * * * * *');
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->cron('*/5 * * * * *');
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->cron('*/10 * * * * *');
    }

    /**
     * Schedule the event to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->cron('0,30 * * * * *');
    }

    /**
     * Set which user the command should run as.
     *
     * @param  string  $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @return $this
     */
    public function withoutOverlapping()
    {
        $this->withoutOverlapping = true;

        return $this->skip(function ()
        {
            return file_exists($this->mutexPath());
        });
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function when(Closure $callback)
    {
        $this->filter = $callback;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function skip(Closure $callback)
    {
        $this->reject = $callback;

        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param  string  $location
     * @param  bool  $append
     * @return $this
     */
    public function sendOutputTo($location, $append = false)
    {
        $this->output = $location;

        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param  string  $location
     * @return $this
     */
    public function appendOutputTo($location)
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * Register a callback to be called before the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function before(Closure $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function after(Closure $callback)
    {
        return $this->then($callback);
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function then(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function name($description)
    {
        return $this->description($description);
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return $this->buildCommand();
    }

    /**
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }
}
