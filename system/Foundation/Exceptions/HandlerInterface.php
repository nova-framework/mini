<?php

namespace System\Foundation\Exceptions;

use System\Http\Request;

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
     * @param  \System\Http\Request  $request
     * @return \System\Http\Response
     */
    public function render(Exception $e, Request $request);
}
