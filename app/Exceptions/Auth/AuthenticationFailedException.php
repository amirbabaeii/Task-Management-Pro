<?php

namespace App\Exceptions\Auth;

class AuthenticationFailedException extends \Exception
{
    protected $code = 401;
} 