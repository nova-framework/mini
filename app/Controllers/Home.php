<?php

namespace App\Controllers;

use System\Http\Request;

use App\Controllers\BaseController;


class Home extends BaseController
{
    protected $layout = 'Sample';


    public function index(Request $request)
    {
        $content = 'This is the Homepage';

        //dump($request->route());

        return $this->createView()
            ->shares('title', 'Homepage')
            ->with('content', $content);
    }
}
