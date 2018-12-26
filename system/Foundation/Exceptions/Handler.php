<?php

namespace System\Foundation\Exceptions;

use System\Auth\AuthenticationException;
use System\Container\Container;
use System\Http\Request;
use System\Http\Response;

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
     * @var \System\Container\Container
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
     * @param  \System\Http\Request
     * @param  \Exception|\Throwable  $e
     * @return void
     */
    public function handleException(Request $request, $e)
    {
        if (! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        $className = get_class($e);

        if (! in_array($className, $this->dontReport)) {
            $this->report($e);
        }

        return $this->render($e, $request);
    }

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        try {
            $logger = $this->container->make(LoggerInterface::class);
        }
        catch (Exception $ex) {
            throw $e; // Throw the original exception
        }

        $logger->error($e);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Exception  $e
     * @param  \System\Http\Request
     * @return void
     */
    public function render(Exception $e, Request $request)
    {
        if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        }

        $e = FlattenException::create($e);

        $handler = new SymfonyExceptionHandler($this->debug);

        return new Response($handler->getHtml($e), $e->getStatusCode(), $e->getHeaders());
    }

    /**
     * Render an exception for console.
     *
     * @param  \System\Http\Request
     * @param  \Exception  $e
     * @return void
     */
    public function renderForConsole(Exception $e)
    {
        $message = sprintf(
            "%s: %s in file %s on line %d%s\n",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        echo $message;
    }
}
