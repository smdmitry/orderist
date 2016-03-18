<?php

class BaseMemcache extends \Phalcon\DI\Injectable
{
    /**
     * @var Memcached
     */
    protected $mc;

    private function __construct()
    {
        $this->mc = new Memcached();
        $this->mc->addServer('127.0.0.1', 11211);
    }

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance; }

    public function get($key)
    {
        return $this->mc->get($key);
    }

    public function set($key, $value, $ttl)
    {
        return $this->mc->set($key, $value, $ttl);
    }

    public function add($key, $value, $ttl)
    {
        return $this->mc->add($key, $value, $ttl);
    }

    public function delete($key)
    {
        return $this->mc->delete($key);
    }
}