<?php

namespace Mini\Routing;

use Mini\Foundation\Exceptions\HandlerInterface as ExceptionHandler;
use Mini\Http\Request;
use Mini\Foundation\Pipeline as BasePipeline;

use Closure;
use Exception;
use Throwable;


class Pipeline extends BasePipeline
{
    /**
     * Get the final piece of the Closure onion.
     *
     * @param  \Closure  $callback
     * @return \Closure
     */
    protected function prepareDestination(Closure $callback)
    {
        return function ($passable) use ($callback)
        {
            try {
                return call_user_func($callback, $passable);
            }
            catch (Exception | Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     *
     * @param  \Closure  $stack
     * @param  mixed  $pipe
     * @return \Closure
     */
    protected function createSlice($stack, $pipe)
    {
        return function ($passable) use ($stack, $pipe)
        {
            try {
                return $this->call($pipe, $passable, $stack);
            }
            catch (Exception | Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Handle the given exception.
     *
     * @param  mixed  $passable
     * @param  \Exception  $exception
     * @return mixed
     *
     * @throws \Exception
     */
    protected function handleException($passable, $exception)
    {
        if (! $this->container->bound(ExceptionHandler::class) || (! $passable instanceof Request)) {
            throw $e;
        }

        $handler = $this->container->make(ExceptionHandler::class);

        return $handler->handleException($passable, $exception);
    }
}
