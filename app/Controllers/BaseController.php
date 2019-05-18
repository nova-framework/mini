<?php

namespace App\Controllers;

use System\Http\Response;
use System\Routing\Controller;
use System\Support\Contracts\RenderableInterface as Renderable;
use System\Support\Facades\View;

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

        // initialize the Controller.
        $this->initialize();

        $response = call_user_func_array(array($this, $method), $parameters);

        return $this->processResponse($response);
    }

    protected function processResponse($response)
    {
        if (($response instanceof Renderable) && ! empty($layout = $this->getLayout())) {
            $view = sprintf('Layouts/%s', $layout);

            $instance = View::make($view, array('content' => $response));

            return new Response($instance->render(), 200);
        }

        return $response;
    }

    protected function createView(array $data = array(), $view = null)
    {
        $classPath = str_replace('\\', '/', static::class);

        if (preg_match('#^App/Controllers/(.*)$#', $classPath, $matches) !== 1) {
            throw new BadMethodCallException('Invalid Controller namespace');
        }

        $view = sprintf('%s/%s', $matches[1], $view ?: ucfirst($this->action));

        return View::make($view, $data);
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getLayout()
    {
        return $this->layout;
    }
}
