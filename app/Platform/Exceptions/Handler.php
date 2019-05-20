<?php

namespace App\Platform\Exceptions;

use Mini\Auth\AuthenticationException;
use Mini\Foundation\Exceptions\Handler as BaseHandler;
use Mini\Foundation\Exceptions\HandlerInterface;
use Mini\Http\Exceptions\HttpException;
use Mini\Http\Request;
use Mini\Session\TokenMismatchException;
use Mini\Support\Facades\Config;
use Mini\Support\Facades\Redirect;
use Mini\Support\Facades\Response;
use Mini\Support\Facades\View;

use Symfony\Component\Debug\Exception\FlattenException;

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

        // Http Error Pages or not Debug enabled.
        else if (($e instanceof HttpException) || ! $this->debug) {
            $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

            if ($request->ajax() || $request->wantsJson()) {
                $e = FlattenException::create($e, $code);

                return Response::json($e->toArray(), $code, $e->getHeaders());
            }

            // Not an AJAX request.
            else if (View::exists('Errors/' .$code)) {
                $view = View::make('Layouts/Default')
                    ->shares('title', 'Error ' .$code)
                    ->nest('content', 'Errors/' .$code, array('exception' => $e));

                return Response::make($view->render(), $code);
            }
        }

        return parent::render($e, $request);
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
        if ($request->ajax() || $request->wantsJson()) {
            return Response::json(array('error' => 'Unauthenticated.'), 401);
        }

        $guards = $exception->guards();

        // We will use the first guard.
        $guard = array_shift($guards);

        $path = Config::get("auth.guards.{$guard}.paths.authorize", 'login');

        return Redirect::guest($path)->with('warning', 'Please sign in to access this page.');
    }
}
