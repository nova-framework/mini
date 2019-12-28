<?php

namespace Mini\Foundation\Exceptions;

use Mini\Auth\AuthenticationException;
use Mini\Container\Container;
use Mini\Http\Request;
use Mini\Http\Response;

use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

use Psr\Log\LoggerInterface;

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
     * Whether or not we are in DEBUG mode.
     */
    protected $debug = false;

    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = array();


    /**
     * Create a new Exceptions Handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        //
        $this->debug = $container['config']->get('app.debug', true);
    }

    /**
     * Handle an uncaught exception from the application.
     *
     * @param  \Mini\Http\Request
     * @param  \Exception|\Throwable  $exception
     * @return void
     */
    public function handleException(Request $request, $exception)
    {
        if (! $exception instanceof Exception) {
            $exception = new FatalThrowableError($exception);
        }

        $this->report($exception);

        return $this->render($exception, $request);
    }

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if (in_array(get_class($exception), $this->dontReport)) {
            return;
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        }
        catch (Exception $ex) {
            throw $exception; // Throw the original exception
        }

        $logger->error($exception);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Exception  $e
     * @param  \Mini\Http\Request
     * @return void
     */
    public function render(Exception $exception, Request $request)
    {
        if ($exception instanceof AuthenticationException) {
            return $this->unauthenticated($request, $exception);
        }

        $exception = FlattenException::create($exception);

        $handler = new SymfonyExceptionHandler($this->debug);

        return new Response(
            $handler->getHtml($exception), $exception->getStatusCode(), $exception->getHeaders()
        );
    }

    /**
     * Render an exception for console.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function renderForConsole(Exception $exception)
    {
        $message = sprintf(
            "%s: %s in file %s on line %d%s\n",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        echo $message;
    }
}
