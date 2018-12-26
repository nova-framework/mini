<?php

namespace System\View\Middleware;

use System\Support\ViewErrorBag;
use System\View\Factory as ViewFactory;

use Closure;


class ShareErrorsFromSession
{
    /**
     * The view factory implementation.
     *
     * @var \System\Contracts\View\Factory
     */
    protected $view;

    /**
     * Create a new error binder instance.
     *
     * @param  \System\View\Factory  $view
     * @return void
     */
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \System\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->view->share(
            'errors', $request->session()->get('errors', new ViewErrorBag())
        );

        return $next($request);
    }
}
