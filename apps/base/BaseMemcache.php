<?php

class BaseMemcache extends \Phalcon\DI\Injectable
{
    /**
     * @var Memcache
     */
    public $mc;

    private $CACHED_TTL = 1;
    private $cacheData   = []; // static cache for page generation
    private $cacheDelete = []; // static cache for deleted keys
    private $cachedTS    = []; // static cache TTL
    private $cachedDelTS  = []; // static cache deleted keys TTL

    public static function i() { static $instance; if (empty($instance)) $instance = new static(); return $instance; }
    private function __construct()
    {
        if (DEBUG) finfo("Memcache connect");
        $this->mc = new Memcache();
        $this->mc->addserver('127.0.0.1', 11211);
    }

    private function _get($key)
    {
        if (DEBUG) {
            $log = is_array($key) ? ('mget ' . implode(',', $key)) : ('get ' . $key);
            finfo("Memcache: {$log}");
        }
        return $this->mc->get($key);
    }

    private function _set($key, $value, $ttl)
    {
        if (DEBUG) finfo("Memcache: set {$key}");
        return $this->mc->set($key, $value, 0, $ttl);
    }

    public function _add($key, $value, $ttl)
    {
        if (DEBUG) finfo("Memcache: add {$key}");
        return $this->mc->add($key, $value, 0, $ttl);
    }

    private function _delete($key)
    {
        if (DEBUG) finfo("Memcache: delete {$key}");
        return $this->mc->delete($key);
    }

    public function get($keys)
    {
        $arrayRequested = is_array($keys);

        if (!$keys) {
            return $arrayRequested ? [] : '';
        }

        $time = microtime(true);
        $keysEmpty = 0;

        // Check in static cache
        if ($arrayRequested) {
            $value = $delkey = [];

            foreach ($keys as $key) {
                if (
                    array_key_exists($key, $this->cacheDelete) &&
                    ($time - $this->cachedDelTS[$key] < $this->CACHED_TTL)
                ) {
                    $value[$key] = false;
                } else {
                    if (array_key_exists($key, $this->cacheData)
                        && isset($this->cachedTS[$key])
                        && ($time - $this->cachedTS[$key] < $this->CACHED_TTL)
                    ) {
                        $value[$key] = $this->cacheData[$key];
                    } else {
                        $delkey[] = $key;
                    }
                }
            }

            // delete keys found in cache
            $keys = $delkey;
            if (empty($keys)) {
                $keysEmpty = 1;
            }
        } else {
            if (
                array_key_exists($keys, $this->cacheDelete) &&
                ($time - $this->cachedDelTS[$keys] < $this->CACHED_TTL)
            ) {
                $value = false;
            } else {
                if (array_key_exists($keys, $this->cacheData)
                    && isset($this->cachedTS[$keys])
                    && ($time - $this->cachedTS[$keys] < $this->CACHED_TTL)
                ) {
                    $value = $this->cacheData[$keys];
                    $keysEmpty = 1;
                }
            }
        }

        // get from memcache
        if (!$keysEmpty) {
            if ($arrayRequested) {
                $mcResult = (array)$this->_get($keys);
            } else {
                $mcResult = $this->_get($keys);
            }

            // and save to cache
            if ($arrayRequested) {
                $value += $mcResult;
                $this->cacheData += $value;
                $this->cachedTS  += array_fill_keys(array_keys($mcResult), $time);
            } else {
                $value = $mcResult;
                $this->cacheData[$keys] = $value;
                $this->cachedTS[$keys] = $time;
            }
        }

        return $value;
    }

    public function set($key, $value, $seconds = 900)
    {
        $seconds = $seconds <= 2592000 ? (int)$seconds : 2592000; // memcached does not set key if TTL > 30 days
        $time = microtime(true);

        $result = $this->_set($key, $value, $seconds);
        if ($result) {
            // save to cache
            $this->cacheData[$key] = $value;
            $this->cachedTS[$key] = $time;
            unset($this->cacheDelete[$key], $this->cachedDelTS[$key]);
        }

        return $result;
    }

    public function add($key, $value, $seconds = 900)
    {
        $seconds = $seconds <= 2592000 ? (int)$seconds : 2592000; // memcached does not set key if TTL > 30 days
        $time = microtime(true);

        $result = $this->_add($key, $value, $seconds);
        if ($result) {
            // save to cache
            $this->cacheData[$key] = $value;
            $this->cachedTS[$key] = $time;
            unset($this->cacheDelete[$key], $this->cachedDelTS[$key]);
        }

        return $result;
    }

    public function delete($key)
    {
        $time = microtime(true);

        //if (
        //    isset($this->cacheDelete[$key]) &&
        //    ($time - $this->cachedDelTS[$key] < $this->CACHED_TTL)
        //) return true; // want to delete 2 times? Why not...
        $result = $this->_delete($key);

        // delete from cache
        unset($this->cacheData[$key], $this->cachedTS[$key]);
        $this->cacheDelete[$key] = true;
        $this->cachedDelTS[$key] = $time;

        return $result;
    }

    public function flushStaticCache($key = null)
    {
        if ($key === null) {
            $this->cacheData = $this->cachedTS = $this->cacheDelete = $this->cachedDelTS = [];
        } else {
            unset($this->cacheData[$key], $this->cachedTS[$key], $this->cacheDelete[$key], $this->cachedDelTS[$key]);
        }
    }
}