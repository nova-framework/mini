<?php

namespace App\Platform\Exceptions;

use Mini\Auth\AuthenticationException;
use Mini\Foundation\Exceptions\Handler as BaseHandler;
use Mini\Foundation\Exceptions\HandlerInterface;
use Mini\Http\Request;
use Mini\Session\TokenMismatchException;
use Mini\Support\Facades\Config;
use Mini\Support\Facades\Redirect;
use Mini\Support\Facades\Response;
use Mini\Support\Facades\View;

use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Exception;


class Handler extends BaseHandler implements HandlerInterface
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = array(
        'Mini\Session\TokenMismatchException',
        'Mini\Auth\AuthenticationException,',
        'Symfony\Component\HttpKernel\Exception\HttpException',
    );

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = array(
        'password',
        'password_confirmation',
    );


    public function report(Exception $e)
    {
        return parent::report($e);
    }

    /**
     * Render an exception as an HTTP response and send it.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function render(Exception $e, Request $request)
    {
        if ($e instanceof TokenMismatchException) {
            return Redirect::back()
                ->withInput($request->except($this->dontFlash))
                ->with('danger', 'Validation Token has expired. Please try again!');
        }

        //
        else if ($e instanceof HttpException) {
            return $this->renderHttpException($e, $request);
        } else if (! $this->debug) {
            $exception = new HttpException(500, 'Internal Server Error');

            return $this->renderHttpException($exception, $request);
        }

        return parent::render($e, $request);
    }

    /**
     * Render the given HttpException.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpException  $e
     * @param  \Mini\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpException $e, Request $request)
    {
        $e = FlattenException::create($e, $code = $e->getStatusCode());

        if ($this->isAjaxRequest($request)) {
            return Response::json($e->toArray(), $code, $e->getHeaders());
        }

        // Not an AJAX request.
        else if (! View::exists('Errors/' .$code)) {
            return;
        }

        $view = View::make('Layouts/Default')
            ->shares('title', 'Error ' .$code)
            ->nest('content', 'Errors/' .$code, array('exception' => $e));

        return Response::make($view->render(), $code);
    }

    /**
     * Returns true if the given Request instance is AJAX.
     *
     * @param  \Mini\Http\Request  $request
     * @return bool
     */
    protected function isAjaxRequest(Request $request)
    {
        return ($request->ajax() || $request->wantsJson());
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Nova\Http\Request  $request
     * @param  \Nova\Auth\AuthenticationException  $exception
     * @return \Nova\Http\Response
     */
    protected function unauthenticated(Request $request, AuthenticationException $exception)
    {
        if ($this->isAjaxRequest($request)) {
            return Response::json(array('error' => 'Unauthenticated.'), 401);
        }

        $guards = $exception->guards();

        // We will use the first guard.
        $guard = array_shift($guards);

        $path = Config::get("auth.guards.{$guard}.paths.authorize", 'login');

        return Redirect::guest($path)->with('warning', 'Please sign in to access this page.');
    }
}
