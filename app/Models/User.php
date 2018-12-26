<?php

namespace App\Models;

use System\Auth\UserInterface;
use System\Auth\UserTrait;
use System\Database\ORM\Model;



class User extends Model implements UserInterface
{
    use UserTrait;

    //
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = array('username', 'password', 'realname', 'email', 'activated', 'activation_code');

    protected $hidden = array('password', 'remember_token', 'activation_code');
};
