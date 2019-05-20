<?php

namespace App\Models;

use Mini\Auth\UserInterface;
use Mini\Auth\UserTrait;
use Mini\Database\ORM\Model;



class User extends Model implements UserInterface
{
    use UserTrait;

    //
    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = array('username', 'password', 'realname', 'email', 'activated', 'activation_code');

    protected $hidden = array('password', 'remember_token', 'activation_code');
};
