<?php

namespace Mini\Foundation\Exceptions;

use Mini\Http\Request;

use Exception;


interface HandlerInterface
{

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e);

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Exception  $e
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Http\Response
     */
    public function render(Exception $e, Request $request);
}
