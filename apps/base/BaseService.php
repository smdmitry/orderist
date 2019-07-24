<?php

class BaseService extends \Phalcon\DI\Injectable
{
    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    const TIME_HOUR = 3600; // 60 * 60
    const TIME_DAY = 86400; // 24 * 60 * 60
    const TIME_YEAR = 31536000; // 365 * 24 * 60 * 60

    public function setCookie($key, $value, $ttl, $path = '/')
    {
        $_COOKIE[$key] = $value;
        $expire = time() + $ttl;
        $domain = '.'.\Phalcon\DI::getDefault()->getConfig()['domain'];
        return setcookie($key, $value, $expire, $path, $domain, false, false);
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
        $formatted = number_format($amount / 100, 2, '.', ' ');
        return str_replace('.00', '', $formatted);
    }

    public function formatDate($time)
    {
        return strftime('%d %B %Y', $time);
    }

    public function filterText($str, $multiline = false)
    {
        $str = $multiline ?
            preg_replace('! +!', ' ', $str) :
            preg_replace('!\s+!', ' ', $str);
        return htmlspecialchars(trim(strip_tags($str)));
    }

    // CSRF Double Submit Cookies http://smd.im/dVM
    public function setCSRFToken()
    {
        $token = substr(base64_encode(openssl_random_pseudo_bytes(32)), 0, 16);
        return $this->setCookie('simpletoken', $token, self::TIME_YEAR, '/');
    }

    public function checkCSRFToken($token)
    {
        static $cache = null;

        if ($cache === null) {
            $cache = true;

            $cookie = $this->getCookie('simpletoken');
            if (!$cookie || !$token || $cookie != $token) {
                $cache = false;
            }

            $this->setCSRFToken();
        }

        return $cache;
    }
}