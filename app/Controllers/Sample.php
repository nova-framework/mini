<?php

namespace App\Controllers;

use System\Http\Request;

use App\Controllers\BaseController;
use App\Models\User;

use Closure;
use Crypt;
use DB;
use Redirect;
use Session;



class Sample extends BaseController
{

    protected function initialize()
    {
        $request = app('request');

        dump($request->route());
    }

    public function page(Request $request, $slug = null)
    {
        $content = '<p>' .htmlspecialchars($slug);

        return $this->createView(compact('content'), 'Index')->shares('title', 'Page');
    }

    public function post(Request $request, $slug = null)
    {
        $content = '<p>' .htmlspecialchars($slug);

        return $this->createView(compact('content'), 'Index')->shares('title', 'Post');
    }

    public function database(Request $request)
    {
        $content = '';

        $content .= '<h3>QueryBuilder</h3>';

        //
        $query = DB::table('users');

        $users = $query->select('id', 'username', 'email')->whereIn('id', array(1, 3, 4))->get();

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        $query = DB::table('users');

        $users = $query->where('username', '!=', 'admin')
            ->limit(2)
            ->orderBy('realname', 'desc')
            ->get(array('id', 'username', 'realname', 'email'));

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        DB::table('users')->where('username', 'testuser')->delete();

        $query = DB::table('users');

        $userId = $query->insertGetId(array(
            'username'  => 'testuser',
            'password'  => 'password',
            'realname'  => 'Test User',
            'email'     => 'test@testuser.local',
            'activated' => 0,
        ));

        $content .= '<pre>' .var_export($userId, true) .'</pre><br>';

        //
        $query = DB::table('users');

        $user = $query->find($userId);

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        //
        $query = DB::table('users');

        $result = $query->where('id', $userId)->update(array(
            'username'  => 'testuser2',
            'password'  => 'another password',
            'realname'  => 'Updated Test User',
            'email'     => 'test@testuser.local',
            'activated' => 1,
        ));

        $content .= '<pre>' .var_export($result, true) .'</pre><br>';

        //
        $query = DB::table('users');

        $user = $query->find($userId);

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        //
        $query = DB::table('users');

        $result = $query->where('id', $userId)->delete();

        $content .= '<pre>' .var_export($result, true) .'</pre><br>';

        //
        $result = DB::table('users')->where('id', 3)->pluck('username');

        $content .= '<pre>' .var_export($result, true) .'</pre><br>';

        //
        $results = DB::table('users')->where('id', '!=', 1)->lists('realname', 'username');

        $content .= '<pre>' .var_export($results, true) .'</pre><br>';

        $content .= '<br><h3>Models</h3>';

        //
        $user = User::find(1);

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        //
        $users = array_map(function ($model)
        {
            return $model->toArray();

        }, User::all());

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        $users = User::select('id', 'username', 'realname', 'email')
            ->whereIn('id', array(2, 4, 5))
            ->get();

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        $users = User::select('id', 'username', 'realname', 'email')
            ->where('username', '!=', 'admin')
            ->orderBy('realname', 'desc')
            ->limit(2)
            ->get();

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        $user = new User(array(
            'username'  => 'testuser',
            'password'  => 'password',
            'realname'  => 'Test User',
            'email'     => 'test@testuser.local',
            'activated' => 0,
        ));

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        $user->save();

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        //
        $userId = $user->id;

        $content .= '<pre>' .var_export($userId, true) .'</pre><br>';

        //
        $user = User::find($userId);

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        $user->activated = 1;

        $user->save();

        //
        //$user = User::find($userId);

        $content .= '<pre>' .var_export($user, true) .'</pre><br>';

        //
        $users = User::lists('realname', 'id');

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        User::where('id', $user->id)->delete();

        //
        $users = User::lists('realname', 'id');

        $content .= '<pre>' .var_export($users, true) .'</pre><br>';

        //
        $result = User::where('id', 3)->pluck('username');

        $content .= '<pre>' .var_export($result, true) .'</pre><br>';

        //
        $results = User::where('id', '!=', 1)->lists('realname', 'username');

        $content .= '<pre>' .var_export($results, true) .'</pre><br>';

        //
        $content .= '<br><h3>Query Log</h3>';

        $items = array_map(function ($item)
        {
            extract($item);

            $bindings = array_map(function ($value)
            {
                return is_numeric($value) ? $value : sprintf('"%s"', $value);

            }, $bindings);

            return str_replace_array('\?', $bindings, $query);

        }, $log = DB::getQueryLog());

        $content .= '<pre>' .var_export($items, true) .'</pre>';
        $content .= '<pre>' .var_export($log, true) .'</pre>';

        return $this->createView(compact('content'), 'Index')
            ->shares('title', 'Database API & QueryBuilder');
    }

    public function error()
    {
        abort(404, 'Page not found');
    }

    public function redirect()
    {
        return Redirect::to('samples/database');
    }

    public function request(Request $request)
    {
        $content = '';

        //
        $content .= '<pre>' .var_export($request->method(), true) .'</pre>';
        $content .= '<pre>' .var_export($request->path(), true) .'</pre>';

        $content .= '<pre>' .var_export($request, true) .'</pre>';


        return $this->createView(compact('content'), 'Index')
            ->shares('title', 'HTTP Request');
    }
}
