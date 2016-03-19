<?php

class BaseService extends \Phalcon\DI\Injectable
{
    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    public function setCookie($key, $value, $ttl, $path = '/')
    {
        $_COOKIE[$key] = $value;
        $expire = time() + $ttl;
        setcookie($key, $value, $expire, $path, '.'.\Phalcon\DI::getDefault()->getConfig()['domain'], false, false);
    }

    public function getCookie($key, $default = null)
    {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }

    public function deleteCookie($key)
    {
        return $this->setCookie($key, false, -1);
    }

    public function formatMoney($amount)
    {
        return number_format($amount / 100, 2, '.', ' ');
    }
}