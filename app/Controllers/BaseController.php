<?php

namespace App\Controllers;

use System\Http\Response;
use System\Routing\Controller;
use System\Support\Facades\View as ViewFactory;
use System\View\View;

use BadMethodCallException;


class BaseController extends Controller
{
    /**
     * The currently requested Action.
     *
     * @var string
     */
    protected $action;

    /**
     * The currently used Layout.
     *
     * @var string
     */
    protected $layout = 'Default';


    protected function initialize()
    {
        //
    }

    public function callAction($method, array $parameters)
    {
        $this->action = $method;

        //
        $this->initialize();

        $response = call_user_func_array(array($this, $method), $parameters);

        if (($response instanceof View) && ! empty($this->layout)) {
            $layout = 'Layouts/' .$this->layout;

            $view = ViewFactory::make($layout, array('content' => $response));

            return new Response($view->render(), 200);
        }

        return $response;
    }

    protected function createView(array $data = array(), $view = null)
    {
        if (is_null($view)) {
            $view = ucfirst($this->action);
        }

        $classPath = str_replace('\\', '/', static::class);

        if (preg_match('#^App/Controllers/(.*)$#', $classPath, $matches) === 1) {
            $view = $matches[1] .'/' .$view;

            return ViewFactory::make($view, $data);
        }

        throw new BadMethodCallException('Invalid Controller namespace');
    }
}
