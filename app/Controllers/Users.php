<?php

namespace App\Controllers;

use Mini\Http\Request;
use Mini\Support\Facades\Auth;
use Mini\Support\Facades\DB;
use Mini\Support\Facades\Hash;
use Mini\Support\Facades\Input;
use Mini\Support\Facades\Redirect;
use Mini\Support\Facades\Request as RequestFacade;
use Mini\Support\Facades\Session;
use Mini\Support\Facades\Validator;
use Mini\Support\Facades\View;

use App\Controllers\BaseController;
use App\Models\User;


class Users extends BaseController
{
    protected $layout = 'Backend';


    protected function validator(Request $request, User $user)
    {
        $rules = array(
            'current_password'      => 'required|valid_password',
            'password'              => 'sometimes|required|strong_password',
            'password_confirmation' => 'sometimes|required|same:password',
        );

        $messages = array(
            'valid_password'  => 'The :attribute field is invalid.',
            'strong_password' => 'The :attribute field is not strong enough.',
        );

        $attributes = array(
            'current_password'      => 'Current Password',
            'password'              => 'New Password',
            'password_confirmation' => 'Password Confirmation',
        );

        // Create a Validator instance.
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        // Add the custom Validation Rule commands.
        $validator->addExtension('valid_password', function($attribute, $value, $parameters) use ($user)
        {
            return Hash::check($value, $user->password);
        });

        $validator->addExtension('strong_password', function ($attribute, $value, $parameters)
        {
            $pattern = "/(?=^.{8,}$)((?=.*\d)|(?=.*\W+))(?![.\n])(?=.*[A-Z])(?=.*[a-z]).*$/";

            return (preg_match($pattern, $value) === 1);
        });

        return $validator;
    }

    protected function initialize()
    {
        View::share('currentUri', RequestFacade::path());
    }

    public function dashboard()
    {
        $debug = '';

        return View::make('Users/Dashboard', compact('debug'))
            ->shares('title', 'Dashboard');
    }

    public function login()
    {
        return View::make('Users/Login')
            ->shares('title', 'User Login');
    }

    public function postLogin(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (! Auth::attempt($credentials, $request->has('remember'))) {
            return Redirect::back()->with('danger', 'Wrong username or password.');
        }

        $user = Auth::user();

        if (Hash::needsRehash($user->password)) {
            $password = $credentials['password'];

            $user->password = Hash::make($password);

            $user->save();
        }

        return Redirect::intended('dashboard')
            ->with('success', sprintf('<b>%s</b>, you have successfully logged in.', $user->username));
    }

    public function logout()
    {
        Auth::logout();

        return Redirect::to('login')->with('success', 'You have successfully logged out.');
    }

    public function profile()
    {
        $user = Auth::user();

        return View::make('Users/Profile', compact('user'))
            ->shares('title',  'User Profile');
    }

    public function postProfile(Request $request)
    {
        $user = Auth::user();

        // Validate the input.
        $validator = $this->validator($request, $user);

        if ($validator->fails()) {
            return Redirect::back()->withInput()->withErrors($validator->errors());
        }

        $password = $request->input('password');

        // Update the authenticated User record.
        $user->password = Hash::make($password);

        $user->save();

        return Redirect::to('profile')->with('message', 'You have successfully updated your Password.');
    }
}
