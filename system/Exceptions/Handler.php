<?php

namespace Mini\Exceptions;

use Mini\Container\Container;
use Mini\Config\Store as Config;
use Mini\Http\Request;

use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpKernel\Exception\HttpException;

use ErrorException;
use Exception;
use Throwable;


class Handler
{
    /**
     * The Container instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;


    /**
     * Create a new Exceptions Handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register the exception / error handlers for the application.
     *
     * @return \Mini\Exceptions\Handler
     */
    public function register()
    {
        set_error_handler(array($this, 'handleError'));

        set_exception_handler(array($this, 'handleException'));

        register_shutdown_function(array($this, 'handleShutdown'));

        return $this;
    }

    /**
     * Convert a PHP error to an ErrorException.
     *
     * @param  int  $level
     * @param  string  $message
     * @param  string  $file
     * @param  int  $line
     * @param  array  $context
     * @return void
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = array())
    {
        if (error_reporting() & ($level > 0)) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * @param  \Exception|\Throwable  $e
     * @return void
     */
    public function handleException($e)
    {
        if (! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        $handler = $this->container->make('Mini\Foundation\Exceptions\HandlerInterface');

        if (! $e instanceof HttpException) {
            $handler->report($e);
        }

        if ($this->container->runningInConsole()) {
            return $handler->renderForConsole($e);
        }

        if ($this->container->bound('request')) {
            $request = $this->container->make('request');
        } else {
            $request = Request::createFromGlobals();
        }

        $handler->render($e, $request)->send();
    }

    /**
     * Handle the PHP shutdown event.
     *
     * @return void
     */
    public function handleShutdown()
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalExceptionFromError($error));
        }
    }

    /**
     * Create a new fatal exception instance from an error array.
     *
     * @param  array  $error
     * @param  int|null  $traceOffset
     * @return \ErrorException
     */
    protected function fatalExceptionFromError(array $error)
    {
        return new ErrorException(
            $error['message'], $error['type'], 0, $error['file'], $error['line']
        );
    }

    /**
     * Determine if the error type is fatal.
     *
     * @param  int  $type
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE));
    }
}
