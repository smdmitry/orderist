<?php

class LockDao extends \Phalcon\DI\Injectable
{
    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance;}
    protected function __construct() {}

    const USER = 'user';

    public function lock($name, $id, $ttl = 10)
    {
        $sleeps = [0, 100, 1000, 10000, 100000];
        foreach ($sleeps as $sleep) {
            if ($sleep) usleep($sleep);

            if (BaseMemcache::i()->add("lock_{$name}:" . $id, 1, $ttl)) {
                return true;
            }
        }

        return false;
    }

    public function unlock($name, $id)
    {
        return BaseMemcache::i()->delete("lock_{$name}:" . $id);
    }
}